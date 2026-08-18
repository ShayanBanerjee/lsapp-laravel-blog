<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Post;
use App\Support\Integrations\Crossref;
use App\Support\Integrations\ManuscriptExporter;
use App\Support\Integrations\MarkdownExporter;
use App\Support\Integrations\Readwise;
use App\Support\Integrations\Zenodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Connections to outside tools.
 *
 * Two shapes, and the difference is worth keeping straight: export works for
 * everyone right now with no account anywhere, while service connections are
 * inert until the reader supplies their own token. Advertising the second as
 * though it were the first is how a features page starts lying.
 */
class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $connections = Integration::where('user_id', $user->id)
            ->get()
            ->keyBy('service')
            ->map(fn (Integration $integration) => [
                'connected' => filled($integration->token),
                'last_synced_human' => $integration->last_synced_at?->diffForHumans(),
                'last_error' => $integration->last_error,
            ]);

        return Inertia::render('settings/integrations', [
            'connections' => $connections,
            'markCount' => $user->highlights()->count(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service' => ['required', Rule::in(['readwise', 'zenodo'])],
            'token' => ['required', 'string', 'max:255'],
        ]);

        // Readwise exposes a cheap auth endpoint, so a typo fails at the point
        // of entry. Zenodo has no equivalent; the first deposit is the check.
        if ($validated['service'] === 'readwise' && ! Readwise::verify($validated['token'])) {
            return back()->with('error', 'That token was not accepted by Readwise. Check it at readwise.io/access_token.');
        }

        Integration::updateOrCreate(
            ['user_id' => $request->user()->id, 'service' => $validated['service']],
            ['token' => $validated['token'], 'last_error' => null],
        );

        return back()->with('success', 'Readwise connected.');
    }

    public function disconnect(Request $request, string $service): RedirectResponse
    {
        abort_unless(in_array($service, ['readwise', 'zenodo'], true), 404);

        Integration::where('user_id', $request->user()->id)->where('service', $service)->delete();

        return back()->with('success', 'Disconnected.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $sent = Readwise::push($request->user());

        if ($sent === null) {
            return back()->with('error', 'Readwise could not be reached. Nothing was lost — try again shortly.');
        }

        return back()->with('success', $sent === 0 ? 'No marks to send yet.' : "Sent {$sent} marks to Readwise.");
    }

    /**
     * Download a piece as Markdown with YAML frontmatter.
     *
     * Streamed with a filename rather than rendered, because the point is a
     * file that drops straight into a vault.
     */
    public function exportPost(Request $request, Post $post): StreamedResponse
    {
        $this->authorize('view', $post);

        $post->load(['persona', 'universe', 'user', 'categories']);

        return $this->download(
            MarkdownExporter::forPost($post),
            $post->slug.'.md',
        );
    }

    /** A reader's own marks from one piece — the passages, not the article. */
    public function exportHighlights(Request $request, Post $post): StreamedResponse
    {
        $this->authorize('view', $post);

        $highlights = $post->highlights()
            ->where('user_id', $request->user()->id)
            ->orderBy('block_index')
            ->orderBy('start_offset')
            ->get(['quote']);

        return $this->download(
            MarkdownExporter::forHighlights($post, $highlights),
            $post->slug.'-highlights.md',
        );
    }

    /** IEEEtran LaTeX — the format the conference portals ask for. */
    public function exportLatex(Request $request, Post $post): StreamedResponse
    {
        $this->authorize('view', $post);

        $post->load(['persona', 'universe', 'user', 'categories']);

        return $this->download(ManuscriptExporter::toLatex($post), $post->slug.'.tex', 'application/x-tex');
    }

    public function exportDocx(Request $request, Post $post): StreamedResponse
    {
        $this->authorize('view', $post);

        $post->load(['persona', 'user']);

        return $this->download(
            ManuscriptExporter::toDocx($post),
            $post->slug.'.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
    }

    /**
     * Create a Zenodo draft deposit.
     *
     * Stops at the draft deliberately — publishing mints a permanent DOI, and
     * that last irreversible step stays a human decision on Zenodo's own page.
     */
    public function deposit(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $deposit = Zenodo::deposit($request->user(), $post->load(['persona', 'user']));

        if ($deposit === null) {
            return back()->with('error', 'Zenodo could not be reached, or no token is connected.');
        }

        return back()->with('success', 'Draft deposit created on Zenodo. Review and publish it there to mint the DOI: '.$deposit['url']);
    }

    /** Turn a pasted DOI into a formed citation. Needs no credentials. */
    public function citation(Request $request): RedirectResponse
    {
        $validated = $request->validate(['doi' => ['required', 'string', 'max:255']]);

        $work = Crossref::lookup($validated['doi']);

        if ($work === null) {
            return back()->with('error', 'No record found for that DOI.');
        }

        return back()->with('success', trim(sprintf(
            '%s (%s). %s. %s',
            $work['authors'] ?: 'Unknown author',
            $work['year'] ?? 'n.d.',
            $work['title'],
            $work['container'] ?? $work['url'],
        )));
    }

    private function download(string $body, string $filename, string $type = 'text/markdown; charset=UTF-8'): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $body,
            $filename,
            ['Content-Type' => $type],
        );
    }
}
