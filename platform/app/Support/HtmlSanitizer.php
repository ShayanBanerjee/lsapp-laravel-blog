<?php

namespace App\Support;

/**
 * Post bodies are rich text produced by TipTap and rendered with
 * dangerouslySetInnerHTML, so they are a stored-XSS sink. Everything is
 * stripped to a tag allowlist on the way in, and the few attributes we keep
 * are re-validated (notably: link hrefs, to block javascript: URLs).
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><s><code><pre><blockquote><h2><h3><h4><ul><ol><li><a><hr>';

    public static function clean(string $html): string
    {
        // strip_tags drops the tags but keeps their text, which would leave
        // script bodies sitting in the post as visible content. Remove these
        // elements together with everything inside them first.
        $html = preg_replace('#<(script|style|template|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|template|iframe|object|embed)\b[^>]*/?>#i', '', $html) ?? $html;

        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Drop every attribute except href on anchors.
        $html = preg_replace_callback(
            '/<([a-z0-9]+)\b([^>]*)>/i',
            static function (array $match): string {
                $tag = strtolower($match[1]);

                if ($tag !== 'a') {
                    return '<'.$tag.'>';
                }

                if (! preg_match('/\bhref\s*=\s*("|\')(.*?)\1/i', $match[2], $href)) {
                    return '<a>';
                }

                $url = trim(html_entity_decode($href[2]));

                // Allowlist the schemes rather than blocklisting javascript:,
                // which is trivially bypassed with entities and whitespace.
                if (! preg_match('#^(https?://|mailto:|/)#i', $url)) {
                    return '<a>';
                }

                return '<a href="'.htmlspecialchars($url, ENT_QUOTES).'" rel="noopener nofollow" target="_blank">';
            },
            $html
        );

        return trim($html ?? '');
    }

    /** Plain-text excerpt for cards and meta descriptions. */
    public static function excerpt(string $html, int $length = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length).'…';
    }
}
