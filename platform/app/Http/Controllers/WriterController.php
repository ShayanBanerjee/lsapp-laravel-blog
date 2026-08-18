<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\WriterNotification;
use App\Support\PostPresenter;
use App\Support\Seo;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The writer social layer: public profiles, a following feed, notifications.
 *
 * Built against STRATEGY.md's explicit exclusions, which matter more here than
 * anywhere else in the app:
 *
 *   - **No public follower counts.** A profile shows what someone wrote and
 *     which of their sentences landed. It never shows how many people follow
 *     them, because that turns writing into standing.
 *   - **No infinite feed.** The following feed paginates and ends. The mechanic
 *     readers came here to escape is not reintroduced because it is easy.
 *   - **Notifications carry the passage**, never a tally. "Four people liked
 *     this" is silence with a number attached.
 */
class WriterController extends Controller
{
    /** A writer's public page, addressed by persona handle. */
    public function show(Request $request, Persona $persona): Response
    {
        $persona->load('universe')->loadCount(['posts as published_posts_count' => fn ($query) => $query->published()]);

        $posts = $persona->posts()
            ->published()
            ->with(['persona.universe', 'universe', 'categories'])
            ->withCount('highlights')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Post $post) => PostPresenter::card($post));

        // The most-marked passages across everything they have written — the
        // honest version of a "top posts" list, since marking costs effort and
        // a view does not.
        $marked = Post::query()
            ->join('highlights', 'highlights.post_id', '=', 'posts.id')
            ->where('posts.persona_id', $persona->id)
            ->where('posts.status', 'published')
            ->groupBy('posts.slug', 'posts.title', 'highlights.quote')
            ->orderByDesc('marks')
            ->limit(5)
            ->get(['posts.slug', 'posts.title', 'highlights.quote', DB::raw('COUNT(*) as marks')]);

        return Inertia::render('writers/show', [
            'writer' => [
                'handle' => $persona->handle,
                'display_name' => $persona->display_name,
                'bio' => $persona->bio,
                'avatar_path' => $persona->avatar_path,
                'universe' => $persona->universe->preview(),
                'published_posts_count' => $persona->published_posts_count,
            ],
            'posts' => $posts,
            'marked' => $marked,
            'isFollowing' => $request->user()
                ? $persona->followers()->where('user_id', $request->user()->id)->exists()
                : false,
            'universe' => UniverseContext::serialize($persona->universe, $request->user()),
        ])->withViewData(['seo' => Seo::forPage(
            $persona->display_name,
            $persona->bio ?? "Writing by {$persona->display_name}.",
            route('writers.show', $persona),
        )]);
    }

    /**
     * Pieces from the personas and universes this reader follows.
     *
     * Chronological and finite. No ranking, because a ranked feed is a claim
     * about what you should read next, and the entire argument for text is that
     * the reader sets the pace.
     */
    public function following(Request $request): Response
    {
        $user = $request->user();

        $personaIds = Follow::where('user_id', $user->id)
            ->where('followable_type', Persona::class)
            ->pluck('followable_id');

        $universeIds = Follow::where('user_id', $user->id)
            ->where('followable_type', Universe::class)
            ->pluck('followable_id');

        $posts = Post::published()
            ->where(function ($query) use ($personaIds, $universeIds) {
                $query->whereIn('persona_id', $personaIds)
                    ->orWhereIn('universe_id', $universeIds);
            })
            ->with(['persona.universe', 'universe', 'categories'])
            ->withCount('highlights')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('writers/following', [
            'posts' => $posts,
            'following' => [
                'writers' => $personaIds->count(),
                'universes' => $universeIds->count(),
            ],
        ]);
    }

    /** Which sentences landed, and who answered them. */
    public function notifications(Request $request): Response
    {
        $user = $request->user();

        $notifications = WriterNotification::where('user_id', $user->id)
            ->with(['post:id,slug,title', 'actor:id,handle,display_name'])
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (WriterNotification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'quote' => $notification->quote,
                'read' => $notification->read_at !== null,
                'when' => $notification->created_at?->diffForHumans(),
                'post' => $notification->post ? [
                    'slug' => $notification->post->slug,
                    'title' => $notification->post->title,
                ] : null,
                'actor' => $notification->actor ? [
                    'handle' => $notification->actor->handle,
                    'display_name' => $notification->actor->display_name,
                ] : null,
            ]);

        // Opening the page is the acknowledgement; there is no separate button.
        WriterNotification::where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);

        return Inertia::render('writers/notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        WriterNotification::where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Cleared.');
    }
}
