<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Prompt;
use App\Models\Response as ModelsResponse;
use App\Support\Ads;
use App\Support\HtmlSanitizer;
use App\Support\PostPresenter;
use App\Support\Seo;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /** Canonical cover-image location. One spelling, used everywhere. */
    private const COVER_DIR = 'cover-images';

    public function index(Request $request): Response
    {
        $query = Post::published()->with(['persona.universe', 'universe', 'categories'])->withCount('highlights');

        if ($slug = $request->query('universe')) {
            $query->whereHas('universe', fn ($q) => $q->where('slug', $slug));
        }

        if ($term = $request->query('q')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%"));
        }

        return Inertia::render('posts/index', [
            'posts' => $query->latest('published_at')->paginate(9)->withQueryString()
                ->through(fn (Post $post) => PostPresenter::card($post)),
            'filters' => [
                'universe' => $request->query('universe'),
                'q' => $request->query('q'),
            ],
            'ads' => Ads::forRequest($request, 'feed'),
        ]);
    }

    public function show(Request $request, Post $post): Response
    {
        $this->authorize('view', $post);

        $user = $request->user();
        $post->load(['persona.universe', 'universe', 'user', 'categories'])->loadCount('highlights');

        $related = Post::published()
            ->where('universe_id', $post->universe_id)
            ->whereKeyNot($post->getKey())
            ->with(['persona.universe', 'universe'])
            ->withCount('highlights')
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Post $related) => PostPresenter::card($related));

        $seo = Seo::forPost($post);

        $responses = $post->responses()
            ->with(['persona:id,handle,display_name', 'user:id,name', 'highlight:id,quote'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (ModelsResponse $response) => [
                'id' => $response->id,
                'body' => $response->body,
                'author' => $response->persona?->display_name ?? $response->user?->name ?? 'A reader',
                'handle' => $response->persona?->handle,
                'quote' => $response->highlight?->quote,
                'highlight_id' => $response->highlight_id,
                'created_human' => $response->created_at?->diffForHumans(),
                'can_delete' => $user?->can('delete', $response) ?? false,
            ]);

        return Inertia::render('posts/show', [
            'post' => [
                ...PostPresenter::card($post),
                'body' => $post->body,
                'can' => [
                    'update' => $user?->can('update', $post) ?? false,
                    'delete' => $user?->can('delete', $post) ?? false,
                ],
            ],
            // A post is always read in its own world.
            'universe' => UniverseContext::serialize($post->universe, $user),
            'related' => $related,

            // Passages marked by anyone, collapsed to one row per passage with
            // a count — this is what gets underlined in the reading view.
            'passages' => $post->markedPassages()->map(fn ($passage) => [
                'block_index' => (int) $passage->block_index,
                'start_offset' => (int) $passage->start_offset,
                'end_offset' => (int) $passage->end_offset,
                'quote' => $passage->quote,
                'marks' => (int) $passage->marks,
            ]),

            // Just this reader's own marks, so the UI can show which are theirs
            // and let them be removed.
            'myHighlights' => $user
                ? $post->highlights()->where('user_id', $user->id)->get()
                    ->map(fn ($highlight) => [
                        'id' => $highlight->id,
                        'block_index' => $highlight->block_index,
                        'start_offset' => $highlight->start_offset,
                        'end_offset' => $highlight->end_offset,
                    ])
                : [],

            'responses' => $responses,

            // The reader's own shelf state for this piece.
            'bookmarks' => $user
                ? [
                    'saved' => $post->bookmarks()->where('user_id', $user->id)->where('kind', 'saved')->exists(),
                    'starred' => $post->bookmarks()->where('user_id', $user->id)->where('kind', 'starred')->exists(),
                ]
                : ['saved' => false, 'starred' => false],

            'ads' => Ads::forRequest($request, 'post'),
            'seo' => $seo,
        ])
            // Also handed to the root Blade view so the tags are in the initial
            // HTML — social scrapers do not execute JavaScript.
            ->withViewData(['seo' => $seo]);
    }

    public function create(Request $request): Response
    {
        // A prompt in the voice of the universe you are about to write in. The
        // blank page — not lack of ideas — is what stops most pieces starting.
        $universe = UniverseContext::activeUniverse($request);

        return Inertia::render('posts/create', [
            'personas' => $this->writablePersonas($request),
            'prompts' => $universe
                ? Prompt::where('universe_id', $universe->id)->inRandomOrder()->limit(3)->pluck('body')
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);
        $persona = $request->user()->personas()->with('universe')->findOrFail($data['persona_id']);

        $this->authorize('use', $persona->universe);

        $body = HtmlSanitizer::clean($data['body']);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => $this->uniqueSlug($data['title']),
            'title' => $data['title'],
            'excerpt' => HtmlSanitizer::excerpt($body),
            'body' => $body,
            'cover_image' => $this->storeCover($request),
            'status' => $data['status'],
            'reading_time' => Post::estimateReadingTime($body),
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        return to_route('posts.show', $post)->with('success', 'Your piece is live.');
    }

    public function edit(Request $request, Post $post): Response
    {
        $this->authorize('update', $post);

        return Inertia::render('posts/edit', [
            'post' => [
                ...PostPresenter::card($post->load(['persona.universe', 'universe'])),
                'body' => $post->body,
                'persona_id' => $post->persona_id,
            ],
            'personas' => $this->writablePersonas($request),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $this->validatePost($request);
        $persona = $request->user()->personas()->with('universe')->findOrFail($data['persona_id']);

        $this->authorize('use', $persona->universe);

        $body = HtmlSanitizer::clean($data['body']);
        $cover = $this->storeCover($request);

        // Only ever delete our own uploads — bundled seed imagery is shared
        // between posts and is not ours to remove.
        if ($cover && $post->hasUploadedCover()) {
            Storage::disk('public')->delete($post->cover_image);
        }

        $post->update([
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'title' => $data['title'],
            'excerpt' => HtmlSanitizer::excerpt($body),
            'body' => $body,
            'cover_image' => $cover ?? $post->cover_image,
            'status' => $data['status'],
            'reading_time' => Post::estimateReadingTime($body),
            'published_at' => $data['status'] === 'published'
                ? ($post->published_at ?? now())
                : null,
        ]);

        return to_route('posts.show', $post)->with('success', 'Changes saved.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        if ($post->hasUploadedCover()) {
            Storage::disk('public')->delete($post->cover_image);
        }

        $post->delete();

        return to_route('posts.index')->with('success', 'Post removed.');
    }

    /** @return array<string, mixed> */
    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'persona_id' => ['required', 'integer'],
            'status' => ['required', 'in:draft,published'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    /**
     * Stores the upload and returns the disk-relative path, or null when no
     * file was sent. The stored value is the single source of truth — views
     * resolve it through Storage::url rather than rebuilding the path.
     */
    private function storeCover(Request $request): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        return $request->file('cover_image')->store(self::COVER_DIR, 'public');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (Post::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** Personas whose universe the user is actually entitled to write in. */
    private function writablePersonas(Request $request): Collection
    {
        return $request->user()->personas()->with('universe')->get()
            ->filter(fn ($persona) => $request->user()->canAccessUniverse($persona->universe))
            ->map(fn ($persona) => [
                'id' => $persona->id,
                'handle' => $persona->handle,
                'display_name' => $persona->display_name,
                'universe' => $persona->universe->preview(),
            ])
            ->values();
    }
}
