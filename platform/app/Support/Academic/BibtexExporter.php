<?php

namespace App\Support\Academic;

/**
 * A BibTeX entry for citing a piece.
 *
 * `@misc` with a `howpublished` URL is the honest entry type for something
 * published on the open web: pretending an online essay is an `@article` with
 * a journal and a volume produces a citation that does not resolve.
 */
class BibtexExporter
{
    public static function render(Manuscript $manuscript): string
    {
        $fields = array_filter([
            'title' => self::clean($manuscript->title),
            'author' => self::clean($manuscript->authorName),
            'year' => $manuscript->publishedAt ? substr($manuscript->publishedAt, 0, 4) : null,
            'month' => $manuscript->publishedAt ? self::month($manuscript->publishedAt) : null,
            // Left unescaped on purpose: \url{} is a LaTeX command, and
            // stripping its braces would leave a broken macro in the entry.
            'howpublished' => '\url{'.self::url($manuscript->url).'}',
            'note' => self::clean('Published on '.$manuscript->siteName
                .($manuscript->orcid ? '. ORCID: '.$manuscript->orcid : '')),
            'keywords' => $manuscript->keywords !== [] ? self::clean(implode(', ', $manuscript->keywords)) : null,
            'urldate' => now()->toDateString(),
        ]);

        $out = '@misc{'.$manuscript->citationKey().",\n";

        foreach ($fields as $name => $value) {
            // Braces rather than quotes so a value containing a quote does not
            // terminate the field early.
            $out .= sprintf("  %-13s = {%s},\n", $name, $value);
        }

        return rtrim($out, ",\n")."\n}\n";
    }

    /**
     * Remove the two characters that can break out of a braced field.
     *
     * Nothing here is a security boundary — a .bib file is not executed — but
     * an unbalanced brace silently swallows the rest of the entry, which is
     * the kind of failure someone only notices in a bibliography at 2am.
     */
    private static function clean(string $value): string
    {
        return trim(str_replace(['{', '}'], '', $value));
    }

    private static function url(string $url): string
    {
        return str_replace(['{', '}', ' '], ['', '', '%20'], $url);
    }

    private static function month(string $date): string
    {
        return strtolower(date('M', strtotime($date) ?: time()));
    }
}
