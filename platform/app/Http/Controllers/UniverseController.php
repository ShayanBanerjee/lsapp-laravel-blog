<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Universe;
use App\Support\PostPresenter;
use App\Support\UniverseContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UniverseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $universes = Universe::withCount([
            'posts as published_posts_count' => fn ($query) => $query->where('status', 'published'),
            'personas',
        ])->orderBy('sort_order')->get();

        return Inertia::render('universes/index', [
            'universes' => $universes->map(fn (Universe $universe) => [
                ...$universe->preview(),
                'locked' => $universe->is_premium && ! ($user?->canAccessUniverse($universe) ?? false),
                'posts_count' => $universe->published_posts_count,
                'writers_count' => $universe->personas_count,
            ]),
        ]);
    }

    public function show(Request $request, Universe $universe): Response
    {
        $posts = Post::published()
            ->where('universe_id', $universe->id)
            ->with(['persona', 'universe'])
            ->withCount('highlights')
            ->latest('published_at')
            ->paginate(9)
            ->through(fn (Post $post) => PostPresenter::card($post));

        $writers = $universe->personas()->with('universe')->limit(8)->get()
            ->map(fn ($persona) => [
                'handle' => $persona->handle,
                'display_name' => $persona->display_name,
                'bio' => $persona->bio,
            ]);

        return Inertia::render('universes/show', [
            'universe' => UniverseContext::serialize($universe, $request->user()),
            'posts' => $posts,
            'writers' => $writers,
            'isFollowing' => $request->user()
                ? $universe->followers()->where('user_id', $request->user()->id)->exists()
                : false,
        ]);
    }
}
