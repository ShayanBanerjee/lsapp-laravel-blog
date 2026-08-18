<?php

namespace App\Support\Integrations;

use App\Models\Integration;
use App\Models\Post;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Deposit a piece with Zenodo, which mints a real DOI.
 *
 * This is the one genuinely working route from "I wrote something" to "it has a
 * citable identifier". Zenodo is CERN-run, its deposit API is public and
 * documented, and the DOI it returns is real — unlike direct submission to a
 * publisher, which has no API at all.
 *
 * Sandbox by default: a DOI is permanent and a test deposit that accidentally
 * went to production could not be taken back.
 */
class Zenodo
{
    public static function baseUrl(): string
    {
        return config('services.zenodo.sandbox', true)
            ? 'https://sandbox.zenodo.org/api'
            : 'https://zenodo.org/api';
    }

    public static function isConnected(User $user): bool
    {
        return Integration::where('user_id', $user->id)->where('service', 'zenodo')->whereNotNull('token')->exists();
    }

    /**
     * Create a *draft* deposit and return its edit URL.
     *
     * Deliberately stops short of publishing. Publishing mints the DOI and is
     * irreversible, so the last step stays a human decision made on Zenodo's
     * own page, where the author can see exactly what they are committing to.
     *
     * @return array{url: string, id: int}|null
     */
    public static function deposit(User $user, Post $post): ?array
    {
        $integration = Integration::where('user_id', $user->id)->where('service', 'zenodo')->first();

        if (! $integration?->token) {
            return null;
        }

        $metadata = [
            'metadata' => [
                'title' => $post->title,
                'upload_type' => 'publication',
                'publication_type' => 'article',
                'description' => $post->excerpt ?? HtmlSanitizer::excerpt($post->body, 900),
                'creators' => [[
                    'name' => $post->persona?->display_name ?? $post->user?->name ?? 'Anonymous',
                ]],
                'publication_date' => $post->published_at?->toDateString(),
                'access_right' => 'open',
                'license' => 'cc-by-4.0',
                'related_identifiers' => [[
                    'identifier' => route('posts.show', $post),
                    'relation' => 'isIdenticalTo',
                    'scheme' => 'url',
                ]],
            ],
        ];

        $response = Http::withToken($integration->token)
            ->timeout(20)
            ->post(self::baseUrl().'/deposit/depositions', $metadata);

        if (! $response->successful()) {
            Log::warning('Zenodo deposit failed', ['user_id' => $user->id, 'status' => $response->status()]);
            $integration->update(['last_error' => 'Zenodo returned '.$response->status().'.']);

            return null;
        }

        $integration->update(['last_synced_at' => now(), 'last_error' => null]);

        return [
            'id' => (int) $response->json('id'),
            'url' => (string) ($response->json('links.html') ?? self::baseUrl()),
        ];
    }
}
