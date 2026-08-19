<?php

namespace App\Support\Academic;

/**
 * Markdown with YAML frontmatter.
 *
 * This is also the Obsidian integration. Obsidian has no cloud API — there is
 * nothing to authenticate against and no endpoint to post to — so the only
 * honest integration is a file in the format its vault already speaks, plus
 * the `obsidian://` URI that opens it. Advertising anything more would be
 * advertising something that cannot exist.
 */
class MarkdownExporter
{
    public static function render(Manuscript $manuscript): string
    {
        $out = "---\n";
        $out .= 'title: '.self::yaml($manuscript->title)."\n";
        $out .= 'author: '.self::yaml($manuscript->authorName)."\n";

        if ($manuscript->orcid) {
            $out .= 'orcid: '.self::yaml($manuscript->orcid)."\n";
        }

        if ($manuscript->publishedAt) {
            $out .= 'date: '.$manuscript->publishedAt."\n";
        }

        $out .= 'source: '.self::yaml($manuscript->url)."\n";

        if ($manuscript->keywords !== []) {
            $out .= "tags:\n";

            foreach ($manuscript->keywords as $keyword) {
                $out .= '  - '.self::yaml($keyword)."\n";
            }
        }

        $out .= "---\n\n";
        $out .= '# '.$manuscript->title."\n\n";

        if ($manuscript->abstract !== '') {
            $out .= '> '.$manuscript->abstract."\n\n";
        }

        foreach (BlockParser::parse($manuscript->bodyHtml) as $block) {
            $out .= match ($block['type']) {
                'heading' => str_repeat('#', min(6, $block['level']))." {$block['text']}\n\n",
                'quote', 'figure' => '> '.str_replace("\n", "\n> ", $block['text'])."\n\n",
                'code' => "```\n".$block['text']."\n```\n\n",
                'bullets' => implode('', array_map(fn (string $item) => "- {$item}\n", $block['items'] ?? []))."\n",
                'numbers' => implode('', array_map(
                    fn (string $item, int $index) => ($index + 1).". {$item}\n",
                    $block['items'] ?? [],
                    array_keys($block['items'] ?? []),
                ))."\n",
                default => $block['text']."\n\n",
            };
        }

        return $out;
    }

    /**
     * Quote any scalar that YAML would otherwise reinterpret.
     *
     * A title beginning with `#`, or reading as `yes`, or containing a colon,
     * turns into a comment, a boolean or a nested mapping respectively.
     */
    private static function yaml(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ' '], $value).'"';
    }
}
