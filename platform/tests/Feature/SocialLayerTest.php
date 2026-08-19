<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{0: User, 1: Persona} */
    private function writer(string $handle, string $universe = 'cosmos'): array
    {
        $user = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', $universe)->value('id'),
            'handle' => $handle,
            'display_name' => ucfirst($handle),
        ]);

        return [$user, $persona];
    }

    private function publish(User $user, Persona $persona, string $slug): Post
    {
        return Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'body' => '<p>A paragraph worth marking.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_profile_is_public_and_lists_published_work(): void
    {
        [$author, $persona] = $this->writer('longlight');
        $this->publish($author, $persona, 'the-long-light');

        Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'not-ready',
            'title' => 'Not ready',
            'body' => '<p>Draft.</p>',
            'status' => 'draft',
        ]);

        $this->get('/@longlight')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('profiles/show')
                ->where('persona.handle', 'longlight')
                // Drafts are the author's business, not the public's.
                ->where('posts.total', 1));
    }

    public function test_a_profile_carries_its_own_seo_metadata(): void
    {
        [$author, $persona] = $this->writer('longlight');
        $persona->update(['bio' => 'Writes about distance.']);
        $this->publish($author, $persona, 'the-long-light');

        $this->get('/@longlight')
            ->assertInertia(fn ($page) => $page
                ->where('seo.type', 'profile')
                ->where('seo.description', 'Writes about distance.')
                ->where('seo.jsonLd.@type', 'ProfilePage'));
    }

    public function test_an_unknown_handle_is_a_404(): void
    {
        $this->get('/@nobody-here')->assertNotFound();
    }

    public function test_the_following_feed_shows_only_what_you_follow(): void
    {
        [$authorA, $personaA] = $this->writer('followed');
        [$authorB, $personaB] = $this->writer('ignored');

        $this->publish($authorA, $personaA, 'you-follow-this');
        $this->publish($authorB, $personaB, 'you-do-not');

        $reader = User::factory()->create();
        $personaA->followers()->create(['user_id' => $reader->id]);

        $this->actingAs($reader)->get('/following')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('following')
                ->where('posts.total', 1)
                ->where('posts.data.0.slug', 'you-follow-this'));
    }

    public function test_following_a_universe_also_composes_the_feed(): void
    {
        [$author, $persona] = $this->writer('naturalist', 'nature');
        $this->publish($author, $persona, 'in-the-loam');

        $reader = User::factory()->create();
        Universe::where('slug', 'nature')->firstOrFail()
            ->followers()->create(['user_id' => $reader->id]);

        $this->actingAs($reader)->get('/following')
            ->assertInertia(fn ($page) => $page->where('posts.total', 1));
    }

    public function test_following_nothing_yields_an_empty_feed_not_everything(): void
    {
        [$author, $persona] = $this->writer('someone');
        $this->publish($author, $persona, 'unfollowed-work');

        $reader = User::factory()->create();

        $this->actingAs($reader)->get('/following')
            ->assertInertia(fn ($page) => $page->where('posts.total', 0));
    }

    public function test_marking_a_passage_alerts_the_author(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'marked-piece');
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0,
            'start_offset' => 0,
            'end_offset' => 9,
            'quote' => 'A paragra',
        ]);

        $alert = Alert::where('user_id', $author->id)->where('type', Alert::MARK)->first();
        $this->assertNotNull($alert);
        $this->assertSame(1, $alert->count);
        $this->assertSame($post->id, $alert->post_id);
    }

    public function test_repeat_marks_on_one_piece_collapse_into_a_count(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'marked-piece');

        foreach (range(1, 3) as $offset) {
            $reader = User::factory()->create();
            $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
                'block_index' => 0,
                'start_offset' => $offset,
                'end_offset' => $offset + 5,
                'quote' => 'parag',
            ]);
        }

        $alerts = Alert::where('user_id', $author->id)->where('type', Alert::MARK)->get();

        $this->assertCount(1, $alerts);
        $this->assertSame(3, $alerts->first()->count);
    }

    public function test_re_posting_the_same_mark_does_not_inflate_the_count(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'marked-piece');
        $reader = User::factory()->create();

        $payload = ['block_index' => 0, 'start_offset' => 0, 'end_offset' => 9, 'quote' => 'A paragra'];

        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", $payload);
        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", $payload);

        $this->assertSame(1, Alert::where('user_id', $author->id)->where('type', Alert::MARK)->value('count'));
    }

    public function test_marking_your_own_work_alerts_nobody(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'my-own-piece');

        $this->actingAs($author)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0,
            'start_offset' => 0,
            'end_offset' => 9,
            'quote' => 'A paragra',
        ]);

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_a_response_alerts_the_author(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'answered-piece');
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/responses", [
            'body' => 'This changed how I read the second paragraph.',
        ]);

        $alert = Alert::where('user_id', $author->id)->where('type', Alert::RESPONSE)->firstOrFail();
        $this->assertStringContainsString('changed how I read', $alert->preview);
    }

    public function test_a_letter_alerts_its_recipient_only(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'letter-piece');
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/letters", [
            'body' => 'I have carried this around all week.',
        ]);

        $this->assertSame(1, Alert::where('user_id', $author->id)->where('type', Alert::LETTER)->count());
        $this->assertSame(0, Alert::where('user_id', $reader->id)->count());
    }

    public function test_following_a_persona_alerts_its_writer_but_unfollowing_does_not(): void
    {
        [$author, $persona] = $this->writer('followed');
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/personas/{$persona->handle}/follow");
        $this->assertSame(1, Alert::where('user_id', $author->id)->where('type', Alert::FOLLOW)->count());

        $this->actingAs($reader)->post("/personas/{$persona->handle}/follow");
        $this->assertSame(1, Alert::where('user_id', $author->id)->where('type', Alert::FOLLOW)->count());
    }

    public function test_the_unread_count_is_shared_and_clearable(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'a-piece');
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/responses", ['body' => 'A real answer here.']);

        $this->actingAs($author)->get('/activity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('unreadAlerts', 1));

        $this->actingAs($author)->post('/activity/read')->assertRedirect();

        $this->actingAs($author)->get('/activity')
            ->assertInertia(fn ($page) => $page->where('unreadAlerts', 0));
    }

    public function test_opening_the_activity_page_does_not_silently_clear_it(): void
    {
        [$author, $persona] = $this->writer('author');
        $post = $this->publish($author, $persona, 'a-piece');
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/responses", ['body' => 'A real answer here.']);

        $this->actingAs($author)->get('/activity')
            ->assertInertia(fn ($page) => $page->where('alerts.0.unread', true));
    }

    public function test_the_activity_page_needs_a_session(): void
    {
        $this->get('/activity')->assertRedirect('/login');
        $this->get('/following')->assertRedirect('/login');
    }
}
