<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\Manuscript;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * Zotero.
 *
 * Files a piece as a `blogPost` item in the user's library — the correct item
 * type for something published on the open web, and the one that produces a
 * citation which actually resolves. Filing it as a journal article to look more
 * academic would produce a reference that does not exist.
 */
class Zotero implements PublishesPosts
{
    private const ENDPOINT = 'https://api.zotero.org';

    public function key(): string
    {
        return 'zotero';
    }

    public function label(): string
    {
        return 'Zotero';
    }

    public function blurb(): string
    {
        return 'File a piece in your Zotero library as a citable blog-post item.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://www.zotero.org/settings/keys/new';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'help' => 'Needs write access to your library', 'secret' => true],
            ['key' => 'user_id', 'label' => 'Zotero user ID', 'help' => 'The numeric ID shown on your key settings page', 'secret' => false],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))->get(self::ENDPOINT.'/keys/current');

        return $response->successful()
            ? PushResult::success('Connected to Zotero.')
            : PushResult::failure('Zotero did not accept that key.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $names = preg_split('/\s+/', trim($manuscript->authorName)) ?: [$manuscript->authorName];
        $lastName = array_pop($names);

        $response = Http::withHeaders($this->headers($credentials))
            ->post(self::ENDPOINT.'/users/'.rawurlencode((string) ($credentials['user_id'] ?? '')).'/items', [[
                'itemType' => 'blogPost',
                'title' => $manuscript->title,
                'creators' => [[
                    'creatorType' => 'author',
                    'firstName' => implode(' ', $names),
                    'lastName' => $lastName,
                ]],
                'abstractNote' => $manuscript->abstract,
                'blogTitle' => $manuscript->siteName,
                'websiteType' => 'Blog',
                'date' => $manuscript->publishedAt ?? '',
                'url' => $manuscript->url,
                'accessDate' => now()->toDateString(),
                'tags' => array_map(fn (string $keyword) => ['tag' => $keyword], $manuscript->keywords),
            ]]);

        // Zotero answers 200 with a per-item report rather than an HTTP error,
        // so a failed item inside a successful response has to be read out.
        if ($response->failed() || ! empty($response->json('failed'))) {
            return PushResult::failure('Zotero refused the item.');
        }

        return PushResult::success('Filed in your Zotero library.', 'https://www.zotero.org/mylibrary');
    }

    /** @return array<string, string> */
    private function headers(array $credentials): array
    {
        return [
            'Zotero-API-Key' => $credentials['api_key'] ?? '',
            'Zotero-API-Version' => '3',
        ];
    }
}
