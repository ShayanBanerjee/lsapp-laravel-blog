<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Contribution;
use App\Models\Membership;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\Earnings\Contributions;
use App\Support\Earnings\Ledger;
use App\Support\Earnings\Money;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
        // Billing unconfigured, so contributions settle inline and the whole
        // flow is exercisable end to end.
        config(['services.stripe.secret' => null]);
    }

    /** @return array{0: User, 1: Persona, 2: Post} */
    private function writer(string $handle = 'writer'): array
    {
        $user = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => $handle,
            'display_name' => ucfirst($handle),
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-piece-worth-paying-for-'.$user->id,
            'title' => 'A piece worth paying for',
            'body' => '<p>Words.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        return [$user, $persona, $post];
    }

    public function test_a_reader_can_tip_a_piece_and_the_split_is_recorded(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $this->actingAs($reader)
            ->post("/posts/{$post->slug}/tip", ['amount_minor' => 1000, 'message' => 'This helped me.'])
            ->assertSessionHasNoErrors();

        $contribution = Contribution::firstOrFail();

        $this->assertSame(1000, $contribution->gross_minor);
        $this->assertSame(250, $contribution->platform_fee_minor);
        $this->assertSame(750, $contribution->writer_net_minor);
        $this->assertSame(2500, $contribution->rate_basis_points);
        $this->assertSame($writer->id, $contribution->to_user_id);
        $this->assertSame(Contribution::SETTLED, $contribution->status);
        $this->assertSame('This helped me.', $contribution->message);
    }

    public function test_the_rate_in_force_is_frozen_onto_each_contribution(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/tip", ['amount_minor' => 1000]);

        // Changing the platform rate later must not rewrite what a writer was
        // already told they earned.
        config(['earnings.platform_rate_basis_points' => 5000]);

        $contribution = Contribution::firstOrFail();
        $this->assertSame(750, $contribution->writer_net_minor);
        $this->assertSame(2500, $contribution->rate_basis_points);
    }

    public function test_a_writer_cannot_tip_themselves(): void
    {
        [$writer, , $post] = $this->writer();

        $this->actingAs($writer)
            ->post("/posts/{$post->slug}/tip", ['amount_minor' => 1000])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_amounts_outside_the_allowed_range_are_refused(): void
    {
        [, , $post] = $this->writer();
        $reader = User::factory()->create();

        // Below the minimum, processing costs more than the platform's share.
        $this->actingAs($reader)
            ->post("/posts/{$post->slug}/tip", ['amount_minor' => 5])
            ->assertSessionHasErrors('amount_minor');

        $this->actingAs($reader)
            ->post("/posts/{$post->slug}/tip", ['amount_minor' => 10_000_000])
            ->assertSessionHasErrors('amount_minor');

        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_a_tip_notifies_the_writer_and_is_never_aggregated(): void
    {
        [$writer, , $post] = $this->writer();

        foreach (range(1, 3) as $i) {
            $reader = User::factory()->create();
            $this->actingAs($reader)->post("/posts/{$post->slug}/tip", [
                'amount_minor' => 500,
                'message' => "Note {$i}",
            ]);
        }

        // Marks collapse into a count; money does not. Each of these is a
        // person choosing to pay, and the message is the point.
        $alerts = Alert::where('user_id', $writer->id)->where('type', Alert::SUPPORT)->get();

        $this->assertCount(3, $alerts);
        $this->assertTrue($alerts->every(fn (Alert $a) => $a->count === 1));
    }

    public function test_an_anonymous_tip_hides_the_supporter(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create(['name' => 'Jane Roe']);

        $this->actingAs($reader)->post("/posts/{$post->slug}/tip", [
            'amount_minor' => 500,
            'anonymous' => true,
        ]);

        $contribution = Contribution::firstOrFail();

        $this->assertTrue($contribution->is_anonymous);
        $this->assertSame('A reader', $contribution->supporterName());

        // And the writer's own page must not leak the name either.
        $this->actingAs($writer)->get('/earnings')
            ->assertInertia(fn ($page) => $page->where('recent.0.from', 'A reader'));
    }

    public function test_a_reader_can_become_and_stop_being_a_member(): void
    {
        [$writer, $persona] = $this->writer();
        $reader = User::factory()->create();

        $this->actingAs($reader)
            ->post("/personas/{$persona->handle}/membership", ['amount_minor' => 700])
            ->assertSessionHasNoErrors();

        $membership = Membership::firstOrFail();
        $this->assertSame(Membership::ACTIVE, $membership->status);
        $this->assertSame(700, $membership->amount_minor);
        $this->assertSame(525, Contribution::firstOrFail()->writer_net_minor);

        $this->actingAs($reader)->delete("/personas/{$persona->handle}/membership");

        $membership->refresh();
        $this->assertSame(Membership::CANCELLED, $membership->status);
        // The row survives — it is part of both people's history.
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_only_offered_membership_tiers_are_accepted(): void
    {
        [, $persona] = $this->writer();
        $reader = User::factory()->create();

        $this->actingAs($reader)
            ->post("/personas/{$persona->handle}/membership", ['amount_minor' => 999])
            ->assertSessionHasErrors('amount_minor');

        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_settling_is_idempotent(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $contribution = Contributions::record(
            writer: $writer,
            supporter: $reader,
            gross: new Money(1000),
            kind: Contribution::TIP,
            post: $post,
        );

        Contributions::settle($contribution, 'ref_1');
        Contributions::settle($contribution->refresh(), 'ref_1');

        // A webhook delivered twice must not pay a writer twice.
        $this->assertSame(750, Ledger::lifetime($writer)->amount);
        $this->assertSame(1, Alert::where('user_id', $writer->id)->where('type', Alert::SUPPORT)->count());
    }

    public function test_the_ledger_separates_pending_from_available(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        // Old enough to be past the chargeback hold.
        $matured = Contributions::record($writer, $reader, new Money(2000), Contribution::TIP, post: $post);
        Contributions::settle($matured);
        $matured->forceFill(['settled_at' => now()->subDays(30)])->save();

        // Fresh, so still at risk of being reversed.
        $fresh = Contributions::record($writer, $reader, new Money(1000), Contribution::TIP, post: $post);
        Contributions::settle($fresh);

        $this->assertSame(2250, Ledger::lifetime($writer)->amount);
        $this->assertSame(1500, Ledger::available($writer)->amount);
        $this->assertSame(750, Ledger::pending($writer)->amount);
    }

    public function test_a_withdrawal_takes_its_amount_from_the_ledger_not_the_request(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $contribution = Contributions::record($writer, $reader, new Money(20_000), Contribution::TIP, post: $post);
        Contributions::settle($contribution);
        $contribution->forceFill(['settled_at' => now()->subDays(30)])->save();

        PayoutAccount::create([
            'user_id' => $writer->id,
            'payouts_enabled_at' => now(),
            'status' => 'active',
        ]);

        // A wildly inflated amount in the payload must be ignored entirely.
        $this->actingAs($writer)
            ->post('/earnings/withdraw', ['amount_minor' => 9_999_999])
            ->assertSessionHas('success');

        $this->assertSame(15_000, Payout::firstOrFail()->amount_minor);
    }

    public function test_a_withdrawal_needs_a_connected_payout_account(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $contribution = Contributions::record($writer, $reader, new Money(20_000), Contribution::TIP, post: $post);
        Contributions::settle($contribution);
        $contribution->forceFill(['settled_at' => now()->subDays(30)])->save();

        $this->actingAs($writer)->post('/earnings/withdraw')->assertSessionHas('error');

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_a_balance_under_the_threshold_rolls_over(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $contribution = Contributions::record($writer, $reader, new Money(500), Contribution::TIP, post: $post);
        Contributions::settle($contribution);
        $contribution->forceFill(['settled_at' => now()->subDays(30)])->save();

        PayoutAccount::create(['user_id' => $writer->id, 'payouts_enabled_at' => now(), 'status' => 'active']);

        $this->actingAs($writer)->post('/earnings/withdraw')->assertSessionHas('error');
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_a_paid_out_balance_is_not_available_twice(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $contribution = Contributions::record($writer, $reader, new Money(20_000), Contribution::TIP, post: $post);
        Contributions::settle($contribution);
        $contribution->forceFill(['settled_at' => now()->subDays(30)])->save();

        PayoutAccount::create(['user_id' => $writer->id, 'payouts_enabled_at' => now(), 'status' => 'active']);

        $this->actingAs($writer)->post('/earnings/withdraw');

        $this->assertSame(0, Ledger::available($writer->fresh())->amount);

        $this->actingAs($writer)->post('/earnings/withdraw')->assertSessionHas('error');
        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_the_earnings_page_states_the_platform_cut_plainly(): void
    {
        [$writer, , $post] = $this->writer();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post("/posts/{$post->slug}/tip", ['amount_minor' => 1000]);

        $this->actingAs($writer)->get('/earnings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('earnings')
                ->where('rate.percent', '25%')
                // The fee is shown, not hidden. The writer can work it out
                // anyway; finding it themselves is worse than being told.
                ->where('balances.platform_share.formatted', '£2.50')
                ->where('balances.lifetime.formatted', '£7.50'));
    }

    public function test_earnings_are_private_to_the_writer(): void
    {
        $this->get('/earnings')->assertRedirect('/login');
    }

    public function test_monthly_series_fills_quiet_months(): void
    {
        [$writer] = $this->writer();

        $series = Ledger::monthly($writer, 6);

        $this->assertCount(6, $series);
        $this->assertSame(0, $series[0]['net_minor']);
    }
}
