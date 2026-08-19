<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\Manuscript;

/**
 * Springer Nature journals, via the sn-jnl class.
 *
 * Springer Nature titles are submitted through Editorial Manager or Snapp
 * depending on the journal. Neither exposes a submission API to third parties,
 * so this prepares the manuscript and links to the journal's own portal.
 */
class SpringerVenue extends BaseVenue
{
    public function key(): string
    {
        return 'springer';
    }

    public function name(): string
    {
        return 'Springer Nature journals';
    }

    public function publisher(): string
    {
        return 'Springer Nature';
    }

    public function scope(): string
    {
        return 'A very wide range of disciplines across several thousand journals.';
    }

    public function guidelinesUrl(): string
    {
        return 'https://www.springernature.com/gp/authors/campaigns/latex-author-support';
    }

    public function portalUrl(): string
    {
        return 'https://www.springernature.com/gp/authors';
    }

    public function editorialSystem(): string
    {
        return 'Editorial Manager or Snapp, depending on the journal';
    }

    public function documentClass(): string
    {
        return 'sn-jnl';
    }

    protected function bibliographyStyle(): string
    {
        return 'sn-basic';
    }

    protected function preamble(Manuscript $manuscript): string
    {
        return "% sn-jnl ships in the Springer Nature LaTeX template bundle.\n"
            ."% The reference style option must match your target journal:\n"
            ."%   sn-basic | sn-mathphys-num | sn-mathphys-ay | sn-aps | sn-vancouver | sn-apa\n"
            ."\\documentclass[sn-basic]{sn-jnl}\n\n"
            ."\\usepackage{graphicx}\n"
            ."\\usepackage{amsmath,amssymb}\n\n"
            ."\\begin{document}\n\n";
    }

    protected function frontMatter(Manuscript $manuscript): string
    {
        $out = '\\title['.$this->escape(mb_substr($manuscript->title, 0, 60)).']{'
            .$this->escape($manuscript->title)."}\n\n";

        $out .= '\\author[1]{\\fnm{}\\sur{'.$this->escape($manuscript->authorName).'}}';
        $out .= "\\email{author@example.org}\n\n";
        $out .= '\\affil[1]{\\orgname{'.$this->escape($manuscript->affiliation ?? $manuscript->siteName)."}}\n\n";

        if ($manuscript->orcid) {
            $out .= '% ORCID: '.$this->escape($manuscript->orcid)."\n\n";
        }

        if ($manuscript->abstract !== '') {
            $out .= '\\abstract{'.$this->escape($manuscript->abstract)."}\n\n";
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
            'Pick the sn-jnl reference-style option your target journal requires — the default here will be wrong for many of them.',
            'Springer Nature journals have individual scope and format rules. Read the specific journal page, not just the group guidance.',
            'Fill in author given names, surnames, emails and affiliations; the template needs them structured, not as one string.',
            'Add a declarations section: funding, competing interests, data availability, ethics approval where applicable.',
            'Confirm whether your journal is open access and what the article processing charge is before submitting.',
            'Check whether the journal wants figures inline or as separate files at submission.',
        ];
    }
}
