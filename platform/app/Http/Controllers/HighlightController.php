<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Post;
use App\Support\Alerts;
use App\Support\HtmlSanitizer;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Marking a passage. The core interaction of the platform.
 */
class HighlightController extends Controller
{
    public function store(Request $request, Post $readable): RedirectResponse
    {
        // You can only mark what you are allowed to read.
        $this->authorize('view', $readable);

        $data = $request->validate([
            'block_index' => ['required', 'integer', 'min:0', 'max:2000'],
            'start_offset' => ['required', 'integer', 'min:0', 'max:65000'],
            'end_offset' => ['required', 'integer', 'min:1', 'max:65000', 'gt:start_offset'],
            'quote' => ['required', 'string', 'max:2000'],
        ]);

        // The quote is client-supplied and is displayed back to other readers,
        // so it is reduced to plain text before storage.
        $data['quote'] = HtmlSanitizer::plain($data['quote'], 1000);

        // firstOrCreate rather than create: the unique index already prevents
        // duplicates, and racing double-clicks should be idempotent rather than
        // a 500.
        $highlight = Highlight::firstOrCreate(
            [
                'post_id' => $readable->id,
                'user_id' => $request->user()->id,
                'block_index' => $data['block_index'],
                'start_offset' => $data['start_offset'],
                'end_offset' => $data['end_offset'],
            ],
            ['quote' => $data['quote']],
        );

        // Only on the first mark of this passage — re-posting an existing mark
        // is idempotent above and must be idempotent here too, or a repeated
        // request would inflate the author's count.
        if ($highlight->wasRecentlyCreated) {
            Alerts::markedPassage(
                $readable,
                $request->user(),
                UniverseContext::activePersona($request),
                $data['quote'],
            );
        }

        return back(fallback: $readable->readUrl())->with('success', 'Passage marked.');
    }

    public function destroy(Request $request, Highlight $highlight): RedirectResponse
    {
        $this->authorize('delete', $highlight);

        $post = $highlight->post;
        $highlight->delete();

        return back(fallback: $post->readUrl())->with('success', 'Mark removed.');
    }
}
