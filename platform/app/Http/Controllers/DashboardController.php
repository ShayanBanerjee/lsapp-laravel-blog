<?php

namespace App\Http\Controllers;

use App\Models\CopyFlag;
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

            /*
             * Near-duplicate warnings on this writer's own pieces.
             *
             * Shown here and nowhere else. A flag is a probabilistic signal, so
             * it belongs on the desk of the person who can act on it quietly —
             * not in a public report, and not as a notification to the author
             * of the piece it resembles.
             */
            'copyFlags' => CopyFlag::open()
                ->whereIn('post_id', $user->posts()->select('id'))
                ->with(['post:id,slug,title', 'matchedPost:id,slug,title,persona_id', 'matchedPost.persona:id,handle'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (CopyFlag $flag) => [
                    'id' => $flag->id,
                    'kind' => $flag->kind,
                    'similarity' => $flag->similarity,
                    'post' => ['slug' => $flag->post?->slug, 'title' => $flag->post?->title],
                    'matched' => [
                        'slug' => $flag->matchedPost?->slug,
                        'title' => $flag->matchedPost?->title,
                        'handle' => $flag->matchedPost?->persona?->handle,
                    ],
                ]),

            'stats' => [
                'published' => $posts->where('status', 'published')->count(),
                'drafts' => $posts->where('status', 'draft')->count(),
                'personas' => $user->personas()->count(),
                'following' => $user->follows()->count(),
            ],
        ]);
    }
}
