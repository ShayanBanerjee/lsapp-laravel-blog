<?php

namespace App\Support\Academic;

use App\Models\Post;
use App\Models\User;

/**
 * A post, reduced to the fields a manuscript actually needs.
 *
 * Every exporter takes one of these rather than a Post, so adding a format
 * means writing one renderer and not rediscovering where the author's name
 * lives, or that a body is HTML.
 */
readonly class Manuscript
{
    /**
     * @param  array<int, string>  $keywords
     */
    public function __construct(
        public string $title,
        public string $authorName,
        public ?string $orcid,
        public string $abstract,
        public string $bodyHtml,
        public array $keywords,
        public ?string $publishedAt,
        public string $url,
        public string $siteName,
        public ?string $affiliation = null,
    ) {}

    public static function fromPost(Post $post, ?User $author = null): self
    {
        $author ??= $post->user;

        return new self(
            title: $post->title,
            authorName: $post->persona?->display_name ?? $author?->name ?? 'Anonymous',
            orcid: $author?->orcid_id,
            abstract: $post->excerpt ?? '',
            bodyHtml: $post->body,
            keywords: $post->relationLoaded('categories')
                ? $post->categories->pluck('name')->all()
                : [],
            publishedAt: $post->published_at?->toDateString(),
            url: route('posts.show', $post),
            siteName: config('app.name'),
        );
    }

    /** Surname-first key, as citation formats want it. */
    public function citationKey(): string
    {
        $surname = preg_replace('/[^a-z]/i', '', last(explode(' ', trim($this->authorName)))) ?: 'anon';
        $year = $this->publishedAt ? substr($this->publishedAt, 0, 4) : date('Y');
        $word = preg_replace('/[^a-z]/i', '', strtok(trim($this->title), ' ') ?: 'work');

        return strtolower($surname).$year.strtolower($word);
    }
}
