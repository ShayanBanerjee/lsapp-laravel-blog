<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\Manuscript;

/** ACM conferences and journals, via acmart. */
class AcmVenue extends BaseVenue
{
    public function key(): string
    {
        return 'acm';
    }

    public function name(): string
    {
        return 'ACM';
    }

    public function publisher(): string
    {
        return 'Association for Computing Machinery';
    }

    public function scope(): string
    {
        return 'Computing research. Conference proceedings and ACM journals.';
    }

    public function guidelinesUrl(): string
    {
        return 'https://www.acm.org/publications/proceedings-template';
    }

    public function portalUrl(): string
    {
        return 'https://www.acm.org/publications/authors/submissions';
    }

    public function editorialSystem(): string
    {
        return 'TAPS, usually reached through the venue’s own submission site';
    }

    public function documentClass(): string
    {
        return 'acmart';
    }

    protected function bibliographyStyle(): string
    {
        return 'ACM-Reference-Format';
    }

    protected function preamble(Manuscript $manuscript): string
    {
        return "% acmart template option must match the venue:\n"
            ."%   sigconf | sigplan | acmsmall | acmlarge | manuscript (for review)\n"
            ."\\documentclass[sigconf]{acmart}\n\n"
            ."\\usepackage{graphicx}\n\n"
            ."\\begin{document}\n\n";
    }

    protected function frontMatter(Manuscript $manuscript): string
    {
        $out = '\\title{'.$this->escape($manuscript->title)."}\n\n";

        $out .= '\\author{'.$this->escape($manuscript->authorName)."}\n";

        if ($manuscript->orcid) {
            $out .= '\\orcid{'.$this->escape($manuscript->orcid)."}\n";
        }

        $out .= "\\affiliation{%\n  \\institution{".$this->escape($manuscript->affiliation ?? $manuscript->siteName)."}\n}\n";
        $out .= "\\email{author@example.org}\n\n";

        if ($manuscript->abstract !== '') {
            $out .= "\\begin{abstract}\n".$this->escape($manuscript->abstract)."\n\\end{abstract}\n\n";
        }

        if ($manuscript->keywords !== []) {
            $out .= '\\keywords{'.$this->keywords($manuscript)."}\n\n";
        }

        $out .= "\\maketitle\n\n";

        return $out;
    }

    public function checklist(): array
    {
        return [
            'Set the acmart option for your venue. Use the `manuscript` option for review copies and the venue’s own option for camera-ready.',
            'Add CCS concepts through the ACM Computing Classification System tool — acmart expects the generated CCSXML block.',
            'ACM requires the rights block, which is generated from the copyright form after acceptance. Leave it out at review time.',
            'Check whether your venue reviews double-blind. If so, remove author names, affiliations and self-identifying acknowledgements.',
            'Add ORCID for every author; ACM records them.',
            'Confirm the page limit and whether references count toward it.',
        ];
    }
}
