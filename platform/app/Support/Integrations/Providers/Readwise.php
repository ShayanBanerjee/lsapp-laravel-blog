<?php

namespace App\Support\Integrations\Providers;

use App\Support\Integrations\PushResult;
use App\Support\Integrations\ReceivesHighlights;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Readwise.
 *
 * The closest fit of any integration here: a Readwise highlight is a passage,
 * a source and an optional note, which is exactly what a mark is. Nothing is
 * approximated in the mapping, and a reader's marks arrive in their own
 * review rotation rather than being stranded on one site.
 */
class Readwise implements ReceivesHighlights
{
    private const ENDPOINT = 'https://readwise.io/api/v2';

    /** Readwise rejects batches larger than this. */
    private const BATCH = 100;

    public function key(): string
    {
        return 'readwise';
    }

    public function label(): string
    {
        return 'Readwise';
    }

    public function blurb(): string
    {
        return 'Send the passages you mark here into your Readwise library, with the source and your notes.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://readwise.io/access_token';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'token', 'label' => 'Access token', 'help' => 'From readwise.io/access_token', 'secret' => true],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))->get(self::ENDPOINT.'/auth/');

        return $response->successful()
            ? PushResult::success('Connected to Readwise.')
            : PushResult::failure('Readwise did not accept that token.');
    }

    public function sendHighlights(array $credentials, Collection $highlights): PushResult
    {
        if ($highlights->isEmpty()) {
            return PushResult::failure('There is nothing to send yet — mark a passage first.');
        }

        $sent = 0;

        // Chunked because the endpoint caps a batch, and because a reader with
        // a year of marks would otherwise send one enormous request that times
        // out and tells them nothing about what got through.
        foreach ($highlights->chunk(self::BATCH) as $chunk) {
            $response = Http::withHeaders($this->headers($credentials))
                ->post(self::ENDPOINT.'/highlights/', [
                    'highlights' => $chunk->map(fn (array $highlight) => array_filter([
                        'text' => $highlight['quote'],
                        'title' => $highlight['title'],
                        'author' => $highlight['author'],
                        'source_url' => $highlight['url'],
                        'source_type' => 'article',
                        'category' => 'articles',
                        'highlighted_at' => $highlight['marked_at'] ?? null,
                        'note' => $highlight['note'] ?? null,
                    ]))->values()->all(),
                ]);

            if ($response->failed()) {
                return $sent === 0
                    ? PushResult::failure('Readwise refused the highlights.')
                    : PushResult::failure("Sent {$sent} highlights, then Readwise refused the rest.");
            }

            $sent += $chunk->count();
        }

        return PushResult::success("Sent {$sent} ".($sent === 1 ? 'highlight' : 'highlights').' to Readwise.', 'https://readwise.io/library');
    }

    /** @return array<string, string> */
    private function headers(array $credentials): array
    {
        return ['Authorization' => 'Token '.($credentials['token'] ?? '')];
    }
}
