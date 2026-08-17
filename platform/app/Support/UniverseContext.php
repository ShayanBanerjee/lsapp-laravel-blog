<?php

namespace App\Support;

use App\Models\Persona;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves which universe's theme the current request should render in, and
 * serializes it subject to entitlement.
 */
class UniverseContext
{
    public const SESSION_KEY = 'active_persona_id';

    public const FALLBACK_SLUG = 'cosmos';

    /**
     * The persona the user is currently writing as, if any.
     */
    public static function activePersona(Request $request): ?Persona
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $personas = $user->personas()->with('universe')->get();

        if ($personas->isEmpty()) {
            return null;
        }

        $selected = $personas->firstWhere('id', $request->session()->get(self::SESSION_KEY));

        return $selected ?? $personas->first();
    }

    /**
     * The universe whose tokens should be applied by default.
     */
    public static function activeUniverse(Request $request): ?Universe
    {
        $persona = self::activePersona($request);

        if ($persona?->universe) {
            return $persona->universe;
        }

        return Universe::cachedAll()->firstWhere('slug', self::FALLBACK_SLUG);
    }

    /**
     * Serialize a universe for the frontend, including the full token set only
     * when the viewer is entitled to it.
     *
     * Falls back to the free Cosmos token set so a locked universe still
     * renders a coherent page rather than an unstyled one.
     *
     * @return array<string, mixed>|null
     */
    public static function serialize(?Universe $universe, ?User $user): ?array
    {
        if (! $universe) {
            return null;
        }

        if ($user && $user->canAccessUniverse($universe)) {
            return $universe->tokens() + ['locked' => false];
        }

        if (! $universe->is_premium) {
            return $universe->tokens() + ['locked' => false];
        }

        $fallback = Universe::cachedAll()->firstWhere('slug', self::FALLBACK_SLUG);

        return $universe->preview() + [
            'locked' => true,
            'theme' => $fallback?->theme ?? $universe->theme,
        ];
    }
}
