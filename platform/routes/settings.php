<?php

use App\Http\Controllers\CustomThemeController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ReadingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/reading', [ReadingController::class, 'edit'])->name('reading.edit');
    Route::put('settings/reading', [ReadingController::class, 'update'])->name('reading.update');

    // The custom theme editor. Authoring is premium (CustomThemePolicy); the
    // page itself is not, so the free tier can see what it would get.
    Route::get('settings/themes', [CustomThemeController::class, 'index'])->name('themes.index');
    Route::post('settings/themes', [CustomThemeController::class, 'store'])->name('themes.store');
    Route::put('settings/themes/{theme}', [CustomThemeController::class, 'update'])->name('themes.update');
    Route::delete('settings/themes/{theme}', [CustomThemeController::class, 'destroy'])->name('themes.destroy');
    Route::put('personas/{persona}/theme', [CustomThemeController::class, 'apply'])->name('personas.theme');

    /*
     * Third-party services. Credentials are verified before they are stored and
     * are never sent back to the browser — see IntegrationController.
     */
    Route::get('settings/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('settings/integrations/{provider}', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::delete('settings/integrations/{provider}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::post('integrations/{provider}/highlights', [IntegrationController::class, 'sendHighlights'])->name('integrations.highlights');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
