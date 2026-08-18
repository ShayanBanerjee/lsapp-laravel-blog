<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\ReadingPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReadingController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/reading', [
            'fonts' => ReadingPreferences::options(),
            'defaults' => ReadingPreferences::DEFAULTS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Allowlisted, not free text — this value becomes a CSS font stack.
            'font' => ['required', 'string', Rule::in(array_keys(ReadingPreferences::FONTS))],
            'size' => ['required', 'integer', 'min:15', 'max:26'],
            'leading' => ['required', 'numeric', 'min:1.35', 'max:2.2'],
            'measure' => ['required', 'integer', 'min:52', 'max:88'],
        ]);

        $request->user()->update([
            'reading_prefs' => ReadingPreferences::normalize($data),
        ]);

        return back()->with('success', 'Reading settings saved.');
    }
}
