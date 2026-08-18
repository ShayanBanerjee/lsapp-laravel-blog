<?php

namespace App\Support\Integrations;

use App\Models\Post;

/**
 * Export a piece as Markdown with YAML frontmatter.
 *
 * This is the whole Obsidian "integration", and deliberately so: Obsidian has
 * no cloud API to integrate with — a vault is a folder of Markdown files on
 * someone's disk. A file in their format, plus the `obsidian://` URI scheme to
 * open it, is not a lesser version of a real integration; it *is* the real
 * integration. The same file also imports cleanly into Bear, Logseq, iA
 * Writer, and anything else that reads Markdown.
 */
class MarkdownExporter
{
    public static function forPost(Post $post): string
    {
        $frontmatter = self::frontmatter([
            'title' => $post->title,
            'date' => $post->published_at?->toDateString(),
            'author' => $post->persona?->display_name ?? $post->user?->name,
            'universe' => $post->universe?->name,
            'tags' => $post->relationLoaded('categories')
                ? $post->categories->pluck('name')->all()
                : [],
            'source' => route('posts.show', $post),
        ]);

        return $frontmatter."\n".self::toMarkdown($post->body)."\n";
    }

    /**
     * A reader's own marks from one piece, as a quote list.
     *
     * The passage is the atom here too — an export of "things I highlighted" is
     * far more useful in a notes app than a copy of the whole article.
     *
     * @param  iterable<object{quote: string}>  $highlights
     */
    public static function forHighlights(Post $post, iterable $highlights): string
    {
        $lines = [self::frontmatter([
            'title' => $post->title.' — highlights',
            'source' => route('posts.show', $post),
        ]), ''];

        foreach ($highlights as $highlight) {
            $lines[] = '> '.str_replace("\n", "\n> ", trim($highlight->quote));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $fields */
    private static function frontmatter(array $fields): string
    {
        $lines = ['---'];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $lines[] = $key.': ['.implode(', ', array_map(self::quote(...), $value)).']';

                continue;
            }

            $lines[] = $key.': '.self::quote((string) $value);
        }

        $lines[] = '---';

        return implode("\n", $lines);
    }

    /** YAML strings are quoted and escaped, so a title with a colon cannot break the document. */
    private static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ' '], $value).'"';
    }

    /**
     * TipTap HTML to Markdown.
     *
     * Only the tags the sanitiser allows can appear, so this is a closed set
     * rather than a general-purpose converter.
     */
    public static function toMarkdown(string $html): string
    {
        $html = preg_replace('#<figure[^>]*data-story="callout"[^>]*data-story-value="([^"]*)"[^>]*>#i', "\n> **$1**\n", $html) ?? $html;

        $replacements = [
            '#<h2[^>]*>(.*?)</h2>#is' => "\n## $1\n",
            '#<h3[^>]*>(.*?)</h3>#is' => "\n### $1\n",
            '#<h4[^>]*>(.*?)</h4>#is' => "\n#### $1\n",
            '#<blockquote[^>]*>(.*?)</blockquote>#is' => "\n> $1\n",
            '#<pre[^>]*>\s*<code[^>]*>(.*?)</code>\s*</pre>#is' => "\n```\n$1\n```\n",
            '#<code[^>]*>(.*?)</code>#is' => '`$1`',
            '#<strong[^>]*>(.*?)</strong>#is' => '**$1**',
            '#<em[^>]*>(.*?)</em>#is' => '_$1_',
            '#<s[^>]*>(.*?)</s>#is' => '~~$1~~',
            '#<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is' => '[$2]($1)',
            '#<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>#is' => "\n![$2]($1)\n",
            '#<img[^>]*src="([^"]*)"[^>]*>#is' => "\n![]($1)\n",
            '#<li[^>]*>(.*?)</li>#is' => "- $1\n",
            '#<hr\s*/?>#i' => "\n---\n",
            '#<br\s*/?>#i' => "\n",
            '#</p>#i' => "\n\n",
        ];

        foreach ($replacements as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html) ?? $html;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse the blank-line pile-up the block replacements leave behind.
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
