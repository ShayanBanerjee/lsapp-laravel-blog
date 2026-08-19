<?php

namespace App\Http\Middleware;

use App\Models\Universe;
use App\Support\ReadingPreferences;
use App\Support\UniverseContext;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activePersona = UniverseContext::activePersona($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'personas' => $activePersona
                    ? $user->personas()->with('universe')->get()->map(fn ($persona) => [
                        'id' => $persona->id,
                        'handle' => $persona->handle,
                        'display_name' => $persona->display_name,
                        'avatar_path' => $persona->avatar_path,
                        'universe' => $persona->universe->preview(),
                    ])
                    : [],
                'activePersonaId' => $activePersona?->id,
                'canCreatePersona' => $user?->canCreateAnotherPersona() ?? false,
            ],
            // Default theme for the request. Individual pages override this by
            // passing their own `universe` prop (a post renders in its own world).
            'activeUniverse' => UniverseContext::serialize(
                UniverseContext::activeUniverse($request),
                $user,
                $activePersona,
            ),
            // Identity + swatch only — never the full token set for locked worlds.
            'universeIndex' => fn () => Universe::cachedAll()->map(fn (Universe $universe) => [
                ...$universe->preview(),
                'locked' => $universe->is_premium && ! ($user?->canAccessUniverse($universe) ?? false),
            ]),
            // Typography follows the reader across devices. Guests get the
            // defaults rather than nothing, so the reading view is never unstyled.
            'reading' => (function () use ($user) {
                $prefs = ReadingPreferences::normalize($user?->reading_prefs);

                return [...$prefs, 'stack' => ReadingPreferences::stack($prefs['font'])];
            })(),

            // Which social buttons to render. Driven by config so an
            // unconfigured provider never shows a button that 503s.
            'socialProviders' => collect(['google', 'facebook', 'github'])
                ->filter(fn (string $p) => filled(config("services.{$p}.client_id")))
                ->values(),

            /*
             * The unread badge.
             *
             * Wrapped in a closure so it is only evaluated for full page
             * loads and partial reloads that actually ask for it — every
             * response otherwise pays for a count query it never renders.
             */
            'unreadAlerts' => fn () => $user?->alerts()->unread()->count() ?? 0,

            /*
             * Ziggy's route table, as a prop.
             *
             * The @routes Blade directive puts this on `window` for the
             * browser, but server-side rendering runs before Blade and has no
             * window — so the SSR entry reads it from here instead. `location`
             * comes from the request because route(..., absolute: true) needs
             * an origin and there is no address bar to take one from.
             */
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
