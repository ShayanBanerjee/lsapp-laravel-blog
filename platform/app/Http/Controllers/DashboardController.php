<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $posts = $user->posts()->with(['persona', 'universe'])->latest()->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_url' => $post->coverUrl(),
                'status' => $post->status,
                'reading_time' => $post->reading_time,
                'published_human' => $post->published_at?->format('j M Y'),
                'updated_human' => $post->updated_at?->diffForHumans(),
                'persona' => $post->persona ? [
                    'handle' => $post->persona->handle,
                    'display_name' => $post->persona->display_name,
                ] : null,
                'universe' => $post->universe?->preview(),
            ]);

        return Inertia::render('dashboard', [
            'posts' => $posts,
            'stats' => [
                'published' => $posts->where('status', 'published')->count(),
                'drafts' => $posts->where('status', 'draft')->count(),
                'personas' => $user->personas()->count(),
                'following' => $user->follows()->count(),
            ],
        ]);
    }
}
