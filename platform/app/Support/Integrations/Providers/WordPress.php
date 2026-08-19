<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\BlockParser;
use App\Support\Academic\Manuscript;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Self-hosted WordPress, via the REST API.
 *
 * Authenticated with an **application password**, not the account password.
 * WordPress issues these per application, they can be revoked individually,
 * and they cannot be used to log into wp-admin — so a leak here does not hand
 * over the site.
 */
class WordPress implements PublishesPosts
{
    public function key(): string
    {
        return 'wordpress';
    }

    public function label(): string
    {
        return 'WordPress';
    }

    public function blurb(): string
    {
        return 'Cross-post to your own WordPress site as a draft.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://wordpress.org/documentation/article/application-passwords/';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'site_url', 'label' => 'Site URL', 'help' => 'https://yourblog.com', 'secret' => false],
            ['key' => 'username', 'label' => 'Username', 'help' => 'Your WordPress user', 'secret' => false],
            [
                'key' => 'app_password',
                'label' => 'Application password',
                // Said plainly, because entering the account password here
                // would be a much worse thing to store and is the mistake
                // people make when the field just says "password".
                'help' => 'Users → Profile → Application Passwords. Not your login password.',
                'secret' => true,
            ],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = $this->client($credentials)->get($this->base($credentials).'/users/me');

        return $response->successful()
            ? PushResult::success('Connected to WordPress as '.$response->json('name').'.')
            : PushResult::failure('WordPress did not accept those details.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $response = $this->client($credentials)->post($this->base($credentials).'/posts', [
            'title' => $manuscript->title,
            'content' => $this->html($manuscript),
            'excerpt' => $manuscript->abstract,
            'status' => 'draft',
        ]);

        if ($response->failed()) {
            return PushResult::failure('WordPress refused the draft: '.$response->json('message', 'unknown error'));
        }

        return PushResult::success('Draft created on your WordPress site.', $response->json('link'));
    }

    private function html(Manuscript $manuscript): string
    {
        $html = '';

        foreach (BlockParser::parse($manuscript->bodyHtml) as $block) {
            $text = e($block['text']);

            $html .= match ($block['type']) {
                'heading' => '<h'.min(4, $block['level']).'>'.$text.'</h'.min(4, $block['level']).'>',
                'quote', 'figure' => '<blockquote><p>'.$text.'</p></blockquote>',
                'code' => '<pre><code>'.$text.'</code></pre>',
                'bullets' => '<ul>'.implode('', array_map(fn ($i) => '<li>'.e($i).'</li>', $block['items'] ?? [])).'</ul>',
                'numbers' => '<ol>'.implode('', array_map(fn ($i) => '<li>'.e($i).'</li>', $block['items'] ?? [])).'</ol>',
                default => '<p>'.$text.'</p>',
            };
        }

        return $html.'<p><em>Originally published at <a href="'.e($manuscript->url).'">'.e($manuscript->url).'</a>.</em></p>';
    }

    private function client(array $credentials): PendingRequest
    {
        return Http::withBasicAuth(
            (string) ($credentials['username'] ?? ''),
            // WordPress shows application passwords in spaced groups of four
            // and accepts them either way; strip so a pasted value works.
            str_replace(' ', '', (string) ($credentials['app_password'] ?? '')),
        )->acceptJson();
    }

    private function base(array $credentials): string
    {
        return rtrim((string) ($credentials['site_url'] ?? ''), '/').'/wp-json/wp/v2';
    }
}
