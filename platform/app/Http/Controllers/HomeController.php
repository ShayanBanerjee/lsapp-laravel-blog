<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Universe;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $featured = Post::published()->with(['persona', 'universe'])
            ->latest('published_at')->limit(6)->get()
            ->map(fn (Post $post) => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_url' => $post->coverUrl(),
                'reading_time' => $post->reading_time,
                'published_human' => $post->published_at?->format('j M Y'),
                'persona' => $post->persona ? [
                    'handle' => $post->persona->handle,
                    'display_name' => $post->persona->display_name,
                ] : null,
                'universe' => $post->universe?->preview(),
            ]);

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
