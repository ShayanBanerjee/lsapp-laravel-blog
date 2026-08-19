<?php

namespace App\Support\Academic;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Crossref metadata lookup.
 *
 * Public and unauthenticated, so this needs no keys — it turns a DOI a writer
 * pastes into a real citation, rather than making them retype an author list.
 *
 * Crossref asks API users to identify themselves in the User-Agent with a
 * contact address, and rewards it with the faster "polite" pool. That is a
 * request, not a requirement, which is exactly why it is easy to skip and
 * worth not skipping.
 */
class Crossref
{
    private const ENDPOINT = 'https://api.crossref.org/works/';

    /** Metadata does not change; a day of caching costs nothing and is polite. */
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @return array<string, mixed>|null
     */
    public static function lookup(string $doi): ?array
    {
        $doi = self::normalize($doi);

        if ($doi === null) {
            return null;
        }

        return Cache::remember('crossref:'.$doi, self::CACHE_TTL_SECONDS, function () use ($doi) {
            $response = Http::withUserAgent(self::userAgent())
                ->acceptJson()
                ->timeout(8)
                ->get(self::ENDPOINT.rawurlencode($doi));

            if ($response->failed()) {
                return null;
            }

            $work = $response->json('message');

            return is_array($work) ? self::present($work, $doi) : null;
        });
    }

    /**
     * Accepts a bare DOI, a doi.org URL, or a "doi:" prefixed string.
     *
     * People paste whichever of these their reference manager gave them, and
     * rejecting two of the three would be a pointless failure.
     */
    public static function normalize(string $input): ?string
    {
        $value = trim($input);
        $value = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $value) ?? $value;

        return preg_match('#^10\.\d{4,9}/\S+$#', $value) ? $value : null;
    }

    /** @param  array<string, mixed>  $work */
    private static function present(array $work, string $doi): array
    {
        $authors = collect($work['author'] ?? [])
            ->map(fn (array $author) => trim(($author['given'] ?? '').' '.($author['family'] ?? '')))
            ->filter()
            ->values()
            ->all();

        return [
            'doi' => $doi,
            'url' => $work['URL'] ?? 'https://doi.org/'.$doi,
            'title' => is_array($work['title'] ?? null) ? ($work['title'][0] ?? '') : ($work['title'] ?? ''),
            'container' => is_array($work['container-title'] ?? null)
                ? ($work['container-title'][0] ?? null)
                : ($work['container-title'] ?? null),
            'authors' => $authors,
            'year' => $work['issued']['date-parts'][0][0] ?? null,
            'publisher' => $work['publisher'] ?? null,
            'type' => $work['type'] ?? null,
        ];
    }

    private static function userAgent(): string
    {
        $contact = config('services.crossref.mailto');

        return config('app.name').'/1.0 (+'.config('app.url').')'
            .($contact ? ' mailto:'.$contact : '');
    }
}
