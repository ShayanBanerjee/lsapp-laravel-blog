<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Post;
use App\Support\Ads;
use App\Support\Earnings\SupportConfig;
use App\Support\PostPresenter;
use App\Support\Seo;
use App\Support\UniverseContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public face of a voice, at /@handle.
 *
 * Personas — not accounts — are what readers follow, so this is the page a
 * shared link points at and the one search engines index. It renders in the
 * persona's own theme for the same reason a post does: the writing carries its
 * world with it.
 */
class ProfileViewController extends Controller
{
    public function show(Request $request, Persona $persona): Response
    {
        $persona->load(['universe', 'customTheme', 'user:id,name']);

        $viewer = $request->user();

        $posts = $persona->publishedPosts()
            ->with(['persona.universe', 'universe', 'categories'])
            ->withCount('highlights')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Post $post) => PostPresenter::card($post));

        $seo = Seo::forPersona($persona);

        return Inertia::render('profiles/show', [
            'persona' => [
                'handle' => $persona->handle,
                'display_name' => $persona->display_name,
                'bio' => $persona->bio,
                'avatar_path' => $persona->avatar_path,
                'universe' => $persona->universe->preview(),
                'joined_human' => $persona->created_at?->format('F Y'),
                // Marks received, not views — the platform's honest measure of
                // whether writing landed.
                'marks' => (int) $persona->posts()->published()->withCount('highlights')->get()->sum('highlights_count'),
                'published_count' => $persona->posts()->published()->count(),
                'followers' => $persona->followers()->count(),
                'is_following' => $viewer
                    ? $persona->followers()->where('user_id', $viewer->id)->exists()
                    : false,
                'is_mine' => $viewer?->id === $persona->user_id,
            ],
            'posts' => $posts,
            'universe' => UniverseContext::serialize($persona->universe, $viewer, $persona),
            'support' => SupportConfig::for($viewer, $persona, $persona->user_id),
            'ads' => Ads::forRequest($request, 'feed'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
