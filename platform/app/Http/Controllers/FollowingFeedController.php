<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Support\Ads;
use App\Support\PostPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The feed composed from what a reader follows.
 *
 * Follows have always been recorded here but nothing read them; this is that
 * read. Both kinds count — a followed persona and a followed universe — which
 * is why the query is one OR rather than two feeds stitched together: merging
 * in PHP would break pagination the moment either side had more rows than a
 * page.
 */
class FollowingFeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $follows = $user->follows()->get(['followable_id', 'followable_type']);
        $personaIds = $follows->where('followable_type', Persona::class)->pluck('followable_id');
        $universeIds = $follows->where('followable_type', Universe::class)->pluck('followable_id');

        $posts = Post::published()
            ->when(
                $personaIds->isNotEmpty() || $universeIds->isNotEmpty(),
                fn ($query) => $query->where(fn ($q) => $q
                    ->whereIn('persona_id', $personaIds)
                    ->orWhereIn('universe_id', $universeIds)),
                // Following nothing means an empty feed, not everything. A feed
                // that silently falls back to the global list teaches readers
                // that following does nothing.
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with(['persona.universe', 'universe', 'categories'])
            ->withCount('highlights')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('following', [
            'posts' => $posts,
            'following' => [
                'personas' => Persona::whereIn('id', $personaIds)->with('universe')->get()
                    ->map(fn (Persona $persona) => [
                        'handle' => $persona->handle,
                        'display_name' => $persona->display_name,
                        'universe' => $persona->universe->preview(),
                    ]),
                'universes' => Universe::whereIn('id', $universeIds)->orderBy('sort_order')->get()
                    ->map(fn (Universe $universe) => $universe->preview()),
            ],
            'ads' => Ads::forRequest($request, 'feed'),
        ]);
    }
}
