<?php

namespace Tests\Feature;

use App\Models\Highlight;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Models\WriterNotification;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriterSocialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{User, Persona, Post} */
    private function writer(string $handle = 'longlight'): array
    {
        $user = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => $handle,
            'display_name' => 'Long Light',
            'bio' => 'Writes about distance.',
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-piece-'.$handle,
            'title' => 'A Piece',
            'body' => '<p>The first paragraph.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        return [$user, $persona, $post];
    }

    /* -------------------- profiles -------------------- */

    public function test_a_writer_profile_is_public(): void
    {
        [, $persona] = $this->writer();

        $this->get("/writers/{$persona->handle}")
            ->assertOk()
            ->assertSee('Long Light')
            ->assertSee('Writes about distance.');
    }

    /**
     * STRATEGY.md excludes public follower counts outright — they turn writing
     * into standing. This asserts the absence, because absences regress.
     */
    public function test_a_profile_never_shows_a_follower_count(): void
    {
        [, $persona] = $this->writer();

        $reader = User::factory()->create();
        $this->actingAs($reader)->post("/personas/{$persona->handle}/follow");

        $this->get("/writers/{$persona->handle}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('writer.followers_count')
                ->missing('writer.follower_count')
                ->has('writer.published_posts_count'));
    }

    public function test_a_profile_shows_which_sentences_landed(): void
    {
        [, $persona, $post] = $this->writer();

        Highlight::create([
            'post_id' => $post->id, 'user_id' => User::factory()->create()->id,
            'block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first',
        ]);

        $this->get("/writers/{$persona->handle}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('marked.0.quote', 'first'));
    }

    /* -------------------- following feed -------------------- */

    public function test_the_feed_shows_pieces_from_followed_writers(): void
    {
        [, $persona, $post] = $this->writer();
        [, , $unfollowed] = $this->writer('otherwriter');

        $reader = User::factory()->create();
        $this->actingAs($reader)->post("/personas/{$persona->handle}/follow");

        $this->actingAs($reader)->get('/following')
            ->assertOk()
            ->assertSee($post->title)
            ->assertInertia(fn ($page) => $page->has('posts.data', 1));

        $this->assertNotSame($post->id, $unfollowed->id);
    }

    public function test_the_feed_is_empty_when_following_nobody(): void
    {
        $this->writer();

        $this->actingAs(User::factory()->create())->get('/following')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts.data', 0));
    }

    /** Paginated and finite — not the mechanic readers came here to escape. */
    public function test_the_feed_paginates_rather_than_scrolling_forever(): void
    {
        [$author, $persona] = $this->writer();

        for ($i = 0; $i < 15; $i++) {
            Post::create([
                'user_id' => $author->id,
                'persona_id' => $persona->id,
                'universe_id' => $persona->universe_id,
                'slug' => "extra-{$i}",
                'title' => "Extra {$i}",
                'body' => '<p>Body.</p>',
                'status' => 'published',
                'published_at' => now()->subHours($i),
            ]);
        }

        $reader = User::factory()->create();
        $this->actingAs($reader)->post("/personas/{$persona->handle}/follow");

        $this->actingAs($reader)->get('/following')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts.data', 9)->has('posts.links'));
    }

    /* -------------------- notifications -------------------- */

    public function test_marking_a_passage_tells_the_writer_which_sentence(): void
    {
        [$author, , $post] = $this->writer();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first',
        ]);

        $this->assertDatabaseHas('writer_notifications', [
            'user_id' => $author->id, 'type' => 'mark', 'quote' => 'first',
        ]);
    }

    /** Marking your own work is not feedback. */
    public function test_marking_your_own_work_notifies_nobody(): void
    {
        [$author, , $post] = $this->writer();

        $this->actingAs($author)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first',
        ]);

        $this->assertDatabaseCount('writer_notifications', 0);
    }

    public function test_re_marking_the_same_passage_does_not_notify_twice(): void
    {
        [, , $post] = $this->writer();
        $reader = User::factory()->create();

        $anchor = ['block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first'];

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", $anchor);
        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", $anchor);

        $this->assertDatabaseCount('writer_notifications', 1);
    }

    public function test_notifications_are_private_to_their_writer(): void
    {
        [$author, , $post] = $this->writer();

        WriterNotification::create([
            'user_id' => $author->id, 'type' => 'mark', 'post_id' => $post->id, 'quote' => 'a private signal',
        ]);

        $this->actingAs($author)->get('/notifications')->assertOk()->assertSee('a private signal');
        $this->actingAs(User::factory()->create())->get('/notifications')->assertOk()->assertDontSee('a private signal');
    }

    public function test_opening_the_page_marks_them_read(): void
    {
        [$author, , $post] = $this->writer();

        WriterNotification::create(['user_id' => $author->id, 'type' => 'mark', 'post_id' => $post->id, 'quote' => 'q']);

        $this->assertSame(1, WriterNotification::where('user_id', $author->id)->whereNull('read_at')->count());

        $this->actingAs($author)->get('/notifications')->assertOk();

        $this->assertSame(0, WriterNotification::where('user_id', $author->id)->whereNull('read_at')->count());
    }

    /** The badge is a count; the notification itself always carries the passage. */
    public function test_the_unread_count_is_shared_for_the_badge(): void
    {
        [$author, , $post] = $this->writer();

        WriterNotification::create(['user_id' => $author->id, 'type' => 'mark', 'post_id' => $post->id, 'quote' => 'q']);

        $this->actingAs($author)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
    }
}
