<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Highlight;
use App\Models\Letter;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Response;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\CircleSeeder;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private function makePost(?User $author = null): Post
    {
        $author ??= User::factory()->create();
        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'writer'.$author->id,
            'display_name' => 'Writer',
        ]);

        return Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-piece-'.$author->id,
            'title' => 'A Piece',
            'body' => '<p>The first paragraph.</p><p>The second paragraph.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /* -------------------- highlights -------------------- */

    public function test_a_reader_can_mark_a_passage(): void
    {
        $post = $this->makePost();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first',
        ])->assertRedirect();

        $this->assertDatabaseHas('highlights', [
            'post_id' => $post->id, 'user_id' => $reader->id, 'quote' => 'first',
        ]);
    }

    /** Double-submitting the same mark must be idempotent, not a 500. */
    public function test_marking_the_same_passage_twice_is_idempotent(): void
    {
        $post = $this->makePost();
        $reader = User::factory()->create();
        $payload = ['block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first'];

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", $payload);
        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", $payload)->assertRedirect();

        $this->assertSame(1, Highlight::where('post_id', $post->id)->count());
    }

    public function test_marks_from_different_readers_collapse_into_one_counted_passage(): void
    {
        $post = $this->makePost();
        $payload = ['block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first'];

        foreach (range(1, 3) as $ignored) {
            $this->actingAs(User::factory()->create())->post("/posts/{$post->slug}/highlights", $payload);
        }

        $passages = $post->markedPassages();

        $this->assertCount(1, $passages);
        $this->assertSame(3, (int) $passages->first()->marks);
    }

    public function test_a_reader_cannot_delete_someone_elses_mark(): void
    {
        $post = $this->makePost();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $highlight = Highlight::create([
            'post_id' => $post->id, 'user_id' => $owner->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 3, 'quote' => 'The',
        ]);

        $this->actingAs($stranger)->delete("/highlights/{$highlight->id}")->assertForbidden();
        $this->assertDatabaseHas('highlights', ['id' => $highlight->id]);

        $this->actingAs($owner)->delete("/highlights/{$highlight->id}")->assertRedirect();
        $this->assertDatabaseMissing('highlights', ['id' => $highlight->id]);
    }

    public function test_a_draft_cannot_be_marked_by_a_stranger(): void
    {
        $post = $this->makePost();
        $post->update(['status' => 'draft', 'published_at' => null]);

        $this->actingAs(User::factory()->create())->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 3, 'quote' => 'The',
        ])->assertForbidden();
    }

    public function test_quotes_are_stored_as_plain_text(): void
    {
        $post = $this->makePost();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 5,
            'quote' => '<img src=x onerror=alert(1)>hello',
        ]);

        $quote = Highlight::where('post_id', $post->id)->value('quote');

        $this->assertStringNotContainsString('<', $quote);
        $this->assertStringNotContainsString('onerror', $quote);
    }

    /* -------------------- responses -------------------- */

    public function test_a_response_can_be_anchored_to_a_highlight(): void
    {
        $post = $this->makePost();
        $reader = User::factory()->create();

        $highlight = Highlight::create([
            'post_id' => $post->id, 'user_id' => $reader->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 3, 'quote' => 'The',
        ]);

        $this->actingAs($reader)->post("/posts/{$post->slug}/responses", [
            'body' => 'This line in particular.',
            'highlight_id' => $highlight->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('responses', ['highlight_id' => $highlight->id, 'post_id' => $post->id]);
    }

    /**
     * A crafted highlight_id from a different post must not be accepted, or a
     * response could be anchored to a passage in someone else's piece.
     */
    public function test_a_response_cannot_borrow_a_highlight_from_another_post(): void
    {
        $postA = $this->makePost();
        $postB = $this->makePost(User::factory()->create());
        $reader = User::factory()->create();

        $foreign = Highlight::create([
            'post_id' => $postB->id, 'user_id' => $reader->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 3, 'quote' => 'The',
        ]);

        $this->actingAs($reader)->post("/posts/{$postA->slug}/responses", [
            'body' => 'Anchored somewhere it should not be.',
            'highlight_id' => $foreign->id,
        ])->assertSessionHasErrors('highlight_id');

        $this->assertDatabaseMissing('responses', ['highlight_id' => $foreign->id]);
    }

    public function test_response_bodies_are_stored_as_plain_text(): void
    {
        $post = $this->makePost();

        $this->actingAs(User::factory()->create())->post("/posts/{$post->slug}/responses", [
            'body' => 'Nice <script>alert(1)</script> piece',
        ]);

        $body = Response::where('post_id', $post->id)->value('body');

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)', $body);
    }

    /** A writer must be able to clear abuse from under their own piece. */
    public function test_the_post_author_can_delete_any_response_on_their_piece(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);
        $commenter = User::factory()->create();

        $response = Response::create([
            'post_id' => $post->id, 'user_id' => $commenter->id, 'body' => 'Something unpleasant.',
        ]);

        $this->actingAs($author)->delete("/responses/{$response->id}")->assertRedirect();
        $this->assertDatabaseMissing('responses', ['id' => $response->id]);
    }

    public function test_an_unrelated_user_cannot_delete_a_response(): void
    {
        $post = $this->makePost();
        $response = Response::create([
            'post_id' => $post->id, 'user_id' => User::factory()->create()->id, 'body' => 'Mine.',
        ]);

        $this->actingAs(User::factory()->create())->delete("/responses/{$response->id}")->assertForbidden();
        $this->assertDatabaseHas('responses', ['id' => $response->id]);
    }

    /* -------------------- letters -------------------- */

    public function test_a_reader_can_send_the_writer_a_private_letter(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/letters", [
            'body' => 'This got me through a bad week.',
        ])->assertRedirect();

        $this->assertDatabaseHas('letters', [
            'post_id' => $post->id, 'from_user_id' => $reader->id, 'to_user_id' => $author->id,
        ]);
    }

    public function test_letters_are_only_visible_to_their_recipient(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);
        $reader = User::factory()->create();
        $nosy = User::factory()->create();

        Letter::create([
            'post_id' => $post->id, 'from_user_id' => $reader->id,
            'to_user_id' => $author->id, 'body' => 'A private thing.',
        ]);

        $this->actingAs($author)->get('/letters')->assertOk()->assertSee('A private thing.');
        $this->actingAs($nosy)->get('/letters')->assertOk()->assertDontSee('A private thing.');
    }

    public function test_opening_the_letters_page_marks_them_read(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);

        Letter::create([
            'post_id' => $post->id, 'from_user_id' => User::factory()->create()->id,
            'to_user_id' => $author->id, 'body' => 'Unread for now.',
        ]);

        $this->assertSame(1, $author->lettersReceived()->whereNull('read_at')->count());

        $this->actingAs($author)->get('/letters')->assertOk();

        $this->assertSame(0, $author->fresh()->lettersReceived()->whereNull('read_at')->count());
    }

    /* -------------------- circles -------------------- */

    public function test_joining_a_circle_is_idempotent(): void
    {
        $this->seed(CircleSeeder::class);
        $circle = Circle::first();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/circles/{$circle->slug}/membership");
        $this->assertSame(1, $circle->members()->count());

        // Toggling off, then on again, must not leave duplicate rows.
        $this->actingAs($user)->post("/circles/{$circle->slug}/membership");
        $this->actingAs($user)->post("/circles/{$circle->slug}/membership");

        $this->assertSame(1, $circle->members()->count());
    }
}
