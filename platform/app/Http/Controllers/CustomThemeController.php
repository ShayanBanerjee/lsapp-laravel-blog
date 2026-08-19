<?php

namespace App\Http\Controllers;

use App\Models\CustomTheme;
use App\Models\Persona;
use App\Models\Universe;
use App\Support\ThemeTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The custom theme editor.
 *
 * Nothing here trusts the submitted token payload. ThemeTokens::compose drops
 * unknown keys, rebuilds every derived value, and refuses to accept a raw
 * gradient at all; the contrast floor is then applied as a validation error
 * rather than a warning, because a theme that fails it is not a style choice —
 * it is an article nobody can read.
 */
class CustomThemeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomTheme::class);

        $user = $request->user();

        return Inertia::render('settings/themes', [
            'themes' => $user->customThemes()->with('baseUniverse')->latest()->get()
                ->map(fn (CustomTheme $theme) => [
                    ...$theme->editorState(),
                    'base' => $theme->baseUniverse->preview(),
                    'personas' => $theme->personas()->pluck('handle'),
                ]),
            // Only universes the author may write in — a custom theme derived
            // from a locked world would leak that world's tokens.
            'bases' => Universe::orderBy('sort_order')->get()
                ->filter(fn (Universe $universe) => $user->canAccessUniverse($universe))
                ->map(fn (Universe $universe) => [
                    ...$universe->preview(),
                    'id' => $universe->id,
                    'tokens' => $universe->theme,
                ])
                ->values(),
            'personas' => $user->personas()->with('universe')->get()
                ->map(fn ($persona) => [
                    'id' => $persona->id,
                    'handle' => $persona->handle,
                    'universe' => $persona->universe->preview(),
                    'custom_theme_id' => $persona->custom_theme_id,
                ]),
            'colorKeys' => ThemeTokens::COLOR_KEYS,
            'haloShapes' => ThemeTokens::haloOptions(),
            'canCreate' => $request->user()->can('create', CustomTheme::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomTheme::class);

        $data = $this->validated($request);
        $base = $this->baseUniverse($request, $data['base_universe_id']);
        $tokens = $this->composed($data, $base->theme);

        $theme = $request->user()->customThemes()->create([
            'base_universe_id' => $base->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($request, $data['name']),
            'tokens' => $tokens,
        ]);

        return to_route('themes.index')->with('success', "Theme “{$theme->name}” saved.");
    }

    public function update(Request $request, CustomTheme $theme): RedirectResponse
    {
        $this->authorize('update', $theme);

        $data = $this->validated($request);
        $base = $this->baseUniverse($request, $data['base_universe_id']);

        $theme->update([
            'base_universe_id' => $base->id,
            'name' => $data['name'],
            'tokens' => $this->composed($data, $base->theme),
        ]);

        return back()->with('success', 'Theme updated.');
    }

    public function destroy(Request $request, CustomTheme $theme): RedirectResponse
    {
        $this->authorize('delete', $theme);

        // Personas fall back to their universe's palette; the FK is nullOnDelete.
        $theme->delete();

        return back()->with('success', 'Theme removed. Those personas are back to their universe palette.');
    }

    /** Put a theme on a persona, or take it off with a null id. */
    public function apply(Request $request, Persona $persona): RedirectResponse
    {
        $this->authorize('update', $persona);

        $data = $request->validate([
            'custom_theme_id' => ['nullable', 'integer'],
        ]);

        $themeId = $data['custom_theme_id'] ?? null;

        if ($themeId !== null) {
            // Scoped to the requesting user, so one account cannot dress its
            // persona in a theme belonging to another.
            $theme = $request->user()->customThemes()->findOrFail($themeId);
            $this->authorize('update', $theme);
            $themeId = $theme->id;
        }

        $persona->update(['custom_theme_id' => $themeId]);

        return back()->with('success', $themeId
            ? "@{$persona->handle} now reads in your theme."
            : "@{$persona->handle} is back to the universe palette.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'base_universe_id' => ['required', 'integer', 'exists:universes,id'],
            'tokens' => ['required', 'array'],
            'tokens.*' => ['nullable', 'string', 'max:32'],
            'haloShape' => ['nullable', 'string', 'in:'.implode(',', array_keys(ThemeTokens::HALO_SHAPES))],
            'haloStrength' => ['nullable', 'numeric', 'between:0,0.6'],
            'grainAngle' => ['nullable', 'numeric'],
            'scheme' => ['nullable', 'in:light,dark'],
        ]);
    }

    private function baseUniverse(Request $request, int $id): Universe
    {
        $universe = Universe::findOrFail($id);

        // A theme derived from a locked universe would hand out that
        // universe's tokens through the back door.
        $this->authorize('use', $universe);

        return $universe;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function composed(array $data, array $base): array
    {
        $tokens = ThemeTokens::compose([
            ...$data['tokens'],
            'haloShape' => $data['haloShape'] ?? 'dome',
            'haloStrength' => $data['haloStrength'] ?? 0.2,
            'grainAngle' => $data['grainAngle'] ?? 115,
            'scheme' => $data['scheme'] ?? 'dark',
        ], $base);

        $failures = ThemeTokens::contrastFailures($tokens);

        if ($failures !== []) {
            throw ValidationException::withMessages([
                'tokens' => array_merge(
                    ['This palette is not readable yet:'],
                    $failures,
                ),
            ]);
        }

        return $tokens;
    }

    private function uniqueSlug(Request $request, string $name): string
    {
        $base = Str::slug($name) ?: 'theme';
        $slug = $base;
        $suffix = 2;

        while ($request->user()->customThemes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
