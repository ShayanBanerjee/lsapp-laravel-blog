<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Academic\Manuscript;
use App\Support\Academic\SubmissionPackage;
use App\Support\Academic\Venues\VenueRegistry;
use App\Support\Academic\Zenodo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The research studio: preparing a piece for a publication venue.
 *
 * Premium, because it is the feature a researcher would pay for and because
 * preparing a package is real compute. Reading it is not gated — the free tier
 * can see exactly what it does and what it honestly cannot, before deciding.
 */
class StudioController extends Controller
{
    public function show(Request $request, Post $post, VenueRegistry $venues): Response
    {
        $this->authorize('update', $post);

        $post->loadMissing(['persona', 'user', 'categories']);
        $manuscript = Manuscript::fromPost($post);

        $words = str_word_count(strip_tags($post->body));

        return Inertia::render('studio', [
            'post' => [
                'slug' => $post->slug,
                'title' => $post->title,
                'word_count' => $words,
                'reading_time' => $post->reading_time,
                'published' => $post->isPublished(),
            ],
            'venues' => $venues->catalogue(),
            'author' => [
                'name' => $manuscript->authorName,
                'orcid' => $manuscript->orcid,
            ],
            /*
             * Readiness signals, stated plainly rather than as a score.
             *
             * A single "85% ready" number would be invented — these are the
             * specific, checkable things that stop a submission, and naming
             * them is more useful than averaging them.
             */
            'readiness' => [
                ['label' => 'ORCID linked', 'ok' => filled($manuscript->orcid), 'hint' => 'Publishers record it, and it disambiguates you from everyone with your surname.'],
                ['label' => 'Abstract written', 'ok' => trim($manuscript->abstract) !== '', 'hint' => 'The excerpt becomes the abstract. Write one that stands alone.'],
                ['label' => 'Keywords set', 'ok' => $manuscript->keywords !== [], 'hint' => 'Add subjects to the piece; they become the keyword list.'],
                ['label' => 'Substantial length', 'ok' => $words >= 800, 'hint' => 'Most venues expect considerably more than a blog post.'],
                ['label' => 'Preprint DOI available', 'ok' => Zenodo::isConfigured(), 'hint' => 'Depositing with Zenodo mints a citable DOI and a public date of record.'],
            ],
            'canPrepare' => $request->user()->is_premium,
        ]);
    }

    public function download(Request $request, Post $post, string $venue, VenueRegistry $venues): StreamedResponse
    {
        $this->authorize('update', $post);

        // Premium: this is the paid capability, enforced server-side.
        abort_unless($request->user()->is_premium, 403, 'The research studio is part of premium.');

        $target = $venues->find($venue);

        abort_if($target === null, 404);

        $post->loadMissing(['persona', 'user', 'categories']);

        $archive = SubmissionPackage::build($target, Manuscript::fromPost($post));
        $filename = Str::slug($post->title).'-'.$target->key().'-submission.zip';

        return response()->streamDownload(
            fn () => print ($archive),
            $filename,
            [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
