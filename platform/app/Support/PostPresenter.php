<?php

namespace App\Support;

use App\Models\Post;

/**
 * The single definition of a post's shape on the wire.
 *
 * Five controllers previously each built their own near-identical card array,
 * which is how fields quietly drift apart between the feed and the dashboard.
 */
class PostPresenter
{
    /** @return array<string, mixed> */
    public static function card(Post $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'cover_url' => $post->coverUrl(),
            'status' => $post->status,
            'reading_time' => $post->reading_time,
            'published_at' => $post->published_at?->toIso8601String(),
            'published_human' => $post->published_at?->format('j M Y'),
            'updated_human' => $post->updated_at?->diffForHumans(),
            // Present only when the query asked for it (withCount('highlights')),
            // so a listing that does not need marks pays nothing for them.
            'marks' => $post->highlights_count,
            'persona' => $post->relationLoaded('persona') && $post->persona ? [
                'handle' => $post->persona->handle,
                'display_name' => $post->persona->display_name,
                'avatar_path' => $post->persona->avatar_path,
            ] : null,
            'universe' => $post->relationLoaded('universe') ? $post->universe?->preview() : null,
        ];
    }
}
