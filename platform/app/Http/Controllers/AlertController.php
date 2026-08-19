<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $alerts = $request->user()->alerts()
            ->with(['actorPersona:id,handle,display_name', 'post:id,slug,title'])
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'count' => $alert->count,
                'preview' => $alert->preview,
                'unread' => $alert->read_at === null,
                'actor' => $alert->actorPersona ? [
                    'handle' => $alert->actorPersona->handle,
                    'display_name' => $alert->actorPersona->display_name,
                ] : null,
                'post' => $alert->post ? ['slug' => $alert->post->slug, 'title' => $alert->post->title] : null,
                'when_human' => $alert->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('alerts', ['alerts' => $alerts]);
    }

    /**
     * Clear the badge.
     *
     * Deliberately explicit rather than "read on open": the list stays marked
     * unread while you look at it, so opening the page on a phone at a bus stop
     * does not lose the thing you meant to come back to.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->alerts()->unread()->update(['read_at' => now()]);

        return back()->with('success', 'Marked as read.');
    }
}
