<?php

namespace App\Providers;

use App\Support\Billing\BillingGateway;
use App\Support\Billing\NullGateway;
use App\Support\Billing\StripeGateway;
use App\Support\Moderation\LexiconModerator;
use App\Support\Moderation\Moderator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Moderation is resolved through the interface so a hosted classifier
        // can replace the local lexicon without touching a single call site.
        $this->app->bind(BillingGateway::class, function () {
            $gateway = match (config('services.billing.driver', 'stripe')) {
                'stripe' => new StripeGateway,
                default => new NullGateway,
            };

            // Never hand back a half-configured gateway.
            return $gateway->isConfigured() ? $gateway : new NullGateway;
        });

        $this->app->bind(Moderator::class, function () {
            return match (config('services.moderation.driver', 'lexicon')) {
                default => new LexiconModerator,
            };
        });
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
    }

    private function configureModels(): void
    {
        /*
         * Fail loudly in development instead of silently in production.
         *
         * - strict mode surfaces lazy-loaded relations, which is how N+1 queries
         *   reach production unnoticed. Under concurrency an N+1 is not a
         *   slow page, it is a connection-pool exhaustion event.
         * - Enabled outside production only, so a missed relation degrades to a
         *   slow response for real users rather than a hard 500.
         */
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
    }

    private function configureRateLimiting(): void
    {
        // Marking passages: generous. A genuinely engaged reader marks a lot in
        // one sitting, and throttling that would punish the core interaction.
        RateLimiter::for('marks', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Writing prose: tighter. This is where spam and abuse actually arrive.
        RateLimiter::for('prose', fn (Request $request) => Limit::perMinute(12)
            ->by($request->user()?->id ?: $request->ip()));

        // Credential endpoints, keyed by IP *and* by the email being tried, so
        // one attacker cannot lock out a real user by burning their quota.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(6)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
    }
}
