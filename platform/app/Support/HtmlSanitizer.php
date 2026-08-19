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
        /*
         * Storytelling blocks are lifted out whole before anything else runs.
         *
         * Handling them tag-by-tag alongside the rest would validate the
         * opening tag and leave the closing one behind as an orphan; and since
         * a block's entire content lives in its attributes, the correct unit
         * of decision is the element, not the tag.
         */
        /*
         * The placeholder carries a fresh random nonce on every call.
         *
         * A fixed token would be forgeable: an author could type the literal
         * placeholder into a paragraph and have arbitrary block markup
         * substituted into it after sanitisation. A per-call nonce cannot be
         * guessed by input that was written before the call began.
         *
         * (NUL delimiters were the obvious choice and do not work — strip_tags
         * removes NUL bytes, so the marker would not survive to the reinsert.)
         */
        $nonce = bin2hex(random_bytes(8));
        $placeholder = static fn (int $index): string => "@@story-{$nonce}-{$index}@@";

        $blocks = [];
        $html = preg_replace_callback(
            '#<figure\b([^>]*)>.*?</figure\s*>#is',
            static function (array $match) use (&$blocks, $placeholder): string {
                $figure = StoryBlocks::sanitizeFigure($match[1]);

                if ($figure === null) {
                    return '';
                }

                $blocks[] = $figure.'</figure>';

                return $placeholder(count($blocks) - 1);
            },
            $html
        ) ?? $html;

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

        $html = trim($html ?? '');

        // Put the validated blocks back where their placeholders sit.
        foreach ($blocks as $index => $figure) {
            $html = str_replace($placeholder($index), $figure, $html);
        }

        return $html;
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
