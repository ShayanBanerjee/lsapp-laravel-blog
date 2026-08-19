<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\Manuscript;

/**
 * IEEE conferences and journals, via the IEEEtran class.
 *
 * IEEE receives submissions through ScholarOne Manuscripts (and PDF eXpress
 * for camera-ready checks). There is no third-party submission API, so this
 * prepares the package and hands off to the portal.
 */
class IeeeVenue extends BaseVenue
{
    public function key(): string
    {
        return 'ieee';
    }

    public function name(): string
    {
        return 'IEEE';
    }

    public function publisher(): string
    {
        return 'Institute of Electrical and Electronics Engineers';
    }

    public function scope(): string
    {
        return 'Engineering, computing and applied science. Conference proceedings and transactions.';
    }

    public function guidelinesUrl(): string
    {
        return 'https://journals.ieeeauthorcenter.ieee.org/create-your-ieee-journal-article/';
    }

    public function portalUrl(): string
    {
        return 'https://mc.manuscriptcentral.com/';
    }

    public function editorialSystem(): string
    {
        return 'ScholarOne Manuscripts';
    }

    public function documentClass(): string
    {
        return 'IEEEtran';
    }

    protected function bibliographyStyle(): string
    {
        return 'IEEEtran';
    }

    protected function preamble(Manuscript $manuscript): string
    {
        return "\\documentclass[conference]{IEEEtran}\n"
            ."\\IEEEoverrideCommandLockouts\n"
            ."\\usepackage[T1]{fontenc}\n"
            ."\\usepackage[utf8]{inputenc}\n"
            ."\\usepackage{cite}\n"
            ."\\usepackage{amsmath,amssymb}\n"
            ."\\usepackage{graphicx}\n"
            ."\\usepackage{url}\n\n"
            ."\\begin{document}\n\n";
    }

    protected function frontMatter(Manuscript $manuscript): string
    {
        $out = '\\title{'.$this->escape($manuscript->title)."}\n\n";

        $out .= '\\author{\\IEEEauthorblockN{'.$this->escape($manuscript->authorName)."}\n";
        $out .= '\\IEEEauthorblockA{'.$this->escape($manuscript->affiliation ?? $manuscript->siteName);

        if ($manuscript->orcid) {
            $out .= "\\\\\nORCID: ".$this->escape($manuscript->orcid);
        }

        $out .= "}}\n\n\\maketitle\n\n";

        if ($manuscript->abstract !== '') {
            $out .= "\\begin{abstract}\n".$this->escape($manuscript->abstract)."\n\\end{abstract}\n\n";
        }

        if ($manuscript->keywords !== []) {
            $out .= "\\begin{IEEEkeywords}\n".$this->keywords($manuscript)."\n\\end{IEEEkeywords}\n\n";
        }

        return $out;
    }

    public function checklist(): array
    {
        return [
            'Confirm the correct IEEEtran option for your venue — conference proceedings and transactions use different ones.',
            'Check the page limit for your specific conference or journal; it is set per venue, not by IEEE globally.',
            'Add author affiliations and email addresses. This package leaves them as placeholders because it cannot know them.',
            'Supply figures as vector PDF or EPS where possible, and confirm every figure is cited in the text.',
            'Run the file through IEEE PDF eXpress before camera-ready submission — it checks font embedding and page geometry.',
            'Confirm whether your venue requires the IEEE copyright notice on the first page.',
            'Add funding and acknowledgement statements if any apply.',
        ];
    }
}
