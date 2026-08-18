<?php

namespace Tests\Feature\Auth;

use App\Models\Persona;
use App\Models\Post;
use App\Models\SocialIdentity;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an unverified address may and may not do.
 *
 * The line is writing vs reading, not member vs stranger: an unverified
 * account keeps the whole reading side, because gating that would cost real
 * readers in order to inconvenience spammers who never read anything.
 */
class VerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private function makePost(User $author): Post
    {
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
            'body' => '<p>The first paragraph.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /* -------------------- the gate -------------------- */

    public function test_an_unverified_writer_cannot_reach_the_editor(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/write')
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_an_unverified_writer_cannot_publish(): void
    {
        $user = User::factory()->unverified()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'unverified',
            'display_name' => 'Writer',
        ]);

        $this->actingAs($user)->post('/posts', [
            'persona_id' => $persona->id,
            'title' => 'Spam',
            'body' => '<p>Buy things.</p>',
            'status' => 'published',
        ])->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_an_unverified_reader_cannot_send_prose_to_a_writer(): void
    {
        $post = $this->makePost(User::factory()->create());
        $reader = User::factory()->unverified()->create();

        $this->actingAs($reader)
            ->post("/posts/{$post->slug}/letters", ['body' => 'Buy things.'])
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->actingAs($reader)
            ->post("/posts/{$post->slug}/responses", ['body' => 'Buy things.'])
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseCount('letters', 0);
        $this->assertDatabaseCount('responses', 0);
    }

    /* -------------------- what stays open -------------------- */

    public function test_an_unverified_reader_keeps_the_whole_reading_side(): void
    {
        $post = $this->makePost(User::factory()->create());
        $reader = User::factory()->unverified()->create();

        $this->actingAs($reader)->get('/dashboard')->assertOk();
        $this->actingAs($reader)->get("/posts/{$post->slug}")->assertOk();
        $this->actingAs($reader)->get('/library')->assertOk();

        // Marking is the core loop and stays free of the gate.
        $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
            'block_index' => 0, 'start_offset' => 4, 'end_offset' => 9, 'quote' => 'first',
        ])->assertRedirect();

        $this->assertDatabaseCount('highlights', 1);
    }

    /**
     * Deleting your own work is never gated. An account that predates
     * enforcement would otherwise be unable to take its own posts down.
     */
    public function test_an_unverified_writer_can_still_delete_their_own_work(): void
    {
        $user = User::factory()->unverified()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)->delete("/posts/{$post->slug}")->assertRedirect();

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_verified_writer_passes_the_gate(): void
    {
        $this->actingAs(User::factory()->create())->get('/write')->assertOk();
    }

    /* -------------------- pruning -------------------- */

    public function test_pruning_removes_only_abandoned_signups(): void
    {
        $abandoned = User::factory()->unverified()->create(['created_at' => now()->subDays(60)]);
        $recent = User::factory()->unverified()->create(['created_at' => now()->subDays(3)]);
        $verified = User::factory()->create(['created_at' => now()->subDays(60)]);

        // Unverified and old, but has written something.
        $withWork = User::factory()->unverified()->create(['created_at' => now()->subDays(60)]);
        $this->makePost($withWork);

        /*
         * Unverified and old, but signed in through a provider that does not
         * assert a verified address. A real person who would never verify.
         */
        $social = User::factory()->unverified()->create(['created_at' => now()->subDays(60)]);
        SocialIdentity::create([
            'user_id' => $social->id,
            'provider' => 'facebook',
            'provider_id' => '12345',
            'email' => $social->email,
        ]);

        $this->artisan('users:prune-unverified')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $abandoned->id]);

        foreach ([$recent, $verified, $withWork, $social] as $kept) {
            $this->assertDatabaseHas('users', ['id' => $kept->id]);
        }
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $abandoned = User::factory()->unverified()->create(['created_at' => now()->subDays(60)]);

        $this->artisan('users:prune-unverified --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $abandoned->id]);
    }

    public function test_the_grace_period_is_configurable(): void
    {
        $user = User::factory()->unverified()->create(['created_at' => now()->subDays(10)]);

        $this->artisan('users:prune-unverified --days=30')->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->artisan('users:prune-unverified --days=7')->assertSuccessful();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
