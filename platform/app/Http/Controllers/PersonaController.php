<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Universe;
use App\Models\User;
use App\Support\UniverseContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('personas/index', [
            'personas' => $user->personas()->with('universe')->withCount('posts')->get()
                ->map(fn (Persona $persona) => [
                    'id' => $persona->id,
                    'handle' => $persona->handle,
                    'display_name' => $persona->display_name,
                    'bio' => $persona->bio,
                    'posts_count' => $persona->posts_count,
                    'universe' => $persona->universe->preview(),
                    'locked' => ! $user->canAccessUniverse($persona->universe),
                ]),
            'universes' => Universe::orderBy('sort_order')->get()
                ->map(fn (Universe $universe) => [
                    ...$universe->preview(),
                    // The create form posts universe_id, so it needs the key.
                    'id' => $universe->id,
                    'locked' => ! $user->canAccessUniverse($universe),
                ]),
            'canCreate' => $user->canCreateAnotherPersona(),
            'personaLimit' => $user->is_premium ? null : User::FREE_PERSONA_LIMIT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Persona::class);

        $data = $request->validate([
            'handle' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('personas', 'handle')],
            'display_name' => ['required', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:400'],
            'universe_id' => ['required', 'exists:universes,id'],
        ]);

        $universe = Universe::findOrFail($data['universe_id']);
        $this->authorize('use', $universe);

        $request->user()->personas()->create($data);

        return to_route('personas.index')->with('success', "Persona @{$data['handle']} created.");
    }

    public function update(Request $request, Persona $persona): RedirectResponse
    {
        $this->authorize('update', $persona);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:400'],
        ]);

        $persona->update($data);

        return back()->with('success', 'Persona updated.');
    }

    public function destroy(Request $request, Persona $persona): RedirectResponse
    {
        $this->authorize('delete', $persona);

        // Posts survive: persona_id is nullOnDelete, ownership stays with the user.
        $persona->delete();

        if ($request->session()->get(UniverseContext::SESSION_KEY) === $persona->id) {
            $request->session()->forget(UniverseContext::SESSION_KEY);
        }

        return back()->with('success', 'Persona retired. Their posts remain yours.');
    }

    /** Switch which persona the writer is currently working as. */
    public function switch(Request $request, Persona $persona): RedirectResponse
    {
        $this->authorize('update', $persona);

        if (! $request->user()->canAccessUniverse($persona->universe)) {
            throw ValidationException::withMessages([
                'persona' => 'That universe is locked on your plan.',
            ]);
        }

        $request->session()->put(UniverseContext::SESSION_KEY, $persona->id);

        return back()->with('success', "Now writing as @{$persona->handle}.");
    }
}
