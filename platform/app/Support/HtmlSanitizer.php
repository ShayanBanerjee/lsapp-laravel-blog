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
    private const ALLOWED_TAGS = '<p><br><strong><em><s><code><pre><blockquote><h2><h3><h4><ul><ol><li><a><hr>'
        .'<figure><figcaption><img>';

    /**
     * Attributes kept, per tag. Everything not listed here is dropped.
     *
     * This is an allowlist of *names* and a validator for each *value*, because
     * a name-only allowlist still lets `src="javascript:…"` through. Nothing
     * here may ever grow to include `style` or anything matching `on*`.
     *
     * The `data-story-*` set is what makes storytelling blocks survive a round
     * trip through the sanitizer — they carry no behaviour themselves, only a
     * label the renderer reads to decide how to present the block.
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt'],
        'figure' => ['data-story', 'data-story-value', 'data-story-label'],
        'figcaption' => [],
    ];

    /** The only storytelling block kinds that may appear in a body. */
    private const STORY_KINDS = ['pinned', 'steps', 'before-after', 'callout'];

    public static function clean(string $html): string
    {
        // strip_tags drops the tags but keeps their text, which would leave
        // script bodies sitting in the post as visible content. Remove these
        // elements together with everything inside them first.
        $html = preg_replace('#<(script|style|template|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|template|iframe|object|embed)\b[^>]*/?>#i', '', $html) ?? $html;

        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Rebuild every tag from scratch, keeping only allowlisted attributes
        // with values that pass their own validator. Rebuilding rather than
        // filtering means a malformed or duplicated attribute cannot survive by
        // hiding in the part of the string we did not rewrite.
        $html = preg_replace_callback(
            '/<([a-z0-9]+)\b([^>]*)>/i',
            static function (array $match): string {
                $tag = strtolower($match[1]);
                $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

                if ($allowed === []) {
                    return '<'.$tag.'>';
                }

                $kept = '';

                foreach ($allowed as $name) {
                    if (! preg_match('/\b'.preg_quote($name, '/').'\s*=\s*("|\')(.*?)\1/i', $match[2], $found)) {
                        continue;
                    }

                    $value = self::attributeValue($tag, $name, trim(html_entity_decode($found[2])));

                    if ($value === null) {
                        continue;
                    }

                    $kept .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
                }

                // Outbound links are never same-origin trusted.
                if ($tag === 'a') {
                    return $kept === '' ? '<a>' : '<a'.$kept.' rel="noopener nofollow" target="_blank">';
                }

                return '<'.$tag.$kept.'>';
            },
            $html
        );

        return trim($html ?? '');
    }

    /**
     * Validate one attribute value, or reject it.
     *
     * Returns null to drop the attribute entirely rather than to empty it —
     * an `<img src="">` is a broken image, an absent src is just no image.
     */
    private static function attributeValue(string $tag, string $name, string $value): ?string
    {
        return match (true) {
            // Allowlist schemes rather than blocklisting javascript:, which is
            // trivially bypassed with entities, whitespace and casing.
            $name === 'href' => preg_match('#^(https?://|mailto:|/)#i', $value) ? $value : null,

            // Images may come from our own storage or an https source. No
            // data: URIs — they are an SVG-script vector.
            $name === 'src' => preg_match('#^(https://|/)#i', $value) ? $value : null,

            $name === 'data-story' => in_array($value, self::STORY_KINDS, true) ? $value : null,

            // Free text, but bounded and stripped of markup.
            $name === 'alt', $name === 'data-story-label' => mb_substr(strip_tags($value), 0, 200),

            $name === 'data-story-value' => mb_substr(strip_tags($value), 0, 40),

            default => null,
        };
    }

    /**
     * Reduce untrusted input to plain text.
     *
     * For every field stored and displayed as text rather than markup:
     * responses, letters, highlight quotes.
     *
     * Note this is *not* just strip_tags. strip_tags removes the tags but keeps
     * their contents, so "<script>alert(1)</script>" becomes the visible string
     * "alert(1)" — harmless where React escapes it, but garbage in the database
     * and a live hazard the moment anyone renders the field as HTML.
     */
    public static function plain(string $input, int $max = 4000): string
    {
        $input = preg_replace('#<(script|style|template|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $input) ?? $input;
        $input = preg_replace('#<(script|style|template|iframe|object|embed)\b[^>]*/?>#i', '', $input) ?? $input;

        return mb_substr(trim(strip_tags($input)), 0, $max);
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
