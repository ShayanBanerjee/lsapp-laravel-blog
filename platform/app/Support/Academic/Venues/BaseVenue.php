<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\BlockParser;
use App\Support\Academic\LatexExporter;
use App\Support\Academic\Manuscript;

/**
 * Shared LaTeX assembly.
 *
 * Body rendering is identical across venues — it is the preamble, the
 * front-matter macros and the checklist that differ — so subclasses supply
 * those three and inherit the rest.
 */
abstract class BaseVenue implements Venue
{
    /** The preamble, up to and including \begin{document}. */
    abstract protected function preamble(Manuscript $manuscript): string;

    /** Title block, abstract and keywords, in this venue's macros. */
    abstract protected function frontMatter(Manuscript $manuscript): string;

    public function renderLatex(Manuscript $manuscript): string
    {
        $out = '% Prepared for '.$this->name().' by '.config('app.name')."\n";
        $out .= '% Source: '.$manuscript->url."\n";
        $out .= '% Document class: '.$this->documentClass()."\n";
        $out .= "%\n";
        $out .= "% This is a starting point, not a finished submission. Check it\n";
        $out .= "% against the current author guidelines before you upload:\n";
        $out .= '%   '.$this->guidelinesUrl()."\n\n";

        $out .= $this->preamble($manuscript);
        $out .= $this->frontMatter($manuscript);
        $out .= $this->body($manuscript);
        $out .= "\n\\bibliographystyle{".$this->bibliographyStyle()."}\n";
        $out .= "% \\bibliography{references}\n";
        $out .= "\n\\end{document}\n";

        return $out;
    }

    protected function bibliographyStyle(): string
    {
        return 'plain';
    }

    /** The manuscript body, as sections. */
    protected function body(Manuscript $manuscript): string
    {
        $out = '';

        foreach (BlockParser::parse($manuscript->bodyHtml) as $block) {
            $text = LatexExporter::escape($block['text']);

            $out .= match ($block['type']) {
                'heading' => match ($block['level']) {
                    2 => "\n\\section{{$text}}\n\n",
                    3 => "\n\\subsection{{$text}}\n\n",
                    default => "\n\\subsubsection{{$text}}\n\n",
                },
                'quote', 'figure' => "\\begin{quote}\n{$text}\n\\end{quote}\n\n",
                // verbatim takes its content raw by definition.
                'code' => "\\begin{verbatim}\n".$block['text']."\n\\end{verbatim}\n\n",
                'bullets' => $this->list('itemize', $block['items'] ?? []),
                'numbers' => $this->list('enumerate', $block['items'] ?? []),
                default => $text."\n\n",
            };
        }

        return $out;
    }

    /** @param  array<int, string>  $items */
    protected function list(string $environment, array $items): string
    {
        if ($items === []) {
            return '';
        }

        $out = "\\begin{{$environment}}\n";

        foreach ($items as $item) {
            $out .= '  \item '.LatexExporter::escape($item)."\n";
        }

        return $out."\\end{{$environment}}\n\n";
    }

    protected function escape(string $value): string
    {
        return LatexExporter::escape($value);
    }

    /** Comma-separated, escaped keywords. */
    protected function keywords(Manuscript $manuscript): string
    {
        return $this->escape(implode(', ', $manuscript->keywords));
    }
}
