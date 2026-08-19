<?php

namespace App\Support\Academic;

use App\Support\Academic\Venues\Venue;
use RuntimeException;
use ZipArchive;

/**
 * Everything a publisher's portal will ask for, in one archive.
 *
 * The value here is not any single file — it is that the whole set is
 * consistent and arrives together. Reformatting a manuscript into a
 * publisher's class, assembling metadata field by field to retype into a
 * portal, drafting a cover letter and working through the author guidelines is
 * most of a day's work, and it is work that has to be redone from scratch for
 * every venue you try.
 *
 * The archive is deliberately plain: real files, no proprietary container, all
 * text except the manuscript DOCX. A researcher can open any of it, edit it,
 * and upload it without this platform being involved again.
 */
class SubmissionPackage
{
    public static function build(Venue $venue, Manuscript $manuscript, ?string $coverLetter = null): string
    {
        $file = tempnam(sys_get_temp_dir(), 'inkfathom-submission-');

        if ($file === false) {
            throw new RuntimeException('Could not allocate a temporary file for the package.');
        }

        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not open the package archive for writing.');
        }

        $zip->addFromString('README.md', self::readme($venue, $manuscript));
        $zip->addFromString('manuscript.tex', $venue->renderLatex($manuscript));
        $zip->addFromString('manuscript.docx', DocxExporter::render($manuscript));
        $zip->addFromString('references.bib', BibtexExporter::render($manuscript));
        $zip->addFromString('metadata.json', self::metadata($venue, $manuscript));
        $zip->addFromString('cover-letter.md', $coverLetter ?? self::coverLetter($venue, $manuscript));
        $zip->addFromString('CHECKLIST.md', self::checklist($venue));
        $zip->close();

        $contents = file_get_contents($file);
        unlink($file);

        if ($contents === false) {
            throw new RuntimeException('Could not read the generated package.');
        }

        return $contents;
    }

    private static function readme(Venue $venue, Manuscript $manuscript): string
    {
        return <<<MD
        # Submission package — {$venue->name()}

        Prepared by {$manuscript->siteName} from:
        {$manuscript->url}

        ## What is in here

        | File | What it is |
        |---|---|
        | `manuscript.tex` | Your piece in the `{$venue->documentClass()}` document class |
        | `manuscript.docx` | The same text as a Word document, for venues that prefer it |
        | `references.bib` | A BibTeX entry citing the original |
        | `metadata.json` | The fields the submission portal will ask you for |
        | `cover-letter.md` | A draft cover letter. Rewrite it — editors can tell |
        | `CHECKLIST.md` | What to confirm before you upload |

        ## What this is not

        **This has not been submitted anywhere.** {$venue->name()} receives
        manuscripts through {$venue->editorialSystem()}, which does not offer a
        third-party submission API — so no tool can submit on your behalf, and
        any that claims to is not doing what it says.

        What this removes is the day of reformatting. Upload it yourself:

        {$venue->portalUrl()}

        ## The guidelines are authoritative

        Author guidelines change, and the checklist here is a starting point
        rather than a current statement of the rules. Read:

        {$venue->guidelinesUrl()}

        MD;
    }

    private static function metadata(Venue $venue, Manuscript $manuscript): string
    {
        return json_encode([
            'venue' => [
                'name' => $venue->name(),
                'publisher' => $venue->publisher(),
                'editorial_system' => $venue->editorialSystem(),
                'portal' => $venue->portalUrl(),
                'guidelines' => $venue->guidelinesUrl(),
            ],
            'manuscript' => [
                'title' => $manuscript->title,
                'abstract' => $manuscript->abstract,
                'keywords' => $manuscript->keywords,
                'word_count' => str_word_count(strip_tags($manuscript->bodyHtml)),
                'original_url' => $manuscript->url,
                'first_published' => $manuscript->publishedAt,
            ],
            'authors' => [[
                'name' => $manuscript->authorName,
                'orcid' => $manuscript->orcid,
                'affiliation' => $manuscript->affiliation,
                // Left blank rather than guessed: a portal will reject a wrong
                // corresponding-author address, and inventing one is worse than
                // an obvious gap.
                'email' => null,
                'is_corresponding' => true,
            ]],
            'declarations' => [
                'competing_interests' => null,
                'funding' => null,
                'data_availability' => null,
                'author_contributions' => null,
            ],
            'prepared_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A cover letter draft.
     *
     * Deliberately left with visible gaps in square brackets. A cover letter
     * that reads as generated is worse than none, and an editor spots one
     * immediately — so this gives structure and refuses to fake the substance.
     */
    private static function coverLetter(Venue $venue, Manuscript $manuscript): string
    {
        $date = now()->format('j F Y');

        return <<<MD
        {$date}

        Dear Editor,

        I am submitting *{$manuscript->title}* for consideration at {$venue->name()}.

        [One or two sentences on what the work shows. State the finding, not the
        topic — "we show X" rather than "we investigate X".]

        [Why it belongs in this venue specifically. Name the readership and,
        where you can, a paper this one answers or extends.]

        [What is new. Be direct about the contribution and equally direct about
        the limits — reviewers find them anyway, and naming them yourself reads
        as confidence rather than weakness.]

        {$manuscript->orcid}

        The work has not been published elsewhere and is not under consideration
        at another journal. [Amend this if a preprint exists — say where, and
        give the DOI.]

        Yours sincerely,
        {$manuscript->authorName}

        ---

        *Draft prepared by {$manuscript->siteName}. Rewrite it in your own voice
        before sending; the bracketed sections are the parts only you can write.*
        MD;
    }

    private static function checklist(Venue $venue): string
    {
        $items = collect($venue->checklist())
            ->map(fn (string $item) => "- [ ] {$item}")
            ->implode("\n");

        return <<<MD
        # Before you upload — {$venue->name()}

        {$items}

        ## Always

        - [ ] Every figure and table is cited in the text, in order.
        - [ ] Every reference in the bibliography is cited, and every citation resolves.
        - [ ] Author names, affiliations and the corresponding email are filled in.
        - [ ] The abstract stands alone and contains no undefined abbreviations.
        - [ ] The manuscript compiles cleanly from source, with no missing packages.

        The publisher's own guidelines are authoritative and change over time:
        {$venue->guidelinesUrl()}
        MD;
    }
}
