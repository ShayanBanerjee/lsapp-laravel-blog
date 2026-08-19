<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\Manuscript;
use App\Support\Academic\MarkdownExporter;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * DEV (dev.to).
 *
 * Cross-posts arrive as drafts, never live. Publishing straight to someone
 * else's audience from a button here would be a surprise the first time it
 * happened, and DEV supports `canonical_url`, so the original stays canonical
 * for search rather than the two copies competing.
 */
class DevTo implements PublishesPosts
{
    private const ENDPOINT = 'https://dev.to/api';

    public function key(): string
    {
        return 'devto';
    }

    public function label(): string
    {
        return 'DEV';
    }

    public function blurb(): string
    {
        return 'Cross-post to dev.to as a draft, with the canonical URL pointing back here.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://dev.to/settings/extensions';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'help' => 'DEV → Settings → Extensions → DEV Community API Keys', 'secret' => true],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))->get(self::ENDPOINT.'/users/me');

        return $response->successful()
            ? PushResult::success('Connected to DEV.')
            : PushResult::failure('DEV did not accept that API key.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))
            ->post(self::ENDPOINT.'/articles', [
                'article' => array_filter([
                    'title' => $manuscript->title,
                    'body_markdown' => MarkdownExporter::render($manuscript),
                    'published' => false,
                    'canonical_url' => $manuscript->url,
                    // DEV allows at most four tags and rejects the request
                    // outright — not silently — if given more.
                    'tags' => array_slice(array_map(
                        fn (string $keyword) => preg_replace('/[^a-z0-9]/', '', strtolower($keyword)) ?: 'writing',
                        $manuscript->keywords,
                    ), 0, 4),
                ], fn ($value) => $value !== null && $value !== []),
            ]);

        if ($response->failed()) {
            return PushResult::failure('DEV refused the draft: '.$response->json('error', 'unknown error'));
        }

        return PushResult::success('Draft created on DEV.', $response->json('url'));
    }

    /** @return array<string, string> */
    private function headers(array $credentials): array
    {
        return ['api-key' => $credentials['api_key'] ?? '', 'Accept' => 'application/vnd.forem.api-v1+json'];
    }
}
