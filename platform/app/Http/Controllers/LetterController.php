<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Post;
use App\Support\Alerts;
use App\Support\HtmlSanitizer;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Private notes from readers to writers.
 *
 * The whole value here is that there is no audience, so nothing about this
 * feature should ever become public or countable in public.
 */
class LetterController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $letters = $user->lettersReceived()
            ->with(['sender:id,name', 'post:id,slug,title'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Letter $letter) => [
                'id' => $letter->id,
                'body' => $letter->body,
                'from' => $letter->sender?->name ?? 'A reader',
                'post' => $letter->post ? ['slug' => $letter->post->slug, 'title' => $letter->post->title] : null,
                'received_human' => $letter->created_at?->diffForHumans(),
                'unread' => $letter->read_at === null,
            ]);

        // Opening the page is reading them; mark in one statement rather than
        // one write per row.
        $user->lettersReceived()->whereNull('read_at')->update(['read_at' => now()]);

        return Inertia::render('letters/index', ['letters' => $letters]);
    }

    public function store(Request $request, Post $readable): RedirectResponse
    {
        $this->authorize('view', $readable);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:4', 'max:2000'],
        ]);

        if ($readable->user_id === $request->user()->id) {
            return back()->with('error', 'That one is already yours.');
        }

        $letter = Letter::create([
            'post_id' => $readable->id,
            'from_user_id' => $request->user()->id,
            'to_user_id' => $readable->user_id,
            'body' => HtmlSanitizer::plain($data['body'], 2000),
        ]);

        Alerts::letterReceived(
            $readable->user_id,
            $request->user(),
            UniverseContext::activePersona($request),
            $letter->body,
        );

        return back()->with('success', 'Your letter is on its way.');
    }
}
