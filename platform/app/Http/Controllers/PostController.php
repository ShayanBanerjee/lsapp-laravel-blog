<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\HtmlSanitizer;
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
        $query = Post::published()->with(['persona.universe', 'universe']);

        if ($slug = $request->query('universe')) {
            $query->whereHas('universe', fn ($q) => $q->where('slug', $slug));
        }

        if ($term = $request->query('q')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%"));
        }

        return Inertia::render('posts/index', [
            'posts' => $query->latest('published_at')->paginate(9)->withQueryString()
                ->through(fn (Post $post) => $this->card($post)),
            'filters' => [
                'universe' => $request->query('universe'),
                'q' => $request->query('q'),
            ],
        ]);
    }

    public function show(Request $request, Post $post): Response
    {
        $this->authorize('view', $post);

        $post->load(['persona.universe', 'universe', 'user']);

        $related = Post::published()
            ->where('universe_id', $post->universe_id)
            ->whereKeyNot($post->getKey())
            ->with(['persona.universe', 'universe'])
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Post $related) => $this->card($related));

        return Inertia::render('posts/show', [
            'post' => [
                ...$this->card($post),
                'body' => $post->body,
                'can' => [
                    'update' => $request->user()?->can('update', $post) ?? false,
                    'delete' => $request->user()?->can('delete', $post) ?? false,
                ],
            ],
            // A post is always read in its own world.
            'universe' => UniverseContext::serialize($post->universe, $request->user()),
            'related' => $related,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('posts/create', [
            'personas' => $this->writablePersonas($request),
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
                ...$this->card($post->load(['persona.universe', 'universe'])),
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

    /** @return array<string, mixed> */
    private function card(Post $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'cover_url' => $post->coverUrl(),
            'status' => $post->status,
            'reading_time' => $post->reading_time,
            'published_at' => $post->published_at?->toIso8601String(),
            'published_human' => $post->published_at?->format('j M Y'),
            'persona' => $post->persona ? [
                'handle' => $post->persona->handle,
                'display_name' => $post->persona->display_name,
                'avatar_path' => $post->persona->avatar_path,
            ] : null,
            'universe' => $post->universe?->preview(),
        ];
    }
}
