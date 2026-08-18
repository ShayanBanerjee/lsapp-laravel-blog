<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Post;
use App\Support\PostPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reader's own shelf. Private by construction — every query is scoped to
 * the authenticated user, and nothing here is ever exposed publicly.
 */
class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $kind = $request->query('kind') === Bookmark::STARRED ? Bookmark::STARRED : Bookmark::SAVED;

        $posts = Post::query()
            ->whereHas('bookmarks', fn ($q) => $q->where('user_id', $user->id)->where('kind', $kind))
            ->with(['persona', 'universe'])
            ->withCount('highlights')
            // Order by when it was shelved, not when it was published — the
            // shelf is a record of the reader's own activity.
            ->orderByDesc(
                Bookmark::select('created_at')
                    ->whereColumn('bookmarks.post_id', 'posts.id')
                    ->where('user_id', $user->id)
                    ->where('kind', $kind)
                    ->limit(1)
            )
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('library/index', [
            'posts' => $posts,
            'kind' => $kind,
            'counts' => [
                'saved' => $user->bookmarks()->where('kind', Bookmark::SAVED)->count(),
                'starred' => $user->bookmarks()->where('kind', Bookmark::STARRED)->count(),
            ],
        ]);
    }

    public function toggle(Request $request, Post $post): RedirectResponse
    {
        // You can only shelve what you are allowed to read.
        $this->authorize('view', $post);

        $data = $request->validate([
            'kind' => ['required', Rule::in([Bookmark::SAVED, Bookmark::STARRED])],
        ]);

        $existing = Bookmark::where([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'kind' => $data['kind'],
        ])->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', $data['kind'] === Bookmark::STARRED ? 'Unstarred.' : 'Removed from your library.');
        }

        // firstOrCreate so a double submit is idempotent rather than a
        // unique-constraint 500.
        Bookmark::firstOrCreate([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'kind' => $data['kind'],
        ]);

        return back()->with('success', $data['kind'] === Bookmark::STARRED ? 'Starred.' : 'Saved to your library.');
    }
}
