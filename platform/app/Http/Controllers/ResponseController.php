<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Response;
use App\Support\HtmlSanitizer;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResponseController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
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

        Response::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'persona_id' => UniverseContext::activePersona($request)?->id,
            'highlight_id' => $data['highlight_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            // Stored and rendered as plain text — responses are not rich text,
            // so there is no reason to accept markup at all.
            'body' => HtmlSanitizer::plain($data['body']),
        ]);

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
