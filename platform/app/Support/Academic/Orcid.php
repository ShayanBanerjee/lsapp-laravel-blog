<?php

namespace App\Support\Academic;

use Illuminate\Support\Facades\Http;

/**
 * ORCID sign-in.
 *
 * A plain OAuth 2 authorization-code flow rather than a Socialite driver,
 * because ORCID's token response is not shaped like a normal profile response:
 * the iD comes back in the *token* payload as `orcid`, and there is no user
 * endpoint to call for it in the public `/authenticate` scope.
 *
 * The sandbox host is configurable, which is what makes this testable at all —
 * ORCID's production registry is a real public registry and not somewhere to
 * point a test suite.
 */
class Orcid
{
    public static function isConfigured(): bool
    {
        return filled(config('services.orcid.client_id')) && filled(config('services.orcid.client_secret'));
    }

    public static function host(): string
    {
        return rtrim((string) config('services.orcid.host', 'https://orcid.org'), '/');
    }

    public static function authorizeUrl(string $state, string $redirectUri): string
    {
        return self::host().'/oauth/authorize?'.http_build_query([
            'client_id' => config('services.orcid.client_id'),
            'response_type' => 'code',
            // The narrowest scope that exists: proves who they are, reads
            // nothing. Asking for record access to display a name would be
            // asking for more than the feature needs.
            'scope' => '/authenticate',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Exchange the code for the researcher's iD.
     *
     * @return array{orcid: string, name: ?string}|null
     */
    public static function exchange(string $code, string $redirectUri): ?array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->post(self::host().'/oauth/token', [
                'client_id' => config('services.orcid.client_id'),
                'client_secret' => config('services.orcid.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            return null;
        }

        $orcid = $response->json('orcid');

        // Validate the shape rather than trusting the response: this value is
        // written to a unique column and shown as an identity claim.
        if (! is_string($orcid) || ! self::isValidId($orcid)) {
            return null;
        }

        $name = $response->json('name');

        return ['orcid' => $orcid, 'name' => is_string($name) ? $name : null];
    }

    /**
     * Structure and checksum.
     *
     * ORCID iDs carry an ISO 7064 MOD 11-2 check digit, so a mistyped or
     * invented identifier is detectable without asking the registry.
     */
    public static function isValidId(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $value)) {
            return false;
        }

        $digits = str_replace('-', '', $value);
        $total = 0;

        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }

        $expected = (12 - ($total % 11)) % 11;
        $checkDigit = $expected === 10 ? 'X' : (string) $expected;

        return $checkDigit === strtoupper($digits[15]);
    }
}
