<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\ThemeEntitlement;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The premium boundary. Theme tokens ship to the browser, so these tests exist
 * to prove the gate is server-side — a client-side gate would let anyone read
 * the paid palettes out of the JS bundle.
 */
class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    public function test_a_locked_universe_never_ships_its_real_tokens(): void
    {
        $abyss = Universe::where('slug', 'abyss')->firstOrFail();

        $response = $this->get('/universes/abyss');

        $response->assertOk();
        $payload = $response->viewData('page')['props']['universe'];

        $this->assertTrue($payload['locked']);
        $this->assertNotSame($abyss->theme['accent'], $payload['theme']['accent']);
        $this->assertNotSame($abyss->theme['bg'], $payload['theme']['bg']);

        // The three-colour preview is intentionally public — it is what the
        // card thumbnail is drawn from, and is not enough to rebuild the theme.
        $this->assertSame($abyss->theme['accent'], $payload['swatch']['accent']);
    }

    public function test_a_premium_user_receives_the_real_tokens(): void
    {
        $abyss = Universe::where('slug', 'abyss')->firstOrFail();
        $user = User::factory()->create(['is_premium' => true]);

        $payload = $this->actingAs($user)->get('/universes/abyss')->viewData('page')['props']['universe'];

        $this->assertFalse($payload['locked']);
        $this->assertSame($abyss->theme['accent'], $payload['theme']['accent']);
    }

    public function test_a_free_universe_ships_its_tokens_to_guests(): void
    {
        $nature = Universe::where('slug', 'nature')->firstOrFail();

        $payload = $this->get('/universes/nature')->viewData('page')['props']['universe'];

        $this->assertFalse($payload['locked']);
        $this->assertSame($nature->theme['accent'], $payload['theme']['accent']);
    }

    public function test_an_entitlement_row_unlocks_a_universe_without_a_subscription(): void
    {
        $desert = Universe::where('slug', 'desert')->firstOrFail();
        $user = User::factory()->create(['is_premium' => false]);

        $this->assertFalse($user->canAccessUniverse($desert));

        ThemeEntitlement::create([
            'user_id' => $user->id,
            'universe_id' => $desert->id,
            'source' => 'grant',
        ]);

        $this->assertTrue($user->fresh()->canAccessUniverse($desert));
    }

    public function test_an_expired_entitlement_does_not_unlock_a_universe(): void
    {
        $desert = Universe::where('slug', 'desert')->firstOrFail();
        $user = User::factory()->create(['is_premium' => false]);

        ThemeEntitlement::create([
            'user_id' => $user->id,
            'universe_id' => $desert->id,
            'source' => 'subscription',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->fresh()->canAccessUniverse($desert));
    }

    public function test_a_free_user_cannot_create_a_persona_in_a_premium_universe(): void
    {
        $user = User::factory()->create(['is_premium' => false]);

        $this->actingAs($user)->post('/personas', [
            'handle' => 'trespasser',
            'display_name' => 'Trespasser',
            'universe_id' => Universe::where('slug', 'jungle')->value('id'),
        ])->assertForbidden();

        $this->assertDatabaseMissing('personas', ['handle' => 'trespasser']);
    }

    public function test_a_free_user_is_held_to_the_persona_limit(): void
    {
        $user = User::factory()->create(['is_premium' => false]);
        $cosmos = Universe::where('slug', 'cosmos')->value('id');

        Persona::create([
            'user_id' => $user->id,
            'universe_id' => $cosmos,
            'handle' => 'first',
            'display_name' => 'First',
        ]);

        $this->assertFalse($user->fresh()->canCreateAnotherPersona());

        $this->actingAs($user->fresh())->post('/personas', [
            'handle' => 'second',
            'display_name' => 'Second',
            'universe_id' => $cosmos,
        ])->assertForbidden();
    }

    public function test_a_premium_user_has_no_persona_limit(): void
    {
        $user = User::factory()->create(['is_premium' => true]);
        $cosmos = Universe::where('slug', 'cosmos')->value('id');

        foreach (['one', 'two', 'three'] as $handle) {
            Persona::create([
                'user_id' => $user->id,
                'universe_id' => $cosmos,
                'handle' => $handle,
                'display_name' => ucfirst($handle),
            ]);
        }

        $this->assertTrue($user->fresh()->canCreateAnotherPersona());
    }

    public function test_retiring_a_persona_keeps_its_posts(): void
    {
        $user = User::factory()->create(['is_premium' => true]);
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'retiring',
            'display_name' => 'Retiring',
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'survivor',
            'title' => 'Survivor',
            'body' => '<p>Body</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)->delete("/personas/{$persona->handle}")->assertRedirect();

        $this->assertDatabaseMissing('personas', ['id' => $persona->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'user_id' => $user->id, 'persona_id' => null]);
    }
}
