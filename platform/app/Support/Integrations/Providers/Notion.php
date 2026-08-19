<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\BlockParser;
use App\Support\Academic\Manuscript;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * Notion.
 *
 * Notion does not accept Markdown or HTML — a page is an array of typed
 * blocks — so the body is mapped through BlockParser into Notion's own block
 * shapes. That is why every exporter here shares one parser: this is the third
 * consumer of it and the one that would have been worst to write twice.
 */
class Notion implements PublishesPosts
{
    private const ENDPOINT = 'https://api.notion.com/v1';

    /** Pinned: Notion's API is versioned by date and changes shape between versions. */
    private const VERSION = '2022-06-28';

    /** Notion rejects a single rich-text run longer than this. */
    private const MAX_RUN = 2000;

    /** And rejects a page create carrying more children than this. */
    private const MAX_BLOCKS = 100;

    public function key(): string
    {
        return 'notion';
    }

    public function label(): string
    {
        return 'Notion';
    }

    public function blurb(): string
    {
        return 'Copy a piece into a Notion page, as real Notion blocks rather than a wall of text.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://www.notion.so/my-integrations';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'token', 'label' => 'Internal integration secret', 'help' => 'From notion.so/my-integrations', 'secret' => true],
            ['key' => 'parent_page_id', 'label' => 'Parent page ID', 'help' => 'The page to add to — share it with your integration first', 'secret' => false],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))->get(self::ENDPOINT.'/users/me');

        if ($response->failed()) {
            return PushResult::failure('Notion did not accept that secret.');
        }

        if (blank($credentials['parent_page_id'] ?? null)) {
            return PushResult::failure('Notion needs a parent page ID to write into.');
        }

        return PushResult::success('Connected to Notion.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $response = Http::withHeaders($this->headers($credentials))
            ->post(self::ENDPOINT.'/pages', [
                'parent' => ['page_id' => str_replace('-', '', (string) ($credentials['parent_page_id'] ?? ''))],
                'properties' => [
                    'title' => ['title' => [['text' => ['content' => mb_substr($manuscript->title, 0, self::MAX_RUN)]]]],
                ],
                'children' => $this->blocks($manuscript),
            ]);

        if ($response->failed()) {
            return PushResult::failure('Notion refused the page: '.$response->json('message', 'unknown error'));
        }

        return PushResult::success('Page created in Notion.', $response->json('url'));
    }

    /** @return array<int, array<string, mixed>> */
    private function blocks(Manuscript $manuscript): array
    {
        $blocks = [$this->paragraph('Originally published at '.$manuscript->url)];

        foreach (BlockParser::parse($manuscript->bodyHtml) as $block) {
            if (count($blocks) >= self::MAX_BLOCKS) {
                break;
            }

            $blocks[] = match ($block['type']) {
                'heading' => $this->heading($block['text'], $block['level']),
                'quote' => $this->simple('quote', $block['text']),
                'code' => [
                    'object' => 'block',
                    'type' => 'code',
                    'code' => ['language' => 'plain text', 'rich_text' => $this->richText($block['text'])],
                ],
                'bullets', 'numbers' => null,
                default => $this->paragraph($block['text']),
            } ?? $this->paragraph(implode("\n", $block['items'] ?? []));
        }

        return $blocks;
    }

    /** @return array<string, mixed> */
    private function heading(string $text, int $level): array
    {
        $type = 'heading_'.min(3, max(1, $level - 1));

        return ['object' => 'block', 'type' => $type, $type => ['rich_text' => $this->richText($text)]];
    }

    /** @return array<string, mixed> */
    private function paragraph(string $text): array
    {
        return $this->simple('paragraph', $text);
    }

    /** @return array<string, mixed> */
    private function simple(string $type, string $text): array
    {
        return ['object' => 'block', 'type' => $type, $type => ['rich_text' => $this->richText($text)]];
    }

    /** @return array<int, array<string, mixed>> */
    private function richText(string $text): array
    {
        // Split rather than truncate: a long paragraph should arrive whole,
        // across several runs, not stop mid-sentence at 2000 characters.
        return collect(mb_str_split($text ?: ' ', self::MAX_RUN))
            ->map(fn (string $chunk) => ['type' => 'text', 'text' => ['content' => $chunk]])
            ->all();
    }

    /** @return array<string, string> */
    private function headers(array $credentials): array
    {
        return [
            'Authorization' => 'Bearer '.($credentials['token'] ?? ''),
            'Notion-Version' => self::VERSION,
        ];
    }
}
