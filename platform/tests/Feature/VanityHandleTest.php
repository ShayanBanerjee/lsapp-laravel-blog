<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Universe;
use App\Models\User;
use App\Support\Handles;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VanityHandleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private function payload(string $handle): array
    {
        return [
            'handle' => $handle,
            'display_name' => 'A Voice',
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
        ];
    }

    public function test_short_handles_are_premium_only(): void
    {
        $free = User::factory()->create(['is_premium' => false]);

        $this->actingAs($free)
            ->post('/personas', $this->payload('nyx'))
            ->assertSessionHasErrors('handle');

        $this->assertDatabaseCount('personas', 0);
    }

    public function test_a_premium_writer_can_claim_a_short_handle(): void
    {
        $premium = User::factory()->create(['is_premium' => true]);

        $this->actingAs($premium)
            ->post('/personas', $this->payload('nyx'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['handle' => 'nyx']);
    }

    public function test_a_free_writer_can_still_take_a_longer_handle(): void
    {
        $free = User::factory()->create(['is_premium' => false]);

        $this->actingAs($free)
            ->post('/personas', $this->payload('nyxidae'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['handle' => 'nyxidae']);
    }

    public function test_reserved_handles_are_refused_even_with_premium(): void
    {
        $premium = User::factory()->create(['is_premium' => true]);

        foreach (['support', 'inkfathom', 'moderator', 'settings'] as $reserved) {
            $this->actingAs($premium)
                ->post('/personas', $this->payload($reserved))
                ->assertSessionHasErrors('handle');
        }

        $this->assertDatabaseCount('personas', 0);
    }

    public function test_handles_must_contain_a_letter(): void
    {
        $premium = User::factory()->create(['is_premium' => true]);

        $this->actingAs($premium)
            ->post('/personas', $this->payload('123456'))
            ->assertSessionHasErrors('handle');
    }

    public function test_handles_cannot_lead_trail_or_double_up_separators(): void
    {
        $premium = User::factory()->create(['is_premium' => true]);

        foreach (['_leading', 'trailing_', 'double__inside'] as $malformed) {
            $this->actingAs($premium)
                ->post('/personas', $this->payload($malformed))
                ->assertSessionHasErrors('handle');
        }

        $this->assertDatabaseCount('personas', 0);
    }

    public function test_a_taken_handle_comes_back_with_a_free_suggestion(): void
    {
        $first = User::factory()->create(['is_premium' => false]);
        $this->actingAs($first)->post('/personas', $this->payload('cartographer'));

        $second = User::factory()->create(['is_premium' => false]);
        $this->actingAs($second)
            ->post('/personas', $this->payload('cartographer'))
            ->assertSessionHasErrors('handle');

        // Uniqueness is caught by the rule; the suggestion path is asserted
        // directly, since that is the part that has to stay useful.
        $suggestion = Handles::suggest('cartographer', fn (string $c) => Persona::where('handle', $c)->exists());
        $this->assertSame('cartographer2', $suggestion);
    }

    public function test_a_writer_can_rename_a_persona_without_colliding_with_itself(): void
    {
        $user = User::factory()->create(['is_premium' => false]);
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'firstname',
            'display_name' => 'First',
        ]);

        $this->actingAs($user)
            ->put("/personas/{$persona->handle}", [
                'handle' => 'firstname',
                'display_name' => 'Renamed',
                'bio' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $persona->fresh()->display_name);
    }

    public function test_renaming_into_a_vanity_handle_still_needs_premium(): void
    {
        $user = User::factory()->create(['is_premium' => false]);
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'longenough',
            'display_name' => 'Long',
        ]);

        $this->actingAs($user)
            ->put("/personas/{$persona->handle}", [
                'handle' => 'shrt',
                'display_name' => 'Long',
                'bio' => null,
            ])
            ->assertSessionHasErrors('handle');

        $this->assertSame('longenough', $persona->fresh()->handle);
    }
}
