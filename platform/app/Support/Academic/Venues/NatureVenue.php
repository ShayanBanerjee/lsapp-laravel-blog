<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\Manuscript;

/**
 * Nature Portfolio journals.
 *
 * Nature accepts LaTeX or Word and does not mandate a house class at initial
 * submission — what it enforces is structure and length, and those limits
 * differ by journal and by article type. The package therefore builds against
 * a plain article class and puts the real work into the checklist, which is
 * where Nature submissions actually go wrong.
 */
class NatureVenue extends BaseVenue
{
    public function key(): string
    {
        return 'nature';
    }

    public function name(): string
    {
        return 'Nature Portfolio';
    }

    public function publisher(): string
    {
        return 'Springer Nature';
    }

    public function scope(): string
    {
        return 'Findings of broad significance across the natural sciences. Extremely selective.';
    }

    public function guidelinesUrl(): string
    {
        return 'https://www.nature.com/nature/for-authors/formatting-guide';
    }

    public function portalUrl(): string
    {
        return 'https://mts-nature.nature.com/';
    }

    public function editorialSystem(): string
    {
        return 'eJournalPress';
    }

    public function documentClass(): string
    {
        return 'article';
    }

    protected function bibliographyStyle(): string
    {
        return 'naturemag';
    }

    protected function preamble(Manuscript $manuscript): string
    {
        return "% Nature Portfolio accepts LaTeX or Word at initial submission and\n"
            ."% does not require a house document class. Length and structure are\n"
            ."% what is enforced, and both vary by journal and article type —\n"
            ."% check the formatting guide for your specific title.\n"
            ."\\documentclass[11pt,a4paper]{article}\n\n"
            ."\\usepackage[T1]{fontenc}\n"
            ."\\usepackage[utf8]{inputenc}\n"
            ."\\usepackage{graphicx}\n"
            ."\\usepackage{amsmath,amssymb}\n"
            ."\\usepackage[margin=1in]{geometry}\n"
            ."\\usepackage{setspace}\n"
            ."\\usepackage{lineno}\n\n"
            ."% Nature asks for double spacing and line numbers on the\n"
            ."% submitted manuscript to make review practical.\n"
            ."\\doublespacing\n"
            ."\\linenumbers\n\n"
            ."\\begin{document}\n\n";
    }

    protected function frontMatter(Manuscript $manuscript): string
    {
        $out = '\\title{'.$this->escape($manuscript->title)."}\n";
        $out .= '\\author{'.$this->escape($manuscript->authorName);

        if ($manuscript->orcid) {
            $out .= '\\thanks{ORCID: '.$this->escape($manuscript->orcid).'}';
        }

        $out .= "}\n";
        $out .= "\\date{}\n\n";
        $out .= "\\maketitle\n\n";

        if ($manuscript->abstract !== '') {
            $out .= "\\begin{abstract}\n".$this->escape($manuscript->abstract)."\n\\end{abstract}\n\n";
        }

        return $out;
    }

    public function checklist(): array
    {
        return [
            'Check the word limit and abstract limit for your specific Nature Portfolio journal and article type — they differ substantially between Articles, Letters and the sister journals.',
            'Nature abstracts are written for a general scientific readership. The first sentences should be intelligible to someone outside your field.',
            'Prepare a cover letter explaining why the work is of broad significance. For Nature this is not a formality; it is read.',
            'Assemble the reporting summary and any editorial policy checklists the journal requires.',
            'Write a data availability statement and a code availability statement, with accession numbers or repository DOIs.',
            'Declare competing interests and author contributions explicitly.',
            'Consider posting the preprint first — Nature Portfolio permits preprints, and depositing one gives you a citable DOI and a public date of record.',
            'Confirm figure and extended-data limits, and whether supplementary information is submitted separately.',
        ];
    }
}
