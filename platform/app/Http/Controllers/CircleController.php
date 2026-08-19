<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Highlight;
use App\Models\Post;
use App\Support\PostPresenter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CircleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $circles = Circle::with('universe')
            ->withCount(['members', 'posts'])
            ->orderBy('sort_order')
            ->get();

        $joined = $user
            ? $user->circles()->pluck('circles.id')->all()
            : [];

        /*
         * A circle is a room, and the useful question about a room is whether
         * anything is happening in it. Two signals answer that: the most recent
         * piece, and the passage readers stopped on. Both are collected in one
         * grouped query rather than per circle.
         */
        $latest = Post::query()
            ->join('circle_post', 'circle_post.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->whereNull('posts.course_module_id')
            ->orderByDesc('posts.published_at')
            ->get(['circle_post.circle_id', 'posts.slug', 'posts.title', 'posts.published_at'])
            ->unique('circle_id')
            ->keyBy('circle_id');

        $signals = Highlight::query()
            ->join('circle_post', 'circle_post.post_id', '=', 'highlights.post_id')
            ->groupBy('circle_post.circle_id', 'highlights.quote')
            ->selectRaw('circle_post.circle_id, highlights.quote, COUNT(*) as marks')
            ->orderByDesc('marks')
            ->get()
            ->unique('circle_id')
            ->keyBy('circle_id');

        return Inertia::render('circles/index', [
            'circles' => $circles->map(fn (Circle $circle) => [
                ...$this->card($circle),
                'joined' => in_array($circle->id, $joined, true),
                'latest' => $latest->get($circle->id)
                    ? [
                        'slug' => $latest[$circle->id]->slug,
                        'title' => $latest[$circle->id]->title,
                        'when' => $latest[$circle->id]->published_at
                            ? Carbon::parse($latest[$circle->id]->published_at)->diffForHumans()
                            : null,
                    ]
                    : null,
                'signal' => $signals->get($circle->id)
                    ? [
                        'quote' => $signals[$circle->id]->quote,
                        'marks' => (int) $signals[$circle->id]->marks,
                    ]
                    : null,
            ]),
        ]);
    }

    public function show(Request $request, Circle $circle): Response
    {
        $user = $request->user();

        $posts = $circle->posts()
            ->published()
            ->with(['persona', 'universe'])
            ->withCount('highlights')
            ->latest('published_at')
            ->paginate(9)
            ->through(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('circles/show', [
            'circle' => $this->card($circle->loadCount(['members', 'posts'])->load('universe')),
            'posts' => $posts,
            'joined' => $user
                ? $circle->members()->whereKey($user->id)->exists()
                : false,
            'members' => $circle->members()->limit(12)->get()
                ->map(fn ($member) => ['name' => $member->name]),
            // What the room has actually stopped on, for the sidebar.
            'passages' => Highlight::query()
                ->join('circle_post', 'circle_post.post_id', '=', 'highlights.post_id')
                ->join('posts', 'posts.id', '=', 'highlights.post_id')
                ->where('circle_post.circle_id', $circle->id)
                ->groupBy('highlights.quote', 'posts.slug', 'posts.title')
                ->selectRaw('highlights.quote, posts.slug, posts.title, COUNT(*) as marks')
                ->orderByDesc('marks')
                ->limit(4)
                ->get()
                ->map(fn ($row) => [
                    'quote' => $row->quote,
                    'marks' => (int) $row->marks,
                    'slug' => $row->slug,
                    'title' => $row->title,
                ]),
        ]);
    }

    public function toggle(Request $request, Circle $circle): RedirectResponse
    {
        $user = $request->user();

        if ($circle->members()->whereKey($user->id)->exists()) {
            $circle->members()->detach($user->id);

            return back()->with('success', "Left {$circle->name}.");
        }

        // attach() would create a duplicate row on a double submit; the unique
        // index would then throw. syncWithoutDetaching is idempotent.
        $circle->members()->syncWithoutDetaching([$user->id]);

        return back()->with('success', "You're in {$circle->name}.");
    }

    /** @return array<string, mixed> */
    private function card(Circle $circle): array
    {
        return [
            'slug' => $circle->slug,
            'name' => $circle->name,
            'tagline' => $circle->tagline,
            'description' => $circle->description,
            'members_count' => $circle->members_count ?? 0,
            'posts_count' => $circle->posts_count ?? 0,
            'universe' => $circle->universe?->preview(),
            'hero_image' => $circle->universe?->hero_image,
        ];
    }
}
