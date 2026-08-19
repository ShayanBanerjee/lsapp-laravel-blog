<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\Manuscript;
use App\Support\Academic\MarkdownExporter;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * Buttondown.
 *
 * Creates a draft email. Nothing here sends to a subscriber list — an
 * accidental send cannot be recalled, and "publish here" is not consent to
 * email several thousand people.
 */
class Buttondown implements PublishesPosts
{
    private const ENDPOINT = 'https://api.buttondown.email/v1';

    public function key(): string
    {
        return 'buttondown';
    }

    public function label(): string
    {
        return 'Buttondown';
    }

    public function blurb(): string
    {
        return 'Draft a newsletter issue from a piece. Never sends — you press send in Buttondown.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://buttondown.email/settings/programming';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'help' => 'Buttondown → Settings → Programming', 'secret' => true],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))->get(self::ENDPOINT.'/subscribers');

        return $response->successful()
            ? PushResult::success('Connected to Buttondown.')
            : PushResult::failure('Buttondown did not accept that API key.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))
            ->post(self::ENDPOINT.'/emails', [
                'subject' => $manuscript->title,
                'body' => MarkdownExporter::render($manuscript),
                'status' => 'draft',
            ]);

        if ($response->failed()) {
            return PushResult::failure('Buttondown refused the draft.');
        }

        return PushResult::success('Draft issue created in Buttondown.', 'https://buttondown.email/emails');
    }

    /** @return array<string, string> */
    private function headers(array $credentials): array
    {
        return ['Authorization' => 'Token '.($credentials['api_key'] ?? '')];
    }
}
