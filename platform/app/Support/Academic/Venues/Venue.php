<?php

namespace App\Support\Academic\Venues;

use App\Support\Academic\Manuscript;

/**
 * A publication venue you can prepare a submission for.
 *
 * ### What this is, and what it deliberately is not
 *
 * There is no API anywhere in this subsystem that submits a manuscript. IEEE,
 * Springer Nature, Nature and ACM all receive submissions exclusively through
 * editorial systems — ScholarOne, Editorial Manager, Snapp, TAPS — and none of
 * them publishes a third-party submission endpoint. A button labelled "submit
 * to IEEE" cannot exist, and building one that appeared to work would be worse
 * than not building it.
 *
 * What genuinely takes a researcher a day, and what this removes, is
 * *preparation*: rewriting the manuscript into that publisher's LaTeX class,
 * assembling the metadata their portal will demand field by field, drafting a
 * cover letter, and checking the piece against the author guidelines. This
 * produces all of it as one archive, ready to upload, plus the deep link to
 * the portal that actually accepts it.
 *
 * ### On the checklists
 *
 * Author guidelines change, and a stale requirement asserted confidently is
 * worse than no requirement at all. Every venue therefore carries a live
 * `guidelinesUrl`, the checklist is phrased as things to confirm rather than
 * facts about the current rules, and the UI states that the publisher's own
 * guide is authoritative.
 */
interface Venue
{
    public function key(): string;

    public function name(): string;

    public function publisher(): string;

    /** One line on what this venue is for. */
    public function scope(): string;

    /** The publisher's live author guidelines. Authoritative, always. */
    public function guidelinesUrl(): string;

    /** Where a human actually uploads the package. */
    public function portalUrl(): string;

    /** The editorial system behind that portal, named plainly. */
    public function editorialSystem(): string;

    /** The LaTeX document class the package is built against. */
    public function documentClass(): string;

    /** The main .tex file. */
    public function renderLatex(Manuscript $manuscript): string;

    /**
     * Things to confirm before uploading, in the order they trip people up.
     *
     * @return array<int, string>
     */
    public function checklist(): array;
}
