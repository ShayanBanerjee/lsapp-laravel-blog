<?php

namespace App\Support\Integrations;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push a reader's marks to Readwise.
 *
 * Readwise is first among the integrations because the mapping is exact rather
 * than approximate: their unit is a highlight with a source, and so is ours.
 * Nothing has to be flattened or invented to move between the two, which is not
 * true of exporting marks into, say, a Notion page.
 *
 * The token is the reader's own (Readwise issues one per account at
 * readwise.io/access_token); there is no OAuth app to register and nothing here
 * works — or is even reachable — until a reader supplies one.
 */
class Readwise
{
    private const ENDPOINT = 'https://readwise.io/api/v2/highlights/';

    /** Readwise rejects batches over 100. */
    private const BATCH = 100;

    public static function isConnected(User $user): bool
    {
        return Integration::where('user_id', $user->id)->where('service', 'readwise')->whereNotNull('token')->exists();
    }

    /** Verify a token before storing it, so a typo fails at the point of entry. */
    public static function verify(string $token): bool
    {
        return Http::withHeaders(['Authorization' => 'Token '.$token])
            ->timeout(10)
            ->get('https://readwise.io/api/v2/auth/')
            ->successful();
    }

    /**
     * Send every mark this reader has made.
     *
     * Returns the number of highlights accepted, or null when the push failed —
     * the caller surfaces that as a flash message rather than a 500, because a
     * third party being down is not our error.
     */
    public static function push(User $user): ?int
    {
        $integration = Integration::where('user_id', $user->id)->where('service', 'readwise')->first();

        if (! $integration?->token) {
            return null;
        }

        $highlights = $user->highlights()
            ->with(['post.persona'])
            ->get()
            ->filter(fn ($highlight) => $highlight->post !== null)
            ->map(fn ($highlight) => [
                'text' => $highlight->quote,
                'title' => $highlight->post->title,
                'author' => $highlight->post->persona?->display_name ?? config('app.name'),
                'source_url' => route('posts.show', $highlight->post),
                'source_type' => 'inkfathom',
                'category' => 'articles',
                'highlighted_at' => $highlight->created_at?->toIso8601String(),
            ])
            ->values();

        if ($highlights->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($highlights->chunk(self::BATCH) as $batch) {
            $response = Http::withHeaders(['Authorization' => 'Token '.$integration->token])
                ->timeout(20)
                ->post(self::ENDPOINT, ['highlights' => $batch->values()->all()]);

            if (! $response->successful()) {
                // Never log the token, and never surface a third party's error
                // body to the reader — it can echo the request back.
                Log::warning('Readwise push failed', ['user_id' => $user->id, 'status' => $response->status()]);

                $integration->update(['last_error' => 'Readwise returned '.$response->status().'.']);

                return null;
            }

            $sent += $batch->count();
        }

        $integration->update(['last_synced_at' => now(), 'last_error' => null]);

        return $sent;
    }
}
