<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Academic\Manuscript;
use App\Support\Academic\MarkdownExporter;
use App\Support\Academic\Zenodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Depositing a piece with Zenodo to mint a DOI.
 *
 * Two steps, and the split is the point: `store` creates a **draft** deposition
 * with the file attached, and `publish` is what actually mints the DOI. A
 * published Zenodo record cannot be withdrawn — only superseded by a new
 * version — so the irreversible half is never a side effect of the reversible
 * one.
 */
class DepositController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);
        abort_unless(Zenodo::isConfigured(), 503, 'Zenodo is not configured.');

        // Only published work: a DOI is a permanent public claim, and minting
        // one for a draft creates a citable record of something nobody can read.
        if (! $post->isPublished()) {
            return back()->with('error', 'Publish the piece here before depositing it.');
        }

        $post->loadMissing(['persona', 'user', 'categories']);
        $manuscript = Manuscript::fromPost($post);

        try {
            $deposit = Zenodo::deposit(
                $manuscript,
                Str::slug($post->title).'.md',
                MarkdownExporter::render($manuscript),
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Zenodo did not accept the deposit. Nothing was published.');
        }

        return back()->with('success', sprintf(
            'Draft deposited with Zenodo (#%d). Review it there, then publish to mint the DOI.',
            $deposit['id'],
        ));
    }
}
