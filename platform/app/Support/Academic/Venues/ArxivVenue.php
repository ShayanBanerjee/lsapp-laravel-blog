<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\Manuscript;

/**
 * arXiv.
 *
 * Included because it is the one venue here where the honest answer to "can I
 * put this in front of researchers today" is yes. arXiv is not peer review; it
 * is a public, timestamped, citable record, and posting there first is
 * compatible with later submission to most of the other venues on this list.
 */
class ArxivVenue extends BaseVenue
{
    public function key(): string
    {
        return 'arxiv';
    }

    public function name(): string
    {
        return 'arXiv';
    }

    public function publisher(): string
    {
        return 'Cornell University';
    }

    public function scope(): string
    {
        return 'Open preprint archive. Not peer reviewed — a public, citable record with a date stamp.';
    }

    public function guidelinesUrl(): string
    {
        return 'https://info.arxiv.org/help/submit/index.html';
    }

    public function portalUrl(): string
    {
        return 'https://arxiv.org/submit';
    }

    public function editorialSystem(): string
    {
        return 'arXiv submission system (moderated, not peer reviewed)';
    }

    public function documentClass(): string
    {
        return 'article';
    }

    protected function preamble(Manuscript $manuscript): string
    {
        return "\\documentclass[11pt,a4paper]{article}\n\n"
            ."\\usepackage[T1]{fontenc}\n"
            ."\\usepackage[utf8]{inputenc}\n"
            ."\\usepackage{graphicx}\n"
            ."\\usepackage{amsmath,amssymb}\n"
            ."\\usepackage{hyperref}\n"
            ."\\usepackage[margin=1in]{geometry}\n\n"
            ."\\begin{document}\n\n";
    }

    protected function frontMatter(Manuscript $manuscript): string
    {
        $out = '\\title{'.$this->escape($manuscript->title)."}\n";
        $out .= '\\author{'.$this->escape($manuscript->authorName)."}\n";
        $out .= '\\date{'.($manuscript->publishedAt ?? '\today')."}\n\n";
        $out .= "\\maketitle\n\n";

        if ($manuscript->abstract !== '') {
            $out .= "\\begin{abstract}\n".$this->escape($manuscript->abstract)."\n\\end{abstract}\n\n";
        }

        return $out;
    }

    public function checklist(): array
    {
        return [
            'Choose the right primary category. Moderators reclassify submissions that are filed in the wrong one, which delays announcement.',
            'A first-time submitter to some categories needs endorsement from an existing author.',
            'arXiv compiles your LaTeX itself. Upload source, not a PDF, unless the category permits PDF-only.',
            'Check your target journal’s preprint policy first. Most permit arXiv posting; a small number still do not.',
            'Once announced, a submission cannot be withdrawn — only superseded by a new version. Read it once more.',
        ];
    }
}
