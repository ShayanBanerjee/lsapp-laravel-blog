<?php

namespace App\Support\Integrations;

use Illuminate\Support\Facades\Http;

/**
 * Crossref metadata lookup.
 *
 * Notable for needing no credentials at all — Crossref's REST API is open, so
 * this works for every user immediately. Used to turn a DOI a writer pastes in
 * into a formed citation rather than making them retype one.
 */
class Crossref
{
    /**
     * @return array{doi: string, title: string, authors: string, year: ?int, container: ?string, url: string}|null
     */
    public static function lookup(string $doi): ?array
    {
        $doi = trim(preg_replace('#^https?://(dx\.)?doi\.org/#i', '', trim($doi)) ?? '');

        if ($doi === '' || ! str_starts_with($doi, '10.')) {
            return null;
        }

        // Crossref asks for a contact in the UA and gives politer rate limits
        // for it. Anonymous requests get the shared pool.
        $response = Http::withHeaders([
            'User-Agent' => config('app.name').' (mailto:'.config('mail.from.address').')',
        ])->timeout(10)->get('https://api.crossref.org/works/'.rawurlencode($doi));

        if (! $response->successful()) {
            return null;
        }

        $work = $response->json('message');

        if (! is_array($work)) {
            return null;
        }

        $authors = collect($work['author'] ?? [])
            ->map(fn (array $author) => trim(($author['family'] ?? '').', '.($author['given'] ?? '')))
            ->filter(fn (string $name) => $name !== ',')
            ->implode('; ');

        return [
            'doi' => (string) ($work['DOI'] ?? $doi),
            'title' => (string) (is_array($work['title'] ?? null) ? ($work['title'][0] ?? '') : ''),
            'authors' => $authors,
            'year' => isset($work['issued']['date-parts'][0][0]) ? (int) $work['issued']['date-parts'][0][0] : null,
            'container' => is_array($work['container-title'] ?? null) ? ($work['container-title'][0] ?? null) : null,
            'url' => (string) ($work['URL'] ?? 'https://doi.org/'.$doi),
        ];
    }
}
