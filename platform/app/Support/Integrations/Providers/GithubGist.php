<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\Manuscript;
use App\Support\Academic\MarkdownExporter;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * GitHub Gist.
 *
 * Gists default to secret here. A gist marked public is world-readable and
 * indexed, which is not what "back this up somewhere I control" means to most
 * people who ask for it.
 */
class GithubGist implements PublishesPosts
{
    private const ENDPOINT = 'https://api.github.com';

    public function key(): string
    {
        return 'gist';
    }

    public function label(): string
    {
        return 'GitHub Gist';
    }

    public function blurb(): string
    {
        return 'Save a Markdown copy as a secret gist — a plain-text backup you own.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://github.com/settings/tokens';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'token', 'label' => 'Personal access token', 'help' => 'Needs the `gist` scope and nothing else', 'secret' => true],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = Http::withToken($credentials['token'] ?? '')->get(self::ENDPOINT.'/user');

        return $response->successful()
            ? PushResult::success('Connected to GitHub as @'.$response->json('login').'.')
            : PushResult::failure('GitHub did not accept that token.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $response = Http::withToken($credentials['token'] ?? '')
            ->post(self::ENDPOINT.'/gists', [
                'description' => $manuscript->title.' — '.$manuscript->siteName,
                'public' => false,
                'files' => [
                    Str::slug($manuscript->title).'.md' => ['content' => MarkdownExporter::render($manuscript)],
                ],
            ]);

        if ($response->failed()) {
            return PushResult::failure('GitHub refused the gist: '.$response->json('message', 'unknown error'));
        }

        return PushResult::success('Saved as a secret gist.', $response->json('html_url'));
    }
}
