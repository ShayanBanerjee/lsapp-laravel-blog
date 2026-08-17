<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Universe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function toggleUniverse(Request $request, Universe $universe): RedirectResponse
    {
        return $this->toggle($request, $universe, "the {$universe->name} universe");
    }

    public function togglePersona(Request $request, Persona $persona): RedirectResponse
    {
        return $this->toggle($request, $persona, "@{$persona->handle}");
    }

    private function toggle(Request $request, Model $followable, string $label): RedirectResponse
    {
        $existing = $followable->followers()->where('user_id', $request->user()->id)->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', "Unfollowed {$label}.");
        }

        $followable->followers()->create(['user_id' => $request->user()->id]);

        return back()->with('success', "Following {$label}.");
    }
}
