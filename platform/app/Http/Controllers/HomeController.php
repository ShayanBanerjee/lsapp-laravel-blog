<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Universe;
use App\Support\PostPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $featured = Post::published()->with(['persona', 'universe'])
            ->withCount('highlights')
            ->latest('published_at')->limit(6)->get()
            ->map(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('welcome', [
            'featured' => $featured,
            'universes' => Universe::withCount([
                'posts as published_posts_count' => fn ($query) => $query->where('status', 'published'),
            ])->orderBy('sort_order')->get()
                ->map(fn (Universe $universe) => [
                    ...$universe->preview(),
                    'locked' => $universe->is_premium && ! ($user?->canAccessUniverse($universe) ?? false),
                    'posts_count' => $universe->published_posts_count,
                ]),
            'stats' => [
                'posts' => Post::published()->count(),
                'universes' => Universe::count(),
            ],
        ]);
    }
}
