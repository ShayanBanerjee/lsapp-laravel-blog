<?php

namespace App\Http\Middleware;

use App\Models\Universe;
use App\Support\Notifier;
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

            // Badge count only. The notifications themselves carry passages
            // and are loaded on their own page, not on every request.
            'unreadNotifications' => fn () => Notifier::unreadFor($user),

            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],

            /*
             * Ziggy's route table, for the SSR process only.
             *
             * The browser already has `route()` as a global from the @routes
             * directive, so this exists purely so the Node renderer can resolve
             * route names — without it every page that calls route() throws
             * server-side, and Inertia *silently* falls back to client
             * rendering, so the breakage is invisible from the outside.
             *
             * Only sent on full page loads, which is exactly when SSR runs.
             * Inertia navigations are rendered in the browser, where the global
             * is already set, so shipping ~8 KB of route table with every
             * navigation would buy nothing.
             */
            'ziggy' => $request->inertia()
                ? null
                : fn () => [...(new Ziggy)->toArray(), 'location' => $request->url()],
        ];
    }
}
