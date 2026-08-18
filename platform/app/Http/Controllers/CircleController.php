<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Post;
use App\Support\PostPresenter;
use App\Support\Seo;
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

        return Inertia::render('circles/index', [
            'circles' => $circles->map(fn (Circle $circle) => [
                ...$this->card($circle),
                'joined' => in_array($circle->id, $joined, true),
            ]),
        ])->withViewData(['seo' => Seo::forPage(
            'Circles',
            'Community organised by subject rather than follower count — each circle with its own reading list and prompts.',
            route('circles.index'),
        )]);
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
        ])->withViewData(['seo' => Seo::forPage(
            $circle->name,
            $circle->description ?? '',
            route('circles.show', $circle),
        )]);
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
        ];
    }
}
