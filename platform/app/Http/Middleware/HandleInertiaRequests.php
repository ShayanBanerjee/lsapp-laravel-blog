<?php

namespace App\Http\Middleware;

use App\Models\Universe;
use App\Support\UniverseContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'universeIndex' => fn () => Universe::orderBy('sort_order')->get()->map(fn (Universe $universe) => [
                ...$universe->preview(),
                'locked' => $universe->is_premium && ! ($user?->canAccessUniverse($universe) ?? false),
            ]),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
