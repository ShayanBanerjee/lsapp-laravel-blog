<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Sign in with Google / Facebook.
 *
 * The account-linking rule is the security-critical part. We link a social
 * identity to an existing account by email **only when the provider says that
 * email is verified**. Without that check, anyone who can create an account at
 * a provider using someone else's unverified address could take over their
 * account here — the classic pre-verified-email takeover.
 */
class SocialAuthController extends Controller
{
    /** Providers we accept, so a crafted URL cannot probe arbitrary drivers. */
    private const PROVIDERS = ['google', 'facebook', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        abort_unless($this->configured($provider), 503, 'That sign-in method is not configured.');

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        abort_unless($this->configured($provider), 503);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            // Covers a denied consent screen, an expired state token, and a
            // provider outage — none of which should surface as a 500.
            return redirect()->route('login')->withErrors([
                'email' => 'That sign-in did not complete. Please try again.',
            ]);
        }

        $user = DB::transaction(function () use ($provider, $social) {
            $identity = SocialIdentity::where('provider', $provider)
                ->where('provider_id', (string) $social->getId())
                ->first();

            if ($identity) {
                $identity->update([
                    'email' => $social->getEmail(),
                    'nickname' => $social->getNickname(),
                    'avatar' => $social->getAvatar(),
                    'last_login_at' => now(),
                ]);

                return $identity->user;
            }

            $email = $social->getEmail();
            $user = null;

            /*
             * Only auto-link when the provider asserts the address is verified.
             * Google always sets `verified_email`; Facebook does not expose an
             * equivalent, so Facebook logins never silently claim an existing
             * account.
             */
            if ($email && $this->providerVerifiedEmail($provider, $social)) {
                $user = User::where('email', $email)->first();
            }

            $user ??= User::create([
                'name' => $social->getName() ?: ($social->getNickname() ?: 'Reader'),
                // Providers may withhold the address; synthesise a unique,
                // non-routable placeholder rather than failing the signup.
                'email' => $email ?: sprintf('%s_%s@users.noreply.invalid', $provider, Str::random(16)),
                // Never a guessable password. The account is reachable only via
                // this provider until the user sets one through password reset.
                'password' => Str::password(64),
                // Trusting the provider's verification, and only then.
                'email_verified_at' => $email && $this->providerVerifiedEmail($provider, $social) ? now() : null,
            ]);

            SocialIdentity::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => (string) $social->getId(),
                'email' => $email,
                'nickname' => $social->getNickname(),
                'avatar' => $social->getAvatar(),
                'last_login_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user, remember: true);
        request()->session()->regenerate();   // fixation defence on privilege change

        return redirect()->intended(route('dashboard'));
    }

    private function configured(string $provider): bool
    {
        return filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"));
    }

    /** Whether the provider asserts this address has been verified by them. */
    private function providerVerifiedEmail(string $provider, mixed $social): bool
    {
        $raw = method_exists($social, 'getRaw') ? $social->getRaw() : [];

        return match ($provider) {
            'google' => (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false),
            'github' => true,   // GitHub only returns verified primary addresses
            default => false,   // Facebook and anything else: never auto-link
        };
    }
}
