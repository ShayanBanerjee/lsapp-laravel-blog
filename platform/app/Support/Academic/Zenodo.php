<?php

namespace App\Support\Academic;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Depositing a piece with Zenodo, which mints a real DOI.
 *
 * This is one of the few genuinely available academic endpoints: Zenodo has a
 * public deposition API, and a published deposition gets a resolvable DOI.
 * Compare the note at the bottom of the roadmap — IEEE and Springer accept
 * manuscripts only through editorial systems with no third-party submission
 * API, so a "submit to IEEE" button could not work. This one does.
 *
 * Three steps, all of which can fail independently: create the deposition,
 * upload the file, publish. Publishing is separated deliberately — it is the
 * irreversible one. A published Zenodo record cannot be deleted, only a new
 * version issued, so nothing here publishes without being asked to.
 */
class Zenodo
{
    public static function isConfigured(): bool
    {
        return filled(config('services.zenodo.token'));
    }

    public static function host(): string
    {
        return rtrim((string) config('services.zenodo.host', 'https://zenodo.org'), '/');
    }

    /**
     * Create a deposition and attach the manuscript.
     *
     * Returns the draft's id and its editing URL. The DOI is reserved at this
     * point but not yet resolvable — that happens on publish.
     *
     * @return array{id: int, doi: ?string, url: string}
     */
    public static function deposit(Manuscript $manuscript, string $filename, string $contents): array
    {
        throw_unless(self::isConfigured(), RuntimeException::class, 'Zenodo is not configured.');

        $created = self::client()
            ->post(self::host().'/api/deposit/depositions', [
                'metadata' => self::metadata($manuscript),
            ])
            ->throw()
            ->json();

        $bucket = $created['links']['bucket'] ?? null;

        throw_unless(is_string($bucket), RuntimeException::class, 'Zenodo did not return an upload bucket.');

        // The bucket API is a plain PUT of the raw bytes to bucket/filename —
        // not a multipart form. Zenodo's older file endpoint is deprecated and
        // caps out at 100MB.
        Http::withToken(config('services.zenodo.token'))
            ->withBody($contents, 'application/octet-stream')
            ->put($bucket.'/'.rawurlencode($filename))
            ->throw();

        return [
            'id' => (int) $created['id'],
            'doi' => $created['metadata']['prereserve_doi']['doi'] ?? null,
            'url' => $created['links']['html'] ?? self::host().'/deposit/'.$created['id'],
        ];
    }

    /**
     * Publish a draft deposition. Irreversible: this mints the DOI.
     *
     * @return array{doi: string, url: string}
     */
    public static function publish(int $depositionId): array
    {
        throw_unless(self::isConfigured(), RuntimeException::class, 'Zenodo is not configured.');

        $published = self::client()
            ->post(self::host().'/api/deposit/depositions/'.$depositionId.'/actions/publish')
            ->throw()
            ->json();

        return [
            'doi' => (string) ($published['doi'] ?? ''),
            'url' => (string) ($published['links']['record_html'] ?? self::host().'/record/'.$depositionId),
        ];
    }

    /** @return array<string, mixed> */
    private static function metadata(Manuscript $manuscript): array
    {
        $creator = ['name' => self::surnameFirst($manuscript->authorName)];

        if ($manuscript->orcid) {
            $creator['orcid'] = $manuscript->orcid;
        }

        return array_filter([
            'title' => $manuscript->title,
            'upload_type' => 'publication',
            'publication_type' => 'preprint',
            'description' => $manuscript->abstract !== ''
                ? $manuscript->abstract
                : 'Published on '.$manuscript->siteName.'.',
            'creators' => [$creator],
            'keywords' => $manuscript->keywords !== [] ? $manuscript->keywords : null,
            'publication_date' => $manuscript->publishedAt,
            // Zenodo requires an explicit licence for open access. CC-BY-4.0 is
            // the safe default for a deposit the author is choosing to make,
            // and is stated here rather than assumed silently.
            'access_right' => 'open',
            'license' => config('services.zenodo.license', 'cc-by-4.0'),
            'related_identifiers' => [[
                'identifier' => $manuscript->url,
                'relation' => 'isIdenticalTo',
                'scheme' => 'url',
            ]],
        ], fn ($value) => $value !== null);
    }

    /** Zenodo expects "Family, Given". */
    private static function surnameFirst(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];

        if (count($parts) < 2) {
            return $name;
        }

        $surname = array_pop($parts);

        return $surname.', '.implode(' ', $parts);
    }

    private static function client(): PendingRequest
    {
        return Http::withToken(config('services.zenodo.token'))->acceptJson()->asJson();
    }
}
