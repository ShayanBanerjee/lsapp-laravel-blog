<?php

namespace App\Http\Controllers;

use App\Models\CustomTheme;
use App\Models\Universe;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The custom theme editor — a premium feature, previously advertised on
 * /upgrade and not built.
 *
 * A theme is a set of token overrides over a base universe, which is the same
 * shape the seeded universes already have. That is why no component changes:
 * they read `--u-accent`, never "which universe is this".
 */
class ThemeController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $themes = CustomTheme::where('user_id', $user->id)
            ->with('universe:id,slug,name')
            ->latest()
            ->get()
            ->map(fn (CustomTheme $theme) => [
                'id' => $theme->id,
                'name' => $theme->name,
                'universe' => $theme->universe?->slug,
                'overrides' => $theme->overrides,
                'is_active' => $theme->is_active,
            ]);

        $base = UniverseContext::activeUniverse($request);

        return Inertia::render('settings/theme', [
            'isPremium' => (bool) $user->is_premium,
            'themes' => $themes,
            'editableTokens' => CustomTheme::EDITABLE,
            // The starting point to edit from — the tokens of the universe the
            // user is currently in, already entitlement-filtered.
            'base' => UniverseContext::serialize($base, $user),
            'universes' => Universe::cachedAll()
                ->filter(fn (Universe $universe) => $user->canAccessUniverse($universe))
                ->map(fn (Universe $universe) => ['slug' => $universe->slug, 'name' => $universe->name])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePremium($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'universe' => ['required', 'string', 'exists:universes,slug'],
            'overrides' => ['required', 'array'],
        ]);

        $universe = Universe::where('slug', $data['universe'])->firstOrFail();

        // You may not build a theme on a world you cannot write in — otherwise
        // the editor becomes a way to read locked palettes back out.
        $this->authorize('use', $universe);

        $overrides = CustomTheme::sanitizeOverrides($data['overrides']);

        if ($overrides === []) {
            throw ValidationException::withMessages([
                'overrides' => 'No usable colours were supplied. Each must be a hex value like #1a2b3c.',
            ]);
        }

        $theme = CustomTheme::create([
            'user_id' => $request->user()->id,
            'universe_id' => $universe->id,
            'name' => $data['name'],
            'overrides' => $overrides,
            'is_active' => false,
        ]);

        return back()->with('success', "Saved “{$theme->name}”.");
    }

    /** Exactly one theme is active at a time, or none. */
    public function activate(Request $request, CustomTheme $theme): RedirectResponse
    {
        $this->requirePremium($request);
        abort_unless($theme->user_id === $request->user()->id, 403);

        CustomTheme::where('user_id', $request->user()->id)->update(['is_active' => false]);

        $theme->update(['is_active' => ! $theme->wasChanged() && $theme->is_active ? false : true]);

        return back()->with('success', "“{$theme->name}” applied.");
    }

    public function deactivate(Request $request): RedirectResponse
    {
        CustomTheme::where('user_id', $request->user()->id)->update(['is_active' => false]);

        return back()->with('success', 'Back to the universe palette.');
    }

    public function destroy(Request $request, CustomTheme $theme): RedirectResponse
    {
        abort_unless($theme->user_id === $request->user()->id, 403);

        $theme->delete();

        return back()->with('success', 'Theme deleted.');
    }

    private function requirePremium(Request $request): void
    {
        abort_unless($request->user()?->is_premium, 403, 'The theme editor is a premium feature.');
    }
}
