<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Post;
use App\Support\HtmlSanitizer;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Marking a passage. The core interaction of the platform.
 */
class HighlightController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        // You can only mark what you are allowed to read.
        $this->authorize('view', $post);

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
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
                'block_index' => $data['block_index'],
                'start_offset' => $data['start_offset'],
                'end_offset' => $data['end_offset'],
            ],
            ['quote' => $data['quote']],
        );

        // Only on the first mark — re-marking an already-marked passage is not
        // a second piece of news for the writer.
        if ($highlight->wasRecentlyCreated) {
            Notifier::marked($post, $request->user(), $data['quote']);
        }

        return back(fallback: route('posts.show', $post))->with('success', 'Passage marked.');
    }

    public function destroy(Request $request, Highlight $highlight): RedirectResponse
    {
        $this->authorize('delete', $highlight);

        $post = $highlight->post;
        $highlight->delete();

        return back(fallback: route('posts.show', $post))->with('success', 'Mark removed.');
    }
}
