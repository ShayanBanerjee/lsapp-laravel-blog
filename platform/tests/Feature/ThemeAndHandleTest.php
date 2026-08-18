<?php

namespace Tests\Feature;

use App\Models\CustomTheme;
use App\Models\Persona;
use App\Models\Universe;
use App\Models\User;
use App\Rules\VanityHandle;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The two features /upgrade advertised and did not have.
 */
class ThemeAndHandleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /* -------------------- theme editor -------------------- */

    public function test_the_editor_is_closed_to_free_accounts(): void
    {
        $free = User::factory()->create(['is_premium' => false]);
        $cosmos = Universe::where('slug', 'cosmos')->firstOrFail();

        $this->actingAs($free)->post('/settings/theme', [
            'name' => 'Mine',
            'universe' => $cosmos->slug,
            'overrides' => ['accent' => '#ff0000'],
        ])->assertForbidden();

        $this->assertDatabaseCount('custom_themes', 0);
    }

    public function test_a_premium_user_can_save_and_apply_a_palette(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $this->actingAs($user)->post('/settings/theme', [
            'name' => 'Mine',
            'universe' => 'cosmos',
            'overrides' => ['accent' => '#FF0000', 'bg' => '#101010'],
        ])->assertRedirect();

        $theme = CustomTheme::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['bg' => '#101010', 'accent' => '#ff0000'], $theme->overrides);

        $this->actingAs($user)->post("/settings/theme/{$theme->id}/activate")->assertRedirect();
        $this->assertTrue($theme->fresh()->is_active);
    }

    /**
     * Overrides are interpolated into a style attribute as CSS custom
     * properties, so anything that is not a plain hex colour is a CSS
     * injection vector and must not survive the write.
     */
    public function test_non_hex_values_are_discarded_rather_than_escaped(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $this->actingAs($user)->post('/settings/theme', [
            'name' => 'Mine',
            'universe' => 'cosmos',
            'overrides' => [
                'accent' => '#00ff00',
                'bg' => 'red; background-image: url(https://evil.example.com/x.png)',
                'text' => 'javascript:alert(1)',
                'border' => 'expression(alert(1))',
            ],
        ])->assertRedirect();

        $overrides = CustomTheme::where('user_id', $user->id)->firstOrFail()->overrides;

        $this->assertSame(['accent' => '#00ff00'], $overrides);
    }

    /** Unknown token names are not a way to inject extra CSS declarations. */
    public function test_unknown_tokens_are_ignored(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $this->actingAs($user)->post('/settings/theme', [
            'name' => 'Mine',
            'universe' => 'cosmos',
            'overrides' => ['accent' => '#00ff00', 'somethingElse' => '#ffffff'],
        ])->assertRedirect();

        $this->assertArrayNotHasKey('somethingElse', CustomTheme::where('user_id', $user->id)->firstOrFail()->overrides);
    }

    public function test_an_active_palette_reaches_the_page_for_its_owner_only(): void
    {
        $user = User::factory()->create(['is_premium' => true]);
        $cosmos = Universe::where('slug', 'cosmos')->firstOrFail();

        CustomTheme::create([
            'user_id' => $user->id,
            'universe_id' => $cosmos->id,
            'name' => 'Mine',
            'overrides' => ['accent' => '#abcdef'],
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeUniverse.theme.accent', '#abcdef'));

        // Somebody else gets the universe palette, untouched.
        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeUniverse.theme.accent', $cosmos->theme['accent']));
    }

    /** A lapsed subscription must stop applying the paid palette. */
    public function test_a_palette_stops_applying_when_premium_lapses(): void
    {
        $user = User::factory()->create(['is_premium' => true]);
        $cosmos = Universe::where('slug', 'cosmos')->firstOrFail();

        CustomTheme::create([
            'user_id' => $user->id,
            'universe_id' => $cosmos->id,
            'name' => 'Mine',
            'overrides' => ['accent' => '#abcdef'],
            'is_active' => true,
        ]);

        $user->update(['is_premium' => false]);

        $this->actingAs($user->fresh())->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeUniverse.theme.accent', $cosmos->theme['accent']));
    }

    /* -------------------- vanity handles -------------------- */

    private function claim(User $user, string $handle): TestResponse
    {
        return $this->actingAs($user)->post('/personas', [
            'handle' => $handle,
            'display_name' => 'A Writer',
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
        ]);
    }

    public function test_a_free_account_cannot_claim_a_short_handle(): void
    {
        $this->claim(User::factory()->create(['is_premium' => false]), 'ada')
            ->assertSessionHasErrors('handle');

        $this->assertDatabaseCount('personas', 0);
    }

    public function test_a_premium_account_can_claim_a_short_handle(): void
    {
        $this->claim(User::factory()->create(['is_premium' => true]), 'ada')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['handle' => 'ada']);
    }

    public function test_a_free_account_can_still_claim_a_normal_handle(): void
    {
        $this->claim(User::factory()->create(['is_premium' => false]), 'longlight')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['handle' => 'longlight']);
    }

    /** Impersonation and route collisions are blocked regardless of plan. */
    public function test_reserved_handles_are_refused_even_to_premium(): void
    {
        foreach (['support', 'admin', 'settings', 'inkfathom'] as $reserved) {
            $this->claim(User::factory()->create(['is_premium' => true]), $reserved)
                ->assertSessionHasErrors('handle');
        }

        $this->assertDatabaseCount('personas', 0);
    }

    public function test_every_reserved_word_that_is_a_route_is_actually_reserved(): void
    {
        // A handle equal to a top-level path would be ambiguous; this keeps the
        // list honest as routes are added.
        foreach (['posts', 'learn', 'circles', 'universes', 'categories', 'library', 'following', 'notifications'] as $path) {
            $this->assertContains($path, VanityHandle::RESERVED, "'{$path}' is a route but not reserved");
        }
    }

    /* -------------------- vanity URL -------------------- */

    public function test_a_writer_is_reachable_at_their_vanity_url(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'ada',
            'display_name' => 'Ada',
        ]);

        $this->get('/@ada')->assertOk()->assertSee('Ada');
    }

    /** The vanity route must not swallow real paths. */
    public function test_the_vanity_route_does_not_shadow_real_routes(): void
    {
        $this->get('/posts')->assertOk();
        $this->get('/learn')->assertOk();
        $this->get('/circles')->assertOk();
        $this->get('/universes')->assertOk();
    }
}
