<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\BlockParser;
use App\Support\Academic\Manuscript;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * Ghost (self-hosted or Ghost Pro).
 *
 * The Admin API does not take the key directly — it takes a short-lived JWT
 * signed with the key's secret half, with the key's id in the header and an
 * audience of `/admin/`. Getting any of those three wrong returns the same
 * unhelpful 401, which is why the token is built out in full here.
 */
class Ghost implements PublishesPosts
{
    /** Ghost rejects tokens with a long life; five minutes is generous. */
    private const TOKEN_TTL = 300;

    public function key(): string
    {
        return 'ghost';
    }

    public function label(): string
    {
        return 'Ghost';
    }

    public function blurb(): string
    {
        return 'Cross-post to your own Ghost site as a draft, with the canonical URL kept here.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://ghost.org/docs/admin-api/';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'site_url', 'label' => 'Site URL', 'help' => 'https://yourblog.com', 'secret' => false],
            ['key' => 'admin_key', 'label' => 'Admin API key', 'help' => 'The id:secret pair from a custom integration', 'secret' => true],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $token = $this->token($credentials['admin_key'] ?? '');

        if ($token === null) {
            return PushResult::failure('That admin key is not in the expected id:secret form.');
        }

        $response = Http::withToken($token, 'Ghost')->get($this->base($credentials).'/site/');

        return $response->successful()
            ? PushResult::success('Connected to Ghost.')
            : PushResult::failure('Ghost did not accept that key.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $token = $this->token($credentials['admin_key'] ?? '');

        if ($token === null) {
            return PushResult::failure('That admin key is not in the expected id:secret form.');
        }

        $response = Http::withToken($token, 'Ghost')
            ->post($this->base($credentials).'/posts/?source=html', [
                'posts' => [[
                    'title' => $manuscript->title,
                    'html' => $this->html($manuscript),
                    'status' => 'draft',
                    'canonical_url' => $manuscript->url,
                    'custom_excerpt' => mb_substr($manuscript->abstract, 0, 300) ?: null,
                    'tags' => array_map(fn (string $keyword) => ['name' => $keyword], $manuscript->keywords),
                ]],
            ]);

        if ($response->failed()) {
            return PushResult::failure('Ghost refused the draft.');
        }

        return PushResult::success('Draft created on your Ghost site.', $response->json('posts.0.url'));
    }

    /**
     * Rebuild the body from parsed blocks rather than forwarding stored HTML.
     *
     * Storytelling blocks are ours and mean nothing on another site, and Ghost's
     * HTML-to-Lexical converter is stricter than a browser about what it will
     * accept.
     */
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

        return $html;
    }

    /**
     * A signed JWT for the Admin API.
     *
     * Written by hand rather than pulling in a JWT library: this is one HS256
     * signature over two small JSON objects, and the secret half of the key is
     * hex-encoded, which is the detail most implementations miss.
     */
    private function token(string $adminKey): ?string
    {
        if (! str_contains($adminKey, ':')) {
            return null;
        }

        [$id, $secret] = explode(':', $adminKey, 2);

        if ($id === '' || $secret === '' || ! ctype_xdigit($secret)) {
            return null;
        }

        $header = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $id]));
        $payload = $this->base64Url(json_encode([
            'iat' => time(),
            'exp' => time() + self::TOKEN_TTL,
            'aud' => '/admin/',
        ]));

        $signature = hash_hmac('sha256', $header.'.'.$payload, hex2bin($secret) ?: '', true);

        return $header.'.'.$payload.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base(array $credentials): string
    {
        return rtrim((string) ($credentials['site_url'] ?? ''), '/').'/ghost/api/admin';
    }
}
