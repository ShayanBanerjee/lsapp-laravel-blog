<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * The shape of a course on the wire.
 *
 * The contents panel is the whole feature, so `outline()` is written to answer
 * every question that panel asks in one pass over already-loaded relations —
 * module order, lesson order, which lesson is current, what is finished, and
 * what comes next. Doing that per-lesson in the view is how a persistent panel
 * turns into an N+1 on every page turn.
 */
class CoursePresenter
{
    /** @return array<string, mixed> */
    public static function card(Course $course, ?int $lessonCount = null, ?int $completed = null): array
    {
        return [
            'slug' => $course->slug,
            'title' => $course->title,
            'subtitle' => $course->subtitle,
            'description' => $course->description,
            'cover_url' => $course->coverUrl(),
            'level' => $course->level,
            'status' => $course->status,
            'published_human' => $course->published_at?->format('j M Y'),
            'lesson_count' => $lessonCount,
            'completed_count' => $completed,
            'persona' => $course->relationLoaded('persona') && $course->persona ? [
                'handle' => $course->persona->handle,
                'display_name' => $course->persona->display_name,
            ] : null,
            'universe' => $course->relationLoaded('universe') ? $course->universe?->preview() : null,
        ];
    }

    /**
     * The contents panel.
     *
     * @param  Collection<int, CourseModule>  $modules  with `lessons` loaded
     * @param  Collection<int, int>  $completedIds  lesson ids this reader has finished
     * @return array<int, array<string, mixed>>
     */
    public static function outline(Course $course, Collection $modules, Collection $completedIds, ?int $currentId = null): array
    {
        return $modules->map(fn (CourseModule $module) => [
            'id' => $module->id,
            'title' => $module->title,
            'summary' => $module->summary,
            // Per-module progress, so a long course reads as a set of finishable
            // parts rather than one bar that barely moves.
            'done' => $module->lessons->filter(fn (Post $l) => $completedIds->contains($l->id))->count(),
            'total' => $module->lessons->count(),
            'minutes' => (int) $module->lessons->sum(fn (Post $l) => max(1, (int) $l->reading_time)),
            'lessons' => $module->lessons->map(fn (Post $lesson) => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'reading_time' => $lesson->reading_time,
                'url' => route('courses.lesson', [$course, $lesson], absolute: false),
                'completed' => $completedIds->contains($lesson->id),
                'current' => $lesson->id === $currentId,
            ])->values(),
        ])->values()->all();
    }

    /**
     * Flatten the outline to reading order, which is what previous/next need.
     *
     * @param  Collection<int, CourseModule>  $modules
     * @return Collection<int, Post>
     */
    public static function readingOrder(Collection $modules): Collection
    {
        return $modules->flatMap(fn (CourseModule $module) => $module->lessons)->values();
    }
}
