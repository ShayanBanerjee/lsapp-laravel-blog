<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Response;
use App\Support\HtmlSanitizer;
use App\Support\Moderation\Moderator;
use App\Support\Notifier;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResponseController extends Controller
{
    public function store(Request $request, Post $post, Moderator $moderator): RedirectResponse
    {
        $this->authorize('view', $post);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
            'highlight_id' => [
                'nullable',
                // Scope the existence check to this post, or a crafted id could
                // anchor a response to a passage in someone else's piece.
                Rule::exists('highlights', 'id')->where('post_id', $post->id),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('responses', 'id')->where('post_id', $post->id),
            ],
        ]);

        $body = HtmlSanitizer::plain($data['body']);
        $verdict = $moderator->check($body);

        /*
         * Blocking is reserved for slurs and directed sexual harassment. Every
         * other kind of hostility — including rude, angry, and profane
         * disagreement — goes up. A writing platform that filters strong
         * opinion is not protecting anyone, it is just quieter.
         */
        if ($verdict->isBlocked()) {
            return back()->withErrors([
                'body' => $verdict->category === 'sexual_harassment'
                    ? 'That reads as sexual harassment directed at a person. Say the substantive part instead.'
                    : 'That contains a slur. Disagree as strongly as you like — not like that.',
            ])->withInput();
        }

        Response::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'persona_id' => UniverseContext::activePersona($request)?->id,
            'highlight_id' => $data['highlight_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            // Stored and rendered as plain text — responses are not rich text,
            // so there is no reason to accept markup at all.
            'body' => $body,
            // Uncertain cases publish immediately and are queued for a human.
            'flagged_category' => $verdict->needsReview() ? $verdict->category : null,
            'flagged_at' => $verdict->needsReview() ? now() : null,
        ]);

        Notifier::answered($post, $request->user(), UniverseContext::activePersona($request)?->id, $body);

        return back(fallback: route('posts.show', $post))->with('success', 'Response posted.');
    }

    public function destroy(Request $request, Response $response): RedirectResponse
    {
        $this->authorize('delete', $response);

        $post = $response->post;
        $response->delete();

        return back(fallback: route('posts.show', $post))->with('success', 'Response removed.');
    }
}
