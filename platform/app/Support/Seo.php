<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Persona;
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

    /**
     * A plain page — the home page, a universe, the Deep Field.
     *
     * Carried over from the storytelling branch, which spotted that these
     * pages had no metadata at all: they are the three most linked-to URLs on
     * the site and were unfurling as a bare title.
     *
     * Returns the same complete shape as every other builder here, with nulls
     * where an article-specific field has no meaning. The Blade view and the
     * SeoHead component both read a fixed set of keys, and a partial payload
     * would make one of them reach for something absent.
     *
     * @return array<string, mixed>
     */
    public static function forPage(string $title, string $description, string $canonical, ?string $image = null): array
    {
        return [
            'title' => $title,
            'description' => HtmlSanitizer::excerpt($description, 155),
            'canonical' => $canonical,
            'image' => $image ? url($image) : null,
            'type' => 'website',
            'publishedAt' => null,
            'modifiedAt' => null,
            'author' => null,
            'section' => null,
            'readingTime' => null,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => HtmlSanitizer::excerpt($description, 155),
                'url' => $canonical,
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => config('app.name'),
                    'url' => config('app.url'),
                ],
            ],
        ];
    }

    /**
     * A persona profile.
     *
     * ProfilePage + Person is what lets a search engine treat @handle as an
     * identity rather than a listing page, which is the whole reason profiles
     * are a public URL in the first place.
     *
     * @return array<string, mixed>
     */
    public static function forPersona(Persona $persona): array
    {
        $url = route('profiles.show', $persona);
        $description = $persona->bio
            ? HtmlSanitizer::excerpt($persona->bio, 155)
            : sprintf('%s writes in %s on %s.', $persona->display_name, $persona->universe->name, config('app.name'));

        return [
            'title' => $persona->display_name.' (@'.$persona->handle.')',
            'description' => $description,
            'canonical' => $url,
            'image' => $persona->universe->hero_image ? url($persona->universe->hero_image) : null,
            'type' => 'profile',
            'publishedAt' => $persona->created_at?->toIso8601String(),
            'modifiedAt' => $persona->updated_at?->toIso8601String(),
            'author' => $persona->display_name,
            'section' => $persona->universe->name,
            'readingTime' => null,
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'dateCreated' => $persona->created_at?->toIso8601String(),
                'mainEntity' => array_filter([
                    '@type' => 'Person',
                    'name' => $persona->display_name,
                    'alternateName' => '@'.$persona->handle,
                    'description' => $persona->bio,
                    'url' => $url,
                ]),
            ]),
        ];
    }

    /**
     * A course overview.
     *
     * schema.org/Course is a supported rich result, which is the difference
     * between a learning path appearing as a course in search and appearing as
     * an ordinary page of links.
     *
     * @return array<string, mixed>
     */
    public static function forCourse(Course $course, int $lessonCount): array
    {
        $url = route('courses.show', $course);
        $description = $course->description
            ? HtmlSanitizer::excerpt($course->description, 155)
            : ($course->subtitle ?? $course->title);

        return [
            'title' => $course->title,
            'description' => $description,
            'canonical' => $url,
            'image' => $course->coverUrl() ? url($course->coverUrl()) : null,
            'type' => 'article',
            'publishedAt' => $course->published_at?->toIso8601String(),
            'modifiedAt' => $course->updated_at?->toIso8601String(),
            'author' => $course->persona?->display_name ?? $course->user?->name,
            'section' => $course->universe?->name,
            'readingTime' => null,
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $course->title,
                'description' => $description,
                'url' => $url,
                'educationalLevel' => $course->level,
                'numberOfCredits' => null,
                'provider' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $course->persona?->display_name ?? $course->user?->name ?? 'Anonymous',
                ],
                'hasCourseInstance' => [
                    '@type' => 'CourseInstance',
                    'courseMode' => 'online',
                    'courseWorkload' => 'PT'.max(1, $lessonCount * 10).'M',
                ],
            ]),
        ];
    }

    /**
     * One lesson inside a course.
     *
     * Canonicalised to its own URL rather than to the course: a lesson is a
     * distinct page with distinct content, and pointing every lesson at the
     * overview would collapse an entire course into one indexed page.
     *
     * @return array<string, mixed>
     */
    public static function forLesson(Course $course, Post $lesson): array
    {
        $url = route('courses.lesson', [$course, $lesson]);
        $description = HtmlSanitizer::excerpt($lesson->body, 155);

        return [
            'title' => $lesson->title.' — '.$course->title,
            'description' => $description,
            'canonical' => $url,
            'image' => $course->coverUrl() ? url($course->coverUrl()) : null,
            'type' => 'article',
            'publishedAt' => $lesson->published_at?->toIso8601String(),
            'modifiedAt' => $lesson->updated_at?->toIso8601String(),
            'author' => $course->persona?->display_name ?? $course->user?->name,
            'section' => $course->title,
            'readingTime' => $lesson->reading_time,
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'LearningResource',
                'name' => $lesson->title,
                'description' => $description,
                'url' => $url,
                'isPartOf' => ['@type' => 'Course', 'name' => $course->title, 'url' => route('courses.show', $course)],
                'timeRequired' => 'PT'.max(1, (int) $lesson->reading_time).'M',
            ]),
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
