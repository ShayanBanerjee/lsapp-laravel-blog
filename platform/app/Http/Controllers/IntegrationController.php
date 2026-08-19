<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Integration as Connection;
use App\Models\Post;
use App\Support\Academic\Manuscript;
use App\Support\Integrations\IntegrationRegistry;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\ReceivesHighlights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connecting and using third-party services.
 *
 * Two rules run through all of it:
 *
 * - **Credentials are never sent back to the browser.** The model hides them,
 *   and the connection list carries only which services are connected and when
 *   they were last used. Re-connecting means re-entering the credential, which
 *   is a small cost for never having a token in a page's props.
 * - **Nothing is stored unverified.** A connection is written only after the
 *   service has confirmed the credential works, so a failure surfaces here
 *   rather than three weeks later against a finished piece.
 */
class IntegrationController extends Controller
{
    public function index(Request $request, IntegrationRegistry $registry): Response
    {
        return Inertia::render('settings/integrations', [
            'catalogue' => $registry->catalogue(),
            'connections' => $request->user()->integrations()
                ->get()
                ->map(fn (Connection $connection) => [
                    'provider' => $connection->provider,
                    'connected_human' => $connection->verified_at?->format('j M Y'),
                    'last_used_human' => $connection->last_used_at?->diffForHumans(),
                ])
                ->keyBy('provider'),
            // Obsidian is here rather than in the catalogue because it is not
            // an API integration and pretending otherwise would be a lie.
            'obsidian' => [
                'label' => 'Obsidian',
                'blurb' => 'Obsidian has no cloud API. Export any piece as Markdown — it carries YAML frontmatter a vault reads directly.',
            ],
        ]);
    }

    public function store(Request $request, IntegrationRegistry $registry, string $provider): RedirectResponse
    {
        $integration = $registry->find($provider);

        abort_if($integration === null, 404);

        $expected = collect($integration->credentialFields())->pluck('key');

        $data = $request->validate(
            $expected->mapWithKeys(fn (string $key) => [
                'credentials.'.$key => ['nullable', 'string', 'max:500'],
            ])->all()
        );

        // Only the keys this provider declared — a crafted payload cannot store
        // extra fields alongside the real ones.
        $credentials = $expected
            ->mapWithKeys(fn (string $key) => [$key => (string) ($data['credentials'][$key] ?? '')])
            ->all();

        $result = $integration->verify($credentials);

        if (! $result->ok) {
            return back()->withErrors(['credentials' => $result->message]);
        }

        $request->user()->integrations()->updateOrCreate(
            ['provider' => $provider],
            ['credentials' => $credentials, 'verified_at' => now()],
        );

        return back()->with('success', $result->message);
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $request->user()->integrations()->where('provider', $provider)->delete();

        return back()->with('success', 'Disconnected.');
    }

    /** Send one piece to a connected destination. */
    public function publish(Request $request, IntegrationRegistry $registry, Post $post, string $provider): RedirectResponse
    {
        $this->authorize('update', $post);

        $integration = $registry->find($provider);

        abort_unless($integration instanceof PublishesPosts, 404);

        $connection = $this->connection($request, $provider);

        $post->loadMissing(['persona', 'user', 'categories']);

        try {
            $result = $integration->publish($connection->credentials, Manuscript::fromPost($post));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $integration->label().' could not be reached.');
        }

        $connection->forceFill(['last_used_at' => now()])->save();

        return $result->ok
            ? back()->with('success', $result->message.($result->url ? ' '.$result->url : ''))
            : back()->with('error', $result->message);
    }

    /**
     * Send this reader's own marks to a highlight service.
     *
     * Scoped to the requesting user's highlights only. Marks are visible in
     * aggregate on a page, but "everything this person marked" is a reading
     * history, and it belongs to them alone.
     */
    public function sendHighlights(Request $request, IntegrationRegistry $registry, string $provider): RedirectResponse
    {
        $integration = $registry->find($provider);

        abort_unless($integration instanceof ReceivesHighlights, 404);

        $connection = $this->connection($request, $provider);

        $highlights = Highlight::query()
            ->where('highlights.user_id', $request->user()->id)
            ->with(['post:id,slug,title,persona_id', 'post.persona:id,display_name'])
            ->latest('highlights.created_at')
            ->limit(1000)
            ->get()
            ->map(fn (Highlight $highlight) => [
                'quote' => $highlight->quote,
                'title' => $highlight->post?->title ?? 'Untitled',
                'author' => $highlight->post?->persona?->display_name ?? config('app.name'),
                'url' => $highlight->post ? route('posts.show', $highlight->post) : config('app.url'),
                'marked_at' => $highlight->created_at?->toIso8601String(),
            ]);

        try {
            $result = $integration->sendHighlights($connection->credentials, $highlights);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $integration->label().' could not be reached.');
        }

        $connection->forceFill(['last_used_at' => now()])->save();

        return $result->ok
            ? back()->with('success', $result->message)
            : back()->with('error', $result->message);
    }

    private function connection(Request $request, string $provider): Connection
    {
        $connection = $request->user()->integrations()->where('provider', $provider)->first();

        abort_if($connection === null, 403, 'That service is not connected.');

        return $connection;
    }
}
