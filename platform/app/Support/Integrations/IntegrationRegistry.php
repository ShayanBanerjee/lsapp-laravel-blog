<?php

namespace App\Support\Integrations;

use App\Support\Integrations\Providers\Buttondown;
use App\Support\Integrations\Providers\DevTo;
use App\Support\Integrations\Providers\Ghost;
use App\Support\Integrations\Providers\GithubGist;
use App\Support\Integrations\Providers\Hashnode;
use App\Support\Integrations\Providers\Instapaper;
use App\Support\Integrations\Providers\Notion;
use App\Support\Integrations\Providers\Readwise;
use App\Support\Integrations\Providers\WordPress;
use App\Support\Integrations\Providers\Zotero;
use Illuminate\Support\Collection;

/**
 * Every integration the app knows about.
 *
 * Order is by usefulness, not alphabet: Readwise first because a mark here and
 * a highlight there are the same object, so it is the only one where nothing
 * is lost in translation.
 *
 * **Two services people ask for are absent, on purpose.** Medium's publishing
 * API was withdrawn in 2023, and Pocket shut down in 2025 — there is nothing to
 * integrate with in either case. Obsidian is also absent from this list because
 * it has no cloud API at all; its integration is the Markdown export, which
 * writes YAML frontmatter a vault reads directly.
 */
class IntegrationRegistry
{
    /** @var array<int, class-string<Integration>> */
    private const PROVIDERS = [
        Readwise::class,
        Notion::class,
        Zotero::class,
        Ghost::class,
        DevTo::class,
        Hashnode::class,
        WordPress::class,
        Buttondown::class,
        GithubGist::class,
        Instapaper::class,
    ];

    /** @return Collection<string, Integration> */
    public function all(): Collection
    {
        return collect(self::PROVIDERS)
            ->map(fn (string $class) => new $class)
            ->keyBy(fn (Integration $provider) => $provider->key());
    }

    public function find(string $key): ?Integration
    {
        return $this->all()->get($key);
    }

    /** @return Collection<string, PublishesPosts> */
    public function destinations(): Collection
    {
        return $this->all()->filter(fn (Integration $provider) => $provider instanceof PublishesPosts);
    }

    /** @return Collection<string, ReceivesHighlights> */
    public function highlightSinks(): Collection
    {
        return $this->all()->filter(fn (Integration $provider) => $provider instanceof ReceivesHighlights);
    }

    /**
     * The catalogue, as the settings page renders it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(): array
    {
        return $this->all()
            ->map(fn (Integration $provider) => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'blurb' => $provider->blurb(),
                'credentials_url' => $provider->credentialsUrl(),
                'fields' => $provider->credentialFields(),
                'can_publish' => $provider instanceof PublishesPosts,
                'takes_highlights' => $provider instanceof ReceivesHighlights,
            ])
            ->values()
            ->all();
    }
}
