<?php

namespace App\Support\Academic;

/**
 * Turns a stored body into an ordered list of blocks the exporters can walk.
 *
 * Every exporter needs the same thing — "what are the paragraphs, headings,
 * lists and quotes, in order" — and none of them should be parsing HTML with
 * their own regex. DOMDocument is used rather than regex here because, unlike
 * the sanitizer's fixed-shape job, this genuinely has to understand nesting.
 *
 * Inline markup is deliberately flattened to text. A manuscript exporter that
 * tries to preserve bold-inside-a-link inside a LaTeX environment produces
 * files that do not compile; plain text always does.
 */
class BlockParser
{
    /**
     * @return array<int, array{type: string, text: string, items?: array<int, string>, level?: int}>
     */
    public static function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new \DOMDocument;

        // Suppress warnings from HTML5 elements libxml does not know, and
        // force UTF-8 — without the meta hint libxml assumes Latin-1 and
        // mangles every accented character.
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root) {
            return [];
        }

        $blocks = [];

        foreach ($root->childNodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($node->nodeName);
            $text = self::text($node);

            $block = match (true) {
                $name === 'p' => $text === '' ? null : ['type' => 'paragraph', 'text' => $text],
                in_array($name, ['h2', 'h3', 'h4'], true) => [
                    'type' => 'heading',
                    'text' => $text,
                    'level' => (int) substr($name, 1),
                ],
                $name === 'blockquote' => ['type' => 'quote', 'text' => $text],
                $name === 'pre' => ['type' => 'code', 'text' => $node->textContent],
                in_array($name, ['ul', 'ol'], true) => [
                    'type' => $name === 'ul' ? 'bullets' : 'numbers',
                    'text' => '',
                    'items' => self::items($node),
                ],
                // Storytelling blocks carry their content in attributes; a
                // manuscript wants the words, not the interaction.
                $name === 'figure' => self::figure($node),
                default => null,
            };

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /** @return array<int, string> */
    private static function items(\DOMElement $list): array
    {
        $items = [];

        foreach ($list->getElementsByTagName('li') as $item) {
            $text = self::text($item);

            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }

    /** @return array{type: string, text: string}|null */
    private static function figure(\DOMElement $figure): ?array
    {
        $parts = array_filter([
            $figure->getAttribute('data-value'),
            $figure->getAttribute('data-label'),
            $figure->getAttribute('data-note'),
            $figure->getAttribute('data-caption'),
            str_replace('||', '. ', $figure->getAttribute('data-steps')),
        ]);

        $text = trim(implode(' — ', $parts));

        return $text === '' ? null : ['type' => 'figure', 'text' => $text];
    }

    private static function text(\DOMElement $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
    }
}
