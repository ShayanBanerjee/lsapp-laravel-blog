<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{Course, Post} a published course and its first lesson */
    private function course(int $lessons = 3, string $status = 'published'): array
    {
        $author = User::factory()->create();
        $universe = Universe::where('slug', 'cosmos')->firstOrFail();

        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => $universe->id,
            'handle' => 'teacher'.$author->id,
            'display_name' => 'Teacher',
        ]);

        $course = Course::create([
            'slug' => 'a-course-'.$author->id,
            'title' => 'A Course',
            'subtitle' => 'Learning things',
            'description' => 'A description.',
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $universe->id,
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDay() : null,
        ]);

        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module One', 'sort_order' => 0]);

        $first = null;

        for ($i = 0; $i < $lessons; $i++) {
            $lesson = Post::create([
                'user_id' => $author->id,
                'persona_id' => $persona->id,
                'universe_id' => $universe->id,
                'slug' => "lesson-{$author->id}-{$i}",
                'title' => "Lesson {$i}",
                'body' => '<p>The first paragraph.</p>',
                'status' => 'published',
                'published_at' => now()->subDay(),
                'kind' => 'lesson',
                'course_module_id' => $module->id,
                'sort_order' => $i,
            ]);

            $first ??= $lesson;
        }

        return [$course, $first];
    }

    public function test_a_guest_can_read_a_published_course_and_its_lessons(): void
    {
        [$course, $lesson] = $this->course();

        $this->get('/learn')->assertOk();
        $this->get("/learn/{$course->slug}")->assertOk();
        $this->get("/learn/{$course->slug}/{$lesson->slug}")->assertOk();
    }

    public function test_a_draft_course_is_hidden_from_everyone_but_its_author(): void
    {
        [$course, $lesson] = $this->course(status: 'draft');

        $this->get("/learn/{$course->slug}")->assertNotFound();
        $this->get("/learn/{$course->slug}/{$lesson->slug}")->assertNotFound();

        $this->actingAs($course->user)->get("/learn/{$course->slug}")->assertOk();
    }

    /**
     * Cross-object references have to be scoped, or a crafted URL pairs any
     * course with any lesson.
     */
    public function test_a_lesson_cannot_be_read_through_the_wrong_course(): void
    {
        [$courseA] = $this->course();
        [, $lessonB] = $this->course();

        $this->get("/learn/{$courseA->slug}/{$lessonB->slug}")->assertNotFound();
    }

    /** Lessons belong in their course, not stranded in the general feed. */
    public function test_lessons_never_appear_in_public_listings(): void
    {
        [, $lesson] = $this->course();

        $this->get('/posts')->assertOk()->assertDontSee($lesson->title);
        $this->get('/')->assertOk()->assertDontSee($lesson->title);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($lesson->slug);
        $this->get('/feed.xml')->assertOk()->assertDontSee($lesson->title);
    }

    public function test_a_lesson_url_redirects_into_its_course(): void
    {
        [$course, $lesson] = $this->course();

        $this->get("/posts/{$lesson->slug}")
            ->assertRedirect("/learn/{$course->slug}/{$lesson->slug}");
    }

    /* -------------------- progress -------------------- */

    public function test_a_reader_can_mark_a_lesson_complete_and_undo_it(): void
    {
        [$course, $lesson] = $this->course();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/learn/{$course->slug}/{$lesson->slug}/progress")->assertRedirect();
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $reader->id, 'post_id' => $lesson->id]);
        $this->assertNotNull(DB::table('lesson_progress')->where('post_id', $lesson->id)->value('completed_at'));

        $this->actingAs($reader)->post("/learn/{$course->slug}/{$lesson->slug}/progress")->assertRedirect();
        $this->assertNull(DB::table('lesson_progress')->where('post_id', $lesson->id)->value('completed_at'));
    }

    public function test_progress_is_private_to_the_reader(): void
    {
        [$course, $lesson] = $this->course();
        $reader = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($reader)->post("/learn/{$course->slug}/{$lesson->slug}/progress");

        $this->actingAs($other)
            ->get("/learn/{$course->slug}/{$lesson->slug}")
            ->assertInertia(fn ($page) => $page->where('lesson.completed', false));
    }

    public function test_a_guest_cannot_record_progress(): void
    {
        [$course, $lesson] = $this->course();

        $this->post("/learn/{$course->slug}/{$lesson->slug}/progress")->assertRedirect('/login');
    }

    /* -------------------- the community layer, unchanged -------------------- */

    /** A lesson is a post, so marking a passage works with no new endpoint. */
    public function test_a_lesson_passage_can_be_marked(): void
    {
        [, $lesson] = $this->course();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$lesson->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first',
        ])->assertRedirect();

        $this->assertDatabaseHas('highlights', ['post_id' => $lesson->id, 'quote' => 'first']);
    }
}
