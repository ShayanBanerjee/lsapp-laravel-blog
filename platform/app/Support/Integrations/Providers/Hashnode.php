<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\Manuscript;
use App\Support\Academic\MarkdownExporter;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * Hashnode.
 *
 * GraphQL rather than REST, and it reports errors in a 200 response body — so
 * `$response->failed()` alone would treat a rejected mutation as a success.
 * Both are checked.
 */
class Hashnode implements PublishesPosts
{
    private const ENDPOINT = 'https://gql.hashnode.com/';

    public function key(): string
    {
        return 'hashnode';
    }

    public function label(): string
    {
        return 'Hashnode';
    }

    public function blurb(): string
    {
        return 'Cross-post to your Hashnode publication as a draft.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://hashnode.com/settings/developer';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'token', 'label' => 'Personal access token', 'help' => 'Hashnode → Settings → Developer', 'secret' => true],
            ['key' => 'publication_id', 'label' => 'Publication ID', 'help' => 'From your publication dashboard URL', 'secret' => false],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $result = $this->query($credentials, 'query { me { username } }');

        return $result['ok'] && filled(data_get($result['data'], 'me.username'))
            ? PushResult::success('Connected to Hashnode as @'.data_get($result['data'], 'me.username').'.')
            : PushResult::failure('Hashnode did not accept that token.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $mutation = <<<'GQL'
        mutation CreateDraft($input: CreateDraftInput!) {
          createDraft(input: $input) { draft { id slug } }
        }
        GQL;

        $result = $this->query($credentials, $mutation, [
            'input' => array_filter([
                'publicationId' => $credentials['publication_id'] ?? null,
                'title' => $manuscript->title,
                'contentMarkdown' => MarkdownExporter::render($manuscript),
                'originalArticleURL' => $manuscript->url,
                'subtitle' => mb_substr($manuscript->abstract, 0, 250) ?: null,
            ]),
        ]);

        if (! $result['ok']) {
            return PushResult::failure('Hashnode refused the draft: '.$result['error']);
        }

        return PushResult::success('Draft created on Hashnode.', 'https://hashnode.com/drafts');
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>|null, error: string}
     */
    private function query(array $credentials, string $query, array $variables = []): array
    {
        $response = Http::withHeaders(['Authorization' => $credentials['token'] ?? ''])
            ->post(self::ENDPOINT, array_filter([
                'query' => $query,
                'variables' => $variables ?: null,
            ]));

        if ($response->failed()) {
            return ['ok' => false, 'data' => null, 'error' => 'HTTP '.$response->status()];
        }

        // GraphQL answers 200 even when the operation failed.
        $errors = $response->json('errors');

        if (! empty($errors)) {
            return ['ok' => false, 'data' => null, 'error' => $errors[0]['message'] ?? 'unknown error'];
        }

        return ['ok' => true, 'data' => $response->json('data'), 'error' => ''];
    }
}
