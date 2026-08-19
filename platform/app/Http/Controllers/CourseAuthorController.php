<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Post;
use App\Models\Scopes\StandalonePostScope;
use App\Support\CoursePresenter;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Building a course.
 *
 * Publishing state is owned by the course, not by individual lessons: a lesson
 * is only ever readable as part of a path, so letting the two drift apart would
 * mean a published course containing invisible steps. `syncLessonPublication`
 * is the single place that keeps them in step, and it runs on every state
 * change.
 */
class CourseAuthorController extends Controller
{
    private const COVER_DIR = 'course-covers';

    public function create(Request $request): Response
    {
        $this->authorize('create', Course::class);

        return Inertia::render('courses/create', [
            'personas' => $this->writablePersonas($request),
            'levels' => Course::LEVELS,
            'canPublish' => $request->user()->canPublish(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Course::class);

        $data = $this->validateCourse($request);
        $persona = $request->user()->personas()->with('universe')->findOrFail($data['persona_id']);
        $this->authorize('use', $persona->universe);
        $this->guardPublishing($request, $data['status']);

        $course = Course::create([
            'user_id' => $request->user()->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => $this->uniqueSlug($data['title']),
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'cover_image' => $this->storeCover($request),
            'level' => $data['level'],
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        return to_route('courses.edit', $course)->with('success', 'Course created. Add your first module.');
    }

    public function edit(Request $request, Course $course): Response
    {
        $this->authorize('update', $course);

        $course->load(['persona.universe', 'universe']);
        $modules = $course->modules()->with('lessons')->get();

        return Inertia::render('courses/edit', [
            'course' => [
                ...CoursePresenter::card($course, CoursePresenter::readingOrder($modules)->count()),
                'subtitle' => $course->subtitle,
                'description' => $course->description,
                'persona_id' => $course->persona_id,
            ],
            'modules' => $modules->map(fn (CourseModule $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'summary' => $module->summary,
                'position' => $module->position,
                'lessons' => $module->lessons->map(fn (Post $lesson) => [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'title' => $lesson->title,
                    'body' => $lesson->body,
                    'position' => $lesson->position,
                    'reading_time' => $lesson->reading_time,
                ])->values(),
            ]),
            'personas' => $this->writablePersonas($request),
            'levels' => Course::LEVELS,
            'canPublish' => $request->user()->canPublish(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $data = $this->validateCourse($request);
        $persona = $request->user()->personas()->with('universe')->findOrFail($data['persona_id']);
        $this->authorize('use', $persona->universe);
        $this->guardPublishing($request, $data['status']);

        $cover = $this->storeCover($request);

        if ($cover && $course->hasUploadedCover()) {
            Storage::disk('public')->delete($course->cover_image);
        }

        $course->update([
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'cover_image' => $cover ?? $course->cover_image,
            'level' => $data['level'],
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published'
                ? ($course->published_at ?? now())
                : null,
        ]);

        $this->syncLessonPublication($course);

        return back()->with('success', 'Course updated.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        if ($course->hasUploadedCover()) {
            Storage::disk('public')->delete($course->cover_image);
        }

        // Modules and lessons cascade; progress rows cascade with them.
        $course->delete();

        return to_route('courses.index')->with('success', 'Course removed.');
    }

    public function storeModule(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:240'],
        ]);

        $course->modules()->create([
            ...$data,
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return back()->with('success', 'Module added.');
    }

    public function updateModule(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->authorize('update', $course);
        $this->assertBelongs($course, $module);

        $module->update($request->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:240'],
        ]));

        return back()->with('success', 'Module updated.');
    }

    public function destroyModule(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->authorize('update', $course);
        $this->assertBelongs($course, $module);

        $module->delete();

        return back()->with('success', 'Module removed, along with its lessons.');
    }

    public function storeLesson(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->authorize('update', $course);
        $this->assertBelongs($course, $module);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);

        $body = HtmlSanitizer::clean($data['body']);

        Post::create([
            'user_id' => $course->user_id,
            'persona_id' => $course->persona_id,
            'universe_id' => $course->universe_id,
            'course_module_id' => $module->id,
            'position' => (int) $module->lessons()->max('position') + 1,
            'slug' => $this->uniqueLessonSlug($data['title']),
            'title' => $data['title'],
            'excerpt' => HtmlSanitizer::excerpt($body),
            'body' => $body,
            // A lesson's visibility is the course's visibility, always.
            'status' => $course->status,
            'reading_time' => Post::estimateReadingTime($body),
            'published_at' => $course->published_at,
        ]);

        return back()->with('success', 'Lesson added.');
    }

    public function updateLesson(Request $request, Course $course, CourseModule $module, string $lesson): RedirectResponse
    {
        $this->authorize('update', $course);
        $this->assertBelongs($course, $module);

        $post = $module->lessons()->where('slug', $lesson)->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);

        $body = HtmlSanitizer::clean($data['body']);

        $post->update([
            'title' => $data['title'],
            'excerpt' => HtmlSanitizer::excerpt($body),
            'body' => $body,
            'reading_time' => Post::estimateReadingTime($body),
        ]);

        return back()->with('success', 'Lesson saved.');
    }

    public function destroyLesson(Request $request, Course $course, CourseModule $module, string $lesson): RedirectResponse
    {
        $this->authorize('update', $course);
        $this->assertBelongs($course, $module);

        $module->lessons()->where('slug', $lesson)->firstOrFail()->delete();

        return back()->with('success', 'Lesson removed.');
    }

    /**
     * Reorder modules, or the lessons inside one module.
     *
     * Positions are rewritten from the submitted order rather than swapped
     * pairwise, so a list that has drifted out of sequence — through a deletion,
     * or two tabs saving at once — is repaired by the next reorder instead of
     * preserving the gap.
     */
    public function reorder(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'kind' => ['required', 'in:modules,lessons'],
            'module_id' => ['nullable', 'integer'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($course, $data) {
            if ($data['kind'] === 'modules') {
                // Scoped to this course, so an id from another course is
                // silently ignored rather than reordered.
                foreach ($data['ids'] as $position => $id) {
                    $course->modules()->whereKey($id)->update(['position' => $position]);
                }

                return;
            }

            $module = $course->modules()->findOrFail($data['module_id']);

            foreach ($data['ids'] as $position => $id) {
                $module->lessons()->whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    /**
     * Keep every lesson's publication state equal to its course's.
     *
     * Runs on any course state change. Lessons are never individually
     * publishable, so this is the only writer of those two columns after
     * creation.
     */
    private function syncLessonPublication(Course $course): void
    {
        Post::withoutGlobalScope(StandalonePostScope::class)
            ->whereIn('course_module_id', $course->modules()->select('id'))
            ->update([
                'status' => $course->status,
                'published_at' => $course->published_at,
            ]);
    }

    private function assertBelongs(Course $course, CourseModule $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    /** @return array<string, mixed> */
    private function validateCourse(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'persona_id' => ['required', 'integer'],
            'level' => ['required', 'in:'.implode(',', Course::LEVELS)],
            'status' => ['required', 'in:draft,published'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function guardPublishing(Request $request, string $status): void
    {
        if ($status === 'published' && ! $request->user()->canPublish()) {
            throw ValidationException::withMessages([
                'status' => 'Confirm your email address before publishing. Your draft is safe — we can send the link again.',
            ]);
        }
    }

    private function storeCover(Request $request): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        return $request->file('cover_image')->store(self::COVER_DIR, 'public');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Lesson slugs share the posts table, so uniqueness is checked against
     * every post — including the ones the standalone scope would hide.
     */
    private function uniqueLessonSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'lesson';
        $slug = $base;
        $suffix = 2;

        while (Post::withoutGlobalScope(StandalonePostScope::class)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function writablePersonas(Request $request): Collection
    {
        return $request->user()->personas()->with('universe')->get()
            ->filter(fn ($persona) => $request->user()->canAccessUniverse($persona->universe))
            ->map(fn ($persona) => [
                'id' => $persona->id,
                'handle' => $persona->handle,
                'display_name' => $persona->display_name,
                'universe' => $persona->universe->preview(),
            ])
            ->values();
    }
}
