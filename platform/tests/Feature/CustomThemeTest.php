<?php

namespace Tests\Feature;

use App\Models\CustomTheme;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\ThemeTokens;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private function cosmos(): Universe
    {
        return Universe::where('slug', 'cosmos')->firstOrFail();
    }

    /** A palette that clears every contrast rule, as the editor would submit it. */
    private function readablePayload(array $overrides = []): array
    {
        return [
            'name' => 'Deep Violet',
            'base_universe_id' => $this->cosmos()->id,
            'tokens' => [
                'bg' => '#0a0a12',
                'bgDeep' => '#050509',
                'surface1' => '#14141f',
                'surface2' => '#1d1d2c',
                'border' => '#33334a',
                'text' => '#f2f2fa',
                'textMuted' => '#b6b6cc',
                'accent' => '#c9b6ff',
                'accentFg' => '#0a0a12',
                'glow' => '#c9b6ff',
                'metalBase' => '#2a2a3f',
                'metalSheen' => '#e6e6ff',
                'metalEdge' => '#7f7fb0',
                'metalShadow' => '#030308',
            ],
            'haloShape' => 'dome',
            'haloStrength' => 0.2,
            'grainAngle' => 115,
            'scheme' => 'dark',
            ...$overrides,
        ];
    }

    public function test_the_editor_page_renders_for_free_and_premium_alike(): void
    {
        // The page is not gated — the free tier can see exactly what premium
        // would give them before paying for it. Only saving is gated.
        $free = User::factory()->create(['is_premium' => false]);
        $premium = User::factory()->create(['is_premium' => true]);

        $this->actingAs($free)->get('/settings/themes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/themes')
                ->where('canCreate', false)
                ->has('bases')
                ->has('colorKeys')
                ->has('haloShapes'));

        $this->actingAs($premium)->get('/settings/themes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canCreate', true));
    }

    public function test_the_editor_only_offers_universes_the_author_can_write_in(): void
    {
        $free = User::factory()->create(['is_premium' => false]);

        $this->actingAs($free)->get('/settings/themes')
            ->assertInertia(function ($page) {
                $bases = collect($page->toArray()['props']['bases']);

                // A theme derived from a locked universe would hand out that
                // universe's full token set through the back door.
                $this->assertTrue($bases->every(fn (array $base) => $base['is_premium'] === false));
                $this->assertTrue($bases->isNotEmpty());
            });
    }

    public function test_a_premium_writer_can_save_a_readable_theme(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $this->actingAs($user)
            ->post('/settings/themes', $this->readablePayload())
            ->assertSessionHasNoErrors();

        $theme = CustomTheme::firstOrFail();
        $this->assertSame('Deep Violet', $theme->name);
        $this->assertSame('#c9b6ff', $theme->tokens['accent']);
    }

    public function test_a_free_writer_cannot_save_a_theme(): void
    {
        $user = User::factory()->create(['is_premium' => false]);

        $this->actingAs($user)
            ->post('/settings/themes', $this->readablePayload())
            ->assertForbidden();

        $this->assertDatabaseCount('custom_themes', 0);
    }

    public function test_an_unreadable_palette_is_refused(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        // Grey text on a grey page: about 1.2:1, far under AA.
        $payload = $this->readablePayload();
        $payload['tokens']['text'] = '#6d6d6d';
        $payload['tokens']['textMuted'] = '#5f5f5f';
        $payload['tokens']['bg'] = '#585858';
        $payload['tokens']['surface1'] = '#585858';

        $this->actingAs($user)
            ->post('/settings/themes', $payload)
            ->assertSessionHasErrors('tokens');

        $this->assertDatabaseCount('custom_themes', 0);
    }

    public function test_a_shape_outside_the_allowlist_is_refused(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $payload = $this->readablePayload([
            'haloShape' => 'none); background-image: url(//evil.example/x',
        ]);

        $this->actingAs($user)
            ->post('/settings/themes', $payload)
            ->assertSessionHasErrors('haloShape');

        $this->assertDatabaseCount('custom_themes', 0);
    }

    public function test_a_raw_gradient_never_survives_as_a_token(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        // The second line of defence: even a request that clears validation
        // cannot hand us a gradient — `halo` is composed here, never accepted.
        $payload = $this->readablePayload();
        $payload['tokens']['halo'] = 'url(//evil.example/x)';

        $this->actingAs($user)->post('/settings/themes', $payload)->assertSessionHasNoErrors();

        $theme = CustomTheme::firstOrFail();
        $this->assertStringNotContainsString('evil.example', $theme->tokens['halo']);
        $this->assertStringStartsWith('radial-gradient(', $theme->tokens['halo']);
    }

    public function test_compose_ignores_a_supplied_halo_directly(): void
    {
        // Asserted at the unit boundary too, so the guarantee does not depend
        // on any particular controller keeping its validation rules.
        $tokens = ThemeTokens::compose(
            ['halo' => 'url(//evil.example/x)', 'haloShape' => 'spine'],
            $this->cosmos()->theme,
        );

        $this->assertStringNotContainsString('evil.example', $tokens['halo']);
        $this->assertStringStartsWith('linear-gradient(', $tokens['halo']);
    }

    public function test_unknown_token_keys_are_dropped(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $payload = $this->readablePayload();
        $payload['tokens']['somethingElse'] = '#ff0000';

        $this->actingAs($user)->post('/settings/themes', $payload)->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('somethingElse', CustomTheme::firstOrFail()->tokens);
    }

    public function test_a_non_colour_value_falls_back_to_the_base_universe(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $payload = $this->readablePayload();
        $payload['tokens']['accent'] = 'red; --u-bg: url(x)';

        $this->actingAs($user)->post('/settings/themes', $payload);

        $this->assertSame($this->cosmos()->theme['accent'], CustomTheme::firstOrFail()->tokens['accent']);
    }

    public function test_a_theme_cannot_be_derived_from_a_locked_universe(): void
    {
        $user = User::factory()->create(['is_premium' => true]);
        $locked = Universe::where('is_premium', true)->firstOrFail();

        // Premium unlocks every universe, so drop the flag while keeping the
        // policy's create() satisfied through an explicit entitlement instead.
        $user->update(['is_premium' => true]);
        $payload = $this->readablePayload(['base_universe_id' => $locked->id]);

        // With premium the author is entitled, so this must succeed…
        $this->actingAs($user)->post('/settings/themes', $payload)->assertSessionHasNoErrors();

        // …and without it, the same request must not.
        $free = User::factory()->create(['is_premium' => false]);
        $this->actingAs($free)->post('/settings/themes', $payload)->assertForbidden();
    }

    public function test_a_persona_theme_is_served_to_every_reader(): void
    {
        $author = User::factory()->create(['is_premium' => true]);
        $theme = CustomTheme::create([
            'user_id' => $author->id,
            'base_universe_id' => $this->cosmos()->id,
            'name' => 'Deep Violet',
            'slug' => 'deep-violet',
            'tokens' => ThemeTokens::compose($this->readablePayload()['tokens'], $this->cosmos()->theme),
        ]);

        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => $this->cosmos()->id,
            'custom_theme_id' => $theme->id,
            'handle' => 'violet',
            'display_name' => 'Violet',
        ]);

        Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $this->cosmos()->id,
            'slug' => 'in-my-colours',
            'title' => 'In my colours',
            'body' => '<p>Read this.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        // A logged-out stranger, not the author.
        $this->get('/posts/in-my-colours')
            ->assertInertia(fn ($page) => $page->where('universe.theme.accent', '#c9b6ff'));
    }

    public function test_a_lapsed_subscription_reverts_the_theme_for_readers(): void
    {
        $author = User::factory()->create(['is_premium' => true]);
        $theme = CustomTheme::create([
            'user_id' => $author->id,
            'base_universe_id' => $this->cosmos()->id,
            'name' => 'Deep Violet',
            'slug' => 'deep-violet',
            'tokens' => ThemeTokens::compose($this->readablePayload()['tokens'], $this->cosmos()->theme),
        ]);

        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => $this->cosmos()->id,
            'custom_theme_id' => $theme->id,
            'handle' => 'violet',
            'display_name' => 'Violet',
        ]);

        Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $this->cosmos()->id,
            'slug' => 'in-my-colours',
            'title' => 'In my colours',
            'body' => '<p>Read this.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $author->update(['is_premium' => false]);

        $this->get('/posts/in-my-colours')
            ->assertInertia(fn ($page) => $page->where('universe.theme.accent', $this->cosmos()->theme['accent']));
    }

    public function test_a_theme_cannot_be_applied_to_someone_elses_persona(): void
    {
        $author = User::factory()->create(['is_premium' => true]);
        $stranger = User::factory()->create(['is_premium' => true]);

        $theme = CustomTheme::create([
            'user_id' => $stranger->id,
            'base_universe_id' => $this->cosmos()->id,
            'name' => 'Not yours',
            'slug' => 'not-yours',
            'tokens' => ThemeTokens::compose($this->readablePayload()['tokens'], $this->cosmos()->theme),
        ]);

        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => $this->cosmos()->id,
            'handle' => 'mine',
            'display_name' => 'Mine',
        ]);

        $this->actingAs($author)
            ->put("/personas/{$persona->handle}/theme", ['custom_theme_id' => $theme->id])
            ->assertNotFound();

        $this->assertNull($persona->fresh()->custom_theme_id);
    }

    public function test_deleting_a_theme_leaves_the_persona_intact(): void
    {
        $author = User::factory()->create(['is_premium' => true]);
        $theme = CustomTheme::create([
            'user_id' => $author->id,
            'base_universe_id' => $this->cosmos()->id,
            'name' => 'Deep Violet',
            'slug' => 'deep-violet',
            'tokens' => ThemeTokens::compose($this->readablePayload()['tokens'], $this->cosmos()->theme),
        ]);

        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => $this->cosmos()->id,
            'custom_theme_id' => $theme->id,
            'handle' => 'violet',
            'display_name' => 'Violet',
        ]);

        $this->actingAs($author)->delete("/settings/themes/{$theme->id}")->assertRedirect();

        $persona->refresh();
        $this->assertNotNull($persona->exists);
        $this->assertNull($persona->custom_theme_id);
    }
}
