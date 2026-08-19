<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Post;
use App\Support\Ads;
use App\Support\CoursePresenter;
use App\Support\Seo;
use App\Support\StoryBlocks;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reading a course.
 *
 * Lessons are Posts, so everything the reading view already does — marking a
 * passage, answering it, saving the piece, the reader's own typography — works
 * here without a second implementation. What a course adds on top is order:
 * a persistent contents panel, a position within it, and a record of how far
 * this reader has got.
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $courses = Course::published()
            ->with(['persona:id,handle,display_name', 'universe'])
            ->withCount('lessons')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        // One query for every course's progress rather than one per course.
        $completed = $user
            ? LessonProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->whereIn('course_id', $courses->pluck('id'))
                ->selectRaw('course_id, COUNT(*) as finished')
                ->groupBy('course_id')
                ->pluck('finished', 'course_id')
            : collect();

        return Inertia::render('courses/index', [
            'courses' => $courses->through(fn (Course $course) => CoursePresenter::card(
                $course,
                $course->lessons_count,
                (int) ($completed[$course->id] ?? 0),
            )),
            'ads' => Ads::forRequest($request, 'feed'),
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        $this->authorize('view', $course);

        $course->load(['persona.universe', 'persona.customTheme', 'persona.user', 'universe', 'user']);
        $modules = $course->modules()->with('lessons')->get();
        $lessons = CoursePresenter::readingOrder($modules);

        $completedIds = $this->completedIds($request, $course);
        $seo = Seo::forCourse($course, $lessons->count());

        return Inertia::render('courses/show', [
            'course' => [
                ...CoursePresenter::card($course, $lessons->count(), $completedIds->count()),
                'estimated_minutes' => $course->estimatedMinutes($lessons),
                // What is left, not what it costs in total — the number a
                // returning reader actually wants.
                'remaining_minutes' => (int) $lessons
                    ->reject(fn (Post $lesson) => $completedIds->contains($lesson->id))
                    ->sum(fn (Post $lesson) => max(1, (int) $lesson->reading_time)),
                'is_complete' => $lessons->isNotEmpty() && $completedIds->count() >= $lessons->count(),
                'can' => [
                    'update' => $request->user()?->can('update', $course) ?? false,
                ],
            ],
            'outline' => CoursePresenter::outline($course, $modules, $completedIds),
            // Where "Continue" should go: the first unfinished lesson, or the
            // start if nothing is done yet.
            'resume' => $this->resumeUrl($course, $lessons, $completedIds),
            'universe' => UniverseContext::serialize($course->universe, $request->user(), $course->persona),
            'ads' => Ads::forRequest($request, 'feed'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    /**
     * One lesson, with the contents panel beside it.
     */
    public function lesson(Request $request, Course $course, string $lesson): Response
    {
        $this->authorize('view', $course);

        $course->load(['persona.universe', 'persona.customTheme', 'persona.user', 'universe']);
        $modules = $course->modules()->with('lessons')->get();
        $order = CoursePresenter::readingOrder($modules);

        $current = $order->firstWhere('slug', $lesson);

        abort_if($current === null, 404);

        $user = $request->user();
        $completedIds = $this->completedIds($request, $course);
        $index = $order->search(fn (Post $candidate) => $candidate->id === $current->id);

        $seo = Seo::forLesson($course, $current);

        return Inertia::render('courses/lesson', [
            'course' => CoursePresenter::card($course, $order->count(), $completedIds->count()),
            'outline' => CoursePresenter::outline($course, $modules, $completedIds, $current->id),
            'lesson' => [
                'id' => $current->id,
                'slug' => $current->slug,
                'title' => $current->title,
                'body' => StoryBlocks::expand($current->body),
                'reading_time' => $current->reading_time,
                'completed' => $completedIds->contains($current->id),
                'number' => $index + 1,
                'total' => $order->count(),
            ],
            'previous' => $this->neighbour($course, $order, $index - 1),
            'next' => $this->neighbour($course, $order, $index + 1),

            'progress' => [
                'done' => $completedIds->count(),
                'total' => $order->count(),
                // Excludes this lesson when it is already finished, so the
                // number does not jump backwards on marking it done.
                'remaining_minutes' => (int) $order
                    ->reject(fn (Post $lesson) => $completedIds->contains($lesson->id) || $lesson->id === $current->id)
                    ->sum(fn (Post $lesson) => max(1, (int) $lesson->reading_time)),
                // True once marking *this* lesson would finish the course.
                'finishes_course' => ! $completedIds->contains($current->id)
                    && $completedIds->count() + 1 >= $order->count(),
            ],

            // The marking layer, identical in shape to a post page — the
            // Readable component is shared, so the payload must be too.
            'passages' => $current->markedPassages()->map(fn ($passage) => [
                'block_index' => (int) $passage->block_index,
                'start_offset' => (int) $passage->start_offset,
                'end_offset' => (int) $passage->end_offset,
                'quote' => $passage->quote,
                'marks' => (int) $passage->marks,
            ]),
            'myHighlights' => $user
                ? $current->highlights()->where('user_id', $user->id)->get()
                    ->map(fn ($highlight) => [
                        'id' => $highlight->id,
                        'block_index' => $highlight->block_index,
                        'start_offset' => $highlight->start_offset,
                        'end_offset' => $highlight->end_offset,
                    ])
                : [],

            'universe' => UniverseContext::serialize($course->universe, $user, $course->persona),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    /**
     * Mark a lesson finished, or unfinished again.
     *
     * Toggling rather than one-way: someone who marks the wrong row, or wants
     * to redo a section, should not be stuck with a progress bar that lies.
     */
    public function toggleProgress(Request $request, Course $course, string $lesson): RedirectResponse
    {
        $this->authorize('view', $course);

        $post = $course->lessons()->where('posts.slug', $lesson)->firstOrFail();

        $record = LessonProgress::firstOrNew([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
        ]);

        $record->course_id = $course->id;
        $record->completed_at = $record->completed_at ? null : now();
        $record->save();

        return back()->with('success', $record->completed_at ? 'Marked as done.' : 'Marked as unread.');
    }

    /**
     * Lesson ids this reader has finished.
     *
     * @return Collection<int, int>
     */
    private function completedIds(Request $request, Course $course): Collection
    {
        $user = $request->user();

        if (! $user) {
            return collect();
        }

        return LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('completed_at')
            ->pluck('post_id');
    }

    /** @param  Collection<int, Post>  $order */
    private function neighbour(Course $course, Collection $order, int $index): ?array
    {
        $lesson = $order->get($index);

        return $lesson ? [
            'title' => $lesson->title,
            'url' => route('courses.lesson', [$course, $lesson], absolute: false),
        ] : null;
    }

    /** @param  Collection<int, Post>  $lessons */
    private function resumeUrl(Course $course, Collection $lessons, Collection $completedIds): ?array
    {
        $next = $lessons->first(fn (Post $lesson) => ! $completedIds->contains($lesson->id))
            ?? $lessons->first();

        if (! $next) {
            return null;
        }

        return [
            'title' => $next->title,
            'url' => route('courses.lesson', [$course, $next], absolute: false),
            'restarting' => $completedIds->count() === $lessons->count() && $lessons->isNotEmpty(),
        ];
    }
}
