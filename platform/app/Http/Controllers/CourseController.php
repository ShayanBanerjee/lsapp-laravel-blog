<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Post;
use App\Support\Ads;
use App\Support\HtmlSanitizer;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Learning paths.
 *
 * The side panel is the point. Video makes you scrub a timeline to find the
 * one part that mattered; a contents panel with per-section state means a
 * reader can leave in the middle of module three and come back to exactly
 * there. That is the addressability argument from STRATEGY.md, built.
 */
class CourseController extends Controller
{
    public function index(): Response
    {
        $courses = Course::published()
            ->with(['universe', 'persona'])
            ->withCount('lessons')
            ->orderBy('sort_order')
            ->latest('published_at')
            ->get()
            ->map(fn (Course $course) => $course->card());

        return Inertia::render('courses/index', [
            'courses' => $courses,
        ])->withViewData(['seo' => Seo::forPage(
            'Tutorials',
            'Multi-part learning paths you can leave and come back to — with a contents panel that remembers where you were.',
            route('courses.index'),
        )]);
    }

    public function show(Request $request, Course $course): Response
    {
        abort_unless($this->readable($request, $course), 404);

        return Inertia::render('courses/show', [
            'course' => $course->card(),
            'outline' => $this->outline($request, $course),
        ])->withViewData(['seo' => Seo::forPage(
            $course->title,
            $course->description ?? $course->subtitle ?? '',
            route('courses.show', $course),
            $course->cover_image,
        )]);
    }

    public function lesson(Request $request, Course $course, Post $lesson): Response
    {
        abort_unless($this->readable($request, $course), 404);

        // The lesson must belong to *this* course. Without this a crafted URL
        // could pair any course with any lesson, which is both wrong and an
        // information leak once courses can be unpublished.
        abort_unless(
            $lesson->isLesson() && $lesson->courseModule?->course_id === $course->id,
            404
        );

        $outline = $this->outline($request, $course);

        // Flatten once to find neighbours, so "next lesson" is not another query.
        $flat = collect($outline)->flatMap(fn (array $module) => $module['lessons'])->values();
        $position = $flat->search(fn (array $item) => $item['slug'] === $lesson->slug);

        $lesson->loadCount('highlights');

        return Inertia::render('courses/lesson', [
            'course' => $course->card(),
            'outline' => $outline,
            'lesson' => [
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'body' => $lesson->body,
                'reading_time' => $lesson->reading_time,
                'marks' => $lesson->highlights_count,
                'completed' => $request->user()
                    ? LessonProgress::where('user_id', $request->user()->id)
                        ->where('post_id', $lesson->id)
                        ->whereNotNull('completed_at')
                        ->exists()
                    : false,
            ],
            'position' => [
                'index' => $position === false ? 0 : $position + 1,
                'total' => $flat->count(),
                'previous' => $position > 0 ? $flat[$position - 1] : null,
                'next' => $position !== false && $position + 1 < $flat->count() ? $flat[$position + 1] : null,
            ],
            // Marks and responses work here unchanged — a lesson is a post.
            'marked' => $lesson->markedPassages(),
            'highlights' => $request->user()
                ? $lesson->highlights()->where('user_id', $request->user()->id)
                    ->get(['id', 'block_index', 'start_offset', 'end_offset', 'quote'])
                : [],
            'ads' => Ads::forRequest($request, 'post'),
        ])->withViewData(['seo' => Seo::forPage(
            $lesson->title,
            HtmlSanitizer::excerpt($lesson->body, 155),
            route('courses.lesson', [$course, $lesson]),
            $course->cover_image,
        )]);
    }

    /** Mark a lesson done, or undo it. Reader-asserted, never inferred. */
    public function progress(Request $request, Course $course, Post $lesson): RedirectResponse
    {
        abort_unless(
            $lesson->isLesson() && $lesson->courseModule?->course_id === $course->id,
            404
        );

        $progress = LessonProgress::firstOrNew([
            'user_id' => $request->user()->id,
            'post_id' => $lesson->id,
        ]);

        $progress->completed_at = $progress->completed_at ? null : now();
        $progress->save();

        return back();
    }

    /**
     * The contents panel: modules, their lessons, and this reader's progress.
     *
     * Three queries regardless of course size — modules with lessons eager
     * loaded, plus one lookup of the reader's completed ids. Building it
     * per-lesson would be an N+1 on the single most-visited page of a course.
     *
     * @return array<int, array<string, mixed>>
     */
    private function outline(Request $request, Course $course): array
    {
        $modules = $course->modules()->with(['lessons' => function ($query) {
            $query->publishedLessons()->orderBy('sort_order');
        }])->get();

        $completed = $request->user()
            ? LessonProgress::where('user_id', $request->user()->id)
                ->whereNotNull('completed_at')
                ->pluck('post_id')
                ->flip()
            : collect();

        return $modules->map(fn ($module) => [
            'title' => $module->title,
            'summary' => $module->summary,
            'lessons' => $module->lessons->map(fn (Post $lesson) => [
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'reading_time' => $lesson->reading_time,
                'completed' => $completed->has($lesson->id),
            ])->values()->all(),
        ])->values()->all();
    }

    /** Drafts stay visible to their author and nobody else — same rule as posts. */
    private function readable(Request $request, Course $course): bool
    {
        return $course->status === 'published'
            && $course->published_at !== null
            && $course->published_at <= now()
            || $request->user()?->id === $course->user_id;
    }
}
