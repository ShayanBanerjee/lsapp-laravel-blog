<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\LessonProgress;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Scopes\StandalonePostScope;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{0: User, 1: Persona} */
    private function teacher(): array
    {
        $user = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'teacher'.$user->id,
            'display_name' => 'Teacher',
        ]);

        return [$user, $persona];
    }

    /** A published course with two modules of two lessons each. */
    private function course(User $user, Persona $persona, string $status = 'published'): Course
    {
        $course = Course::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-real-course',
            'title' => 'A Real Course',
            'level' => 'beginner',
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDay() : null,
        ]);

        foreach ([['One', ['first', 'second']], ['Two', ['third', 'fourth']]] as $position => [$title, $lessons]) {
            $module = CourseModule::create([
                'course_id' => $course->id,
                'title' => "Module {$title}",
                'position' => $position,
            ]);

            foreach ($lessons as $index => $slug) {
                Post::withoutGlobalScope(StandalonePostScope::class)->create([
                    'user_id' => $user->id,
                    'persona_id' => $persona->id,
                    'universe_id' => $persona->universe_id,
                    'course_module_id' => $module->id,
                    'position' => $index,
                    'slug' => "lesson-{$slug}",
                    'title' => 'Lesson '.ucfirst($slug),
                    'body' => '<p>A paragraph worth marking in a lesson.</p>',
                    'status' => $course->status,
                    'reading_time' => 3,
                    'published_at' => $course->published_at,
                ]);
            }
        }

        return $course;
    }

    public function test_the_index_and_authoring_pages_render(): void
    {
        [$user, $persona] = $this->teacher();
        $this->course($user, $persona);

        $this->get('/courses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('courses/index')->where('courses.total', 1));

        $this->actingAs($user)->get('/courses/new')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('courses/create')->has('personas')->has('levels'));

        $this->actingAs($user)->get('/courses/a-real-course/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('courses/edit')
                ->has('modules', 2)
                ->has('modules.0.lessons', 2));
    }

    public function test_a_published_course_is_readable_by_anyone(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);

        $this->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('courses/show')
                ->where('course.lesson_count', 4)
                ->has('outline', 2)
                ->has('outline.0.lessons', 2));
    }

    public function test_a_draft_course_is_visible_only_to_its_author(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona, status: 'draft');

        $this->get("/courses/{$course->slug}")->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/courses/{$course->slug}")->assertForbidden();
        $this->actingAs($user)->get("/courses/{$course->slug}")->assertOk();
    }

    public function test_a_lesson_renders_with_its_contents_panel_and_neighbours(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);

        $this->get("/courses/{$course->slug}/lesson-second")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('courses/lesson')
                ->where('lesson.number', 2)
                ->where('lesson.total', 4)
                ->where('previous.title', 'Lesson First')
                ->where('next.title', 'Lesson Third')
                // The panel marks where the reader is.
                ->where('outline.0.lessons.1.current', true));
    }

    public function test_the_first_and_last_lessons_have_no_neighbour_beyond_them(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);

        $this->get("/courses/{$course->slug}/lesson-first")
            ->assertInertia(fn ($page) => $page->where('previous', null)->where('next.title', 'Lesson Second'));

        $this->get("/courses/{$course->slug}/lesson-fourth")
            ->assertInertia(fn ($page) => $page->where('next', null));
    }

    public function test_an_unknown_lesson_is_a_404(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);

        $this->get("/courses/{$course->slug}/no-such-lesson")->assertNotFound();
    }

    /**
     * The load-bearing consequence of lessons being Posts: they must not leak
     * into anything that lists standalone writing.
     */
    public function test_lessons_never_appear_where_standalone_writing_is_listed(): void
    {
        [$user, $persona] = $this->teacher();
        $this->course($user, $persona);

        Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-standalone-piece',
            'title' => 'A standalone piece',
            'body' => '<p>Not a lesson.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/posts')->assertInertia(fn ($page) => $page->where('posts.total', 1));
        $this->get("/@{$persona->handle}")->assertInertia(fn ($page) => $page->where('posts.total', 1));

        $rss = $this->get('/feed.xml')->getContent();
        $this->assertStringNotContainsString('Lesson First', $rss);
        $this->assertStringContainsString('A standalone piece', $rss);

        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringNotContainsString('lesson-first', $sitemap);
    }

    public function test_a_lesson_is_not_reachable_as_a_standalone_post(): void
    {
        [$user, $persona] = $this->teacher();
        $this->course($user, $persona);

        $this->get('/posts/lesson-first')->assertNotFound();
    }

    public function test_a_lesson_can_be_marked_like_any_other_writing(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        $this->actingAs($reader)->post('/posts/lesson-first/highlights', [
            'block_index' => 0,
            'start_offset' => 0,
            'end_offset' => 11,
            'quote' => 'A paragraph',
        ])->assertSessionHasNoErrors();

        $this->get("/courses/{$course->slug}/lesson-first")
            ->assertInertia(fn ($page) => $page->where('passages.0.quote', 'A paragraph'));
    }

    public function test_a_lesson_can_be_answered_and_saved_like_any_other_writing(): void
    {
        [$user, $persona] = $this->teacher();
        $this->course($user, $persona);
        $reader = User::factory()->create();

        $this->actingAs($reader)
            ->post('/posts/lesson-first/responses', ['body' => 'This explanation finally landed.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($reader)
            ->post('/posts/lesson-first/bookmark', ['kind' => 'saved'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('responses', 1);
        $this->assertDatabaseCount('bookmarks', 1);
    }

    public function test_progress_toggles_and_drives_the_resume_link(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        // Nothing done: resume points at the first lesson.
        $this->actingAs($reader)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page->where('resume.title', 'Lesson First'));

        $this->actingAs($reader)->post("/courses/{$course->slug}/lesson-first/progress")->assertRedirect();

        $this->actingAs($reader)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('course.completed_count', 1)
                ->where('resume.title', 'Lesson Second')
                ->where('outline.0.lessons.0.completed', true));

        // Toggling back off restores the earlier position.
        $this->actingAs($reader)->post("/courses/{$course->slug}/lesson-first/progress");

        $this->actingAs($reader)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page->where('course.completed_count', 0));
    }

    public function test_a_lesson_reports_what_is_left_to_read(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        // Four lessons at 3 minutes each. Standing on lesson one, the three
        // ahead are what remains — the lesson you are reading does not count.
        $this->actingAs($reader)->get("/courses/{$course->slug}/lesson-first")
            ->assertInertia(fn ($page) => $page
                ->where('progress.total', 4)
                ->where('progress.done', 0)
                ->where('progress.remaining_minutes', 9)
                ->where('progress.finishes_course', false));
    }

    public function test_the_last_unfinished_lesson_announces_that_it_finishes_the_course(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        foreach (['lesson-first', 'lesson-second', 'lesson-third'] as $slug) {
            $this->actingAs($reader)->post("/courses/{$course->slug}/{$slug}/progress");
        }

        $this->actingAs($reader)->get("/courses/{$course->slug}/lesson-fourth")
            ->assertInertia(fn ($page) => $page->where('progress.finishes_course', true));

        // And a lesson already done must not claim to finish anything.
        $this->actingAs($reader)->get("/courses/{$course->slug}/lesson-first")
            ->assertInertia(fn ($page) => $page->where('progress.finishes_course', false));
    }

    public function test_a_finished_course_says_so(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        $this->actingAs($reader)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page->where('course.is_complete', false));

        foreach (['lesson-first', 'lesson-second', 'lesson-third', 'lesson-fourth'] as $slug) {
            $this->actingAs($reader)->post("/courses/{$course->slug}/{$slug}/progress");
        }

        $this->actingAs($reader)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('course.is_complete', true)
                ->where('course.remaining_minutes', 0));
    }

    public function test_the_outline_carries_progress_for_each_module(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/courses/{$course->slug}/lesson-first/progress");

        $this->actingAs($reader)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page
                // A long course should read as a set of finishable parts.
                ->where('outline.0.done', 1)
                ->where('outline.0.total', 2)
                ->where('outline.1.done', 0)
                ->where('outline.1.total', 2));
    }

    public function test_progress_is_private_to_each_reader(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($reader)->post("/courses/{$course->slug}/lesson-first/progress");

        $this->actingAs($other)->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page->where('course.completed_count', 0));
    }

    public function test_publishing_a_course_publishes_its_lessons(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona, status: 'draft');

        $this->actingAs($user)->post("/courses/{$course->slug}", [
            'title' => $course->title,
            'persona_id' => $persona->id,
            'level' => 'beginner',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $lessons = Post::withoutGlobalScope(StandalonePostScope::class)->whereNotNull('course_module_id')->get();

        $this->assertCount(4, $lessons);
        $this->assertTrue($lessons->every(fn (Post $lesson) => $lesson->status === 'published'));
        $this->assertTrue($lessons->every(fn (Post $lesson) => $lesson->published_at !== null));
    }

    public function test_unpublishing_a_course_hides_its_lessons_again(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);

        $this->actingAs($user)->post("/courses/{$course->slug}", [
            'title' => $course->title,
            'persona_id' => $persona->id,
            'level' => 'beginner',
            'status' => 'draft',
        ]);

        // Checked as somebody else: the author can always see their own draft,
        // so asserting against their session would prove nothing.
        $this->actingAs(User::factory()->create())
            ->get("/courses/{$course->slug}/lesson-first")
            ->assertForbidden();

        $lessons = Post::withoutGlobalScope(StandalonePostScope::class)->whereNotNull('course_module_id')->get();
        $this->assertTrue($lessons->every(fn (Post $lesson) => $lesson->status === 'draft'));
        $this->assertTrue($lessons->every(fn (Post $lesson) => $lesson->published_at === null));
    }

    public function test_only_the_author_can_edit_a_course(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/courses/{$course->slug}/edit")->assertForbidden();
        $this->actingAs($stranger)->delete("/courses/{$course->slug}")->assertForbidden();
    }

    public function test_a_module_from_another_course_cannot_be_edited_through_this_one(): void
    {
        [$user, $persona] = $this->teacher();
        $mine = $this->course($user, $persona);

        $otherCourse = Course::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'another-course',
            'title' => 'Another course',
            'level' => 'beginner',
            'status' => 'draft',
        ]);
        $foreign = CourseModule::create(['course_id' => $otherCourse->id, 'title' => 'Foreign', 'position' => 0]);

        $this->actingAs($user)
            ->put("/courses/{$mine->slug}/modules/{$foreign->id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Foreign', $foreign->fresh()->title);
    }

    public function test_reordering_rewrites_positions(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $modules = $course->modules()->get();

        $this->actingAs($user)->post("/courses/{$course->slug}/reorder", [
            'kind' => 'modules',
            'ids' => [$modules[1]->id, $modules[0]->id],
        ])->assertRedirect();

        $this->assertSame(0, $modules[1]->fresh()->position);
        $this->assertSame(1, $modules[0]->fresh()->position);

        // The reading order — and therefore previous/next — follows.
        $this->get("/courses/{$course->slug}/lesson-third")
            ->assertInertia(fn ($page) => $page->where('lesson.number', 1)->where('previous', null));
    }

    public function test_deleting_a_course_removes_its_lessons_and_progress(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/courses/{$course->slug}/lesson-first/progress");
        $this->assertDatabaseCount('lesson_progress', 1);

        $this->actingAs($user)->delete("/courses/{$course->slug}")->assertRedirect();

        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('course_modules', 0);
        $this->assertSame(0, Post::withoutGlobalScope(StandalonePostScope::class)->count());
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_an_unverified_author_cannot_publish_a_course(): void
    {
        $user = User::factory()->unverified()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'unconfirmed',
            'display_name' => 'Unconfirmed',
        ]);

        $this->actingAs($user)->post('/courses', [
            'title' => 'Straight out',
            'persona_id' => $persona->id,
            'level' => 'beginner',
            'status' => 'published',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_a_course_carries_course_schema_for_search(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);

        $this->get("/courses/{$course->slug}")
            ->assertInertia(fn ($page) => $page->where('seo.jsonLd.@type', 'Course'));

        $this->get("/courses/{$course->slug}/lesson-first")
            ->assertInertia(fn ($page) => $page
                ->where('seo.jsonLd.@type', 'LearningResource')
                // Each lesson is its own indexable page, not a duplicate of the
                // syllabus.
                ->where('seo.canonical', route('courses.lesson', [$course, 'lesson-first'])));
    }

    public function test_progress_rows_survive_only_for_their_lesson(): void
    {
        [$user, $persona] = $this->teacher();
        $course = $this->course($user, $persona);
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/courses/{$course->slug}/lesson-first/progress");

        $row = LessonProgress::firstOrFail();
        $this->assertSame($course->id, $row->course_id);
        $this->assertNotNull($row->completed_at);
    }
}
