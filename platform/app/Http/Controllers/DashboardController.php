<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Post;
use App\Support\PostPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $posts = $user->posts()->with(['persona', 'universe'])
            ->withCount('highlights')
            ->latest()->get()
            ->map(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('dashboard', [
            'posts' => $posts,
            // The answer to "which sentence worked" — the single most useful
            // thing this platform can hand a writer, and the reason marks exist.
            'markedPassages' => Highlight::query()
                ->selectRaw('posts.slug, posts.title, highlights.quote, COUNT(*) as marks')
                ->join('posts', 'posts.id', '=', 'highlights.post_id')
                ->where('posts.user_id', $user->id)
                ->groupBy('posts.slug', 'posts.title', 'highlights.block_index', 'highlights.start_offset', 'highlights.end_offset', 'highlights.quote')
                ->orderByDesc('marks')
                ->limit(6)
                ->get(),

            'unreadLetters' => $user->lettersReceived()->whereNull('read_at')->count(),

            'stats' => [
                'published' => $posts->where('status', 'published')->count(),
                'drafts' => $posts->where('status', 'draft')->count(),
                'personas' => $user->personas()->count(),
                'following' => $user->follows()->count(),
            ],
        ]);
    }
}
