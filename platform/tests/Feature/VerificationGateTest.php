<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification gates publishing and nothing else. Both halves of that sentence
 * are asserted here — an unconfirmed account must still be able to draft, or
 * the gate becomes a wall nobody walks back through.
 */
class VerificationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{0: User, 1: Persona} */
    private function writer(bool $verified): array
    {
        $user = $verified
            ? User::factory()->create()
            : User::factory()->unverified()->create();

        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'writer'.$user->id,
            'display_name' => 'Writer',
        ]);

        return [$user, $persona];
    }

    public function test_an_unverified_writer_cannot_publish(): void
    {
        [$user, $persona] = $this->writer(verified: false);

        $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Straight to the world',
                'body' => '<p>Unconfirmed.</p>',
                'persona_id' => $persona->id,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_an_unverified_writer_can_still_draft(): void
    {
        [$user, $persona] = $this->writer(verified: false);

        $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Kept back',
                'body' => '<p>Still mine.</p>',
                'persona_id' => $persona->id,
                'status' => 'draft',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', ['title' => 'Kept back', 'status' => 'draft']);
    }

    public function test_an_unverified_writer_cannot_publish_an_existing_draft(): void
    {
        [$user, $persona] = $this->writer(verified: false);

        $post = Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'kept-back',
            'title' => 'Kept back',
            'body' => '<p>Still mine.</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->put("/posts/{$post->slug}", [
                'title' => 'Kept back',
                'body' => '<p>Still mine.</p>',
                'persona_id' => $persona->id,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_a_verified_writer_publishes_normally(): void
    {
        [$user, $persona] = $this->writer(verified: true);

        $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Out in the open',
                'body' => '<p>Confirmed.</p>',
                'persona_id' => $persona->id,
                'status' => 'published',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', ['title' => 'Out in the open', 'status' => 'published']);
    }

    public function test_the_editor_is_told_whether_publishing_is_available(): void
    {
        [$unverified] = $this->writer(verified: false);
        [$verified] = $this->writer(verified: true);

        $this->actingAs($unverified)->get('/write')
            ->assertInertia(fn ($page) => $page->where('canPublish', false));

        $this->actingAs($verified)->get('/write')
            ->assertInertia(fn ($page) => $page->where('canPublish', true));
    }

    public function test_pruning_removes_only_inert_unconfirmed_accounts(): void
    {
        $stale = User::factory()->unverified()->create(['created_at' => now()->subDays(45)]);
        $recent = User::factory()->unverified()->create(['created_at' => now()->subDay()]);
        $confirmed = User::factory()->create(['created_at' => now()->subDays(45)]);

        // Unconfirmed and old, but they wrote something — that is a real person
        // with real work, and deleting it would be indefensible.
        [$productive, $persona] = $this->writer(verified: false);
        $productive->forceFill(['created_at' => now()->subDays(45)])->save();
        Post::create([
            'user_id' => $productive->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'draft-of-record',
            'title' => 'Draft of record',
            'body' => '<p>Mine.</p>',
            'status' => 'draft',
        ]);

        $this->artisan('inkfathom:prune-unverified')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $stale->id]);
        $this->assertDatabaseHas('users', ['id' => $recent->id]);
        $this->assertDatabaseHas('users', ['id' => $confirmed->id]);
        $this->assertDatabaseHas('users', ['id' => $productive->id]);
    }
}
