<?php

namespace App\Support;

use App\Models\Post;

/**
 * Search and social metadata.
 *
 * Reading is deliberately free and unauthenticated on this platform precisely
 * so that pieces are indexable and shareable — that only pays off if the
 * metadata is right, so this is built as a first-class concern rather than a
 * handful of tags sprinkled through components.
 */
class Seo
{
    /** @return array<string, mixed> */
    public static function forPost(Post $post): array
    {
        $title = $post->title;
        $description = $post->excerpt
            ?? HtmlSanitizer::excerpt($post->body, 155);

        $image = $post->coverUrl();
        $url = route('posts.show', $post);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'image' => $image ? url($image) : null,
            'type' => 'article',
            'publishedAt' => $post->published_at?->toIso8601String(),
            'modifiedAt' => $post->updated_at?->toIso8601String(),
            'author' => $post->persona?->display_name ?? $post->user?->name,
            'section' => $post->relationLoaded('categories') ? $post->categories->first()?->name : null,
            'readingTime' => $post->reading_time,
            // JSON-LD is what earns the rich result; without it a piece is just
            // another blue link.
            'jsonLd' => self::articleSchema($post, $url, $description, $image),
        ];
    }

    /** @return array<string, mixed> */
    private static function articleSchema(Post $post, string $url, string $description, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => mb_substr($post->title, 0, 110),
            'description' => $description,
            'image' => $image ? url($image) : null,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->persona?->display_name ?? $post->user?->name ?? 'Anonymous',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'logo' => ['@type' => 'ImageObject', 'url' => url('/favicon.svg')],
            ],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'wordCount' => str_word_count(strip_tags($post->body)),
            'timeRequired' => 'PT'.max(1, (int) $post->reading_time).'M',
        ]);
    }
}
