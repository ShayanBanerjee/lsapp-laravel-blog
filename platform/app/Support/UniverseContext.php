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

        $personas = $user->personas()->with(['universe', 'customTheme'])->get();

        if ($personas->isEmpty()) {
            return null;
        }

        $selected = $personas->firstWhere('id', $request->session()->get(self::SESSION_KEY));

        // The author of the active persona is, by definition, the user we
        // already hold — saying so saves themeFor() a lookup on every request.
        return ($selected ?? $personas->first())->setRelation('user', $user);
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
     * When a persona is supplied and wears a custom theme, that theme's tokens
     * replace the universe's — see themeFor() for the entitlement rule.
     *
     * @return array<string, mixed>|null
     */
    public static function serialize(?Universe $universe, ?User $user, ?Persona $persona = null): ?array
    {
        if (! $universe) {
            return null;
        }

        $entitled = ($user && $user->canAccessUniverse($universe)) || ! $universe->is_premium;

        if (! $entitled) {
            $fallback = Universe::cachedAll()->firstWhere('slug', self::FALLBACK_SLUG);

            return $universe->preview() + [
                'locked' => true,
                'theme' => $fallback?->theme ?? $universe->theme,
            ];
        }

        return [
            ...$universe->tokens(),
            'locked' => false,
            'theme' => self::themeFor($universe, $persona),
        ];
    }

    /**
     * The token set a persona's work should actually be read in.
     *
     * A custom theme applies to everyone reading that persona, not just its
     * author — the whole point is that the writing carries the look. But it is
     * a paid feature, so it is checked against the *author's* current plan on
     * every render: let a lapsed subscription keep serving a custom palette and
     * the subscription stops meaning anything.
     *
     * @return array<string, mixed>
     */
    public static function themeFor(Universe $universe, ?Persona $persona): array
    {
        if (! $persona?->custom_theme_id) {
            return $universe->theme;
        }

        // Read through the relation when it is already loaded and fall back to
        // a query when it is not: preventLazyLoading() is on outside
        // production, so an unguarded property read here would turn a missing
        // eager load in some unrelated caller into a hard failure.
        $theme = $persona->relationLoaded('customTheme')
            ? $persona->customTheme
            : $persona->customTheme()->first();

        $author = $persona->relationLoaded('user')
            ? $persona->user
            : $persona->user()->first();

        return ($theme && $author?->is_premium) ? $theme->tokens : $universe->theme;
    }
}
