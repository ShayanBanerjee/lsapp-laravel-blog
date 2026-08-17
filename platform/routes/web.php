<?php

use App\Http\Controllers\CircleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeepFieldController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\UniverseController;
use App\Http\Controllers\UpgradeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * Public reading. Anyone can browse universes and read what is published in
 * them — the writing stays free to read, which keeps SEO and sharing intact.
 * Monetization lives on the writing side (personas and themes).
 */
Route::get('universes', [UniverseController::class, 'index'])->name('universes.index');
Route::get('universes/{universe}', [UniverseController::class, 'show'])->name('universes.show');

// `create`/`edit` are declared by resource() ahead of the {post} slug route,
// so they are not swallowed by slug matching.
Route::resource('posts', PostController::class)
    ->only(['index', 'show'])
    ->parameters(['posts' => 'post']);

Route::get('upgrade', [UpgradeController::class, 'show'])->name('upgrade.show');

Route::get('circles', [CircleController::class, 'index'])->name('circles.index');
Route::get('circles/{circle}', [CircleController::class, 'show'])->name('circles.show');

// The Deep Field — one continuous descent through all six universes.
Route::get('deep-field', DeepFieldController::class)->name('deep-field');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::get('write', [PostController::class, 'create'])->name('posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::get('personas', [PersonaController::class, 'index'])->name('personas.index');
    Route::post('personas', [PersonaController::class, 'store'])->name('personas.store');
    Route::put('personas/{persona}', [PersonaController::class, 'update'])->name('personas.update');
    Route::delete('personas/{persona}', [PersonaController::class, 'destroy'])->name('personas.destroy');
    Route::post('personas/{persona}/switch', [PersonaController::class, 'switch'])->name('personas.switch');

    Route::post('universes/{universe}/follow', [FollowController::class, 'toggleUniverse'])->name('universes.follow');
    Route::post('personas/{persona}/follow', [FollowController::class, 'togglePersona'])->name('personas.follow');

    Route::post('upgrade', [UpgradeController::class, 'activate'])->name('upgrade.activate');
    Route::delete('upgrade', [UpgradeController::class, 'deactivate'])->name('upgrade.deactivate');

    Route::post('circles/{circle}/membership', [CircleController::class, 'toggle'])->name('circles.toggle');
    Route::get('letters', [LetterController::class, 'index'])->name('letters.index');

    /*
     * User-generated content endpoints are rate limited.
     *
     * Marking is deliberately generous — an engaged reader genuinely marks many
     * passages in one sitting — while writing prose is throttled harder, since
     * that is where spam and abuse actually arrive.
     */
    Route::middleware('throttle:marks')->group(function () {
        Route::post('posts/{post}/highlights', [HighlightController::class, 'store'])->name('highlights.store');
        Route::delete('highlights/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy');
    });

    Route::middleware('throttle:prose')->group(function () {
        Route::post('posts/{post}/responses', [ResponseController::class, 'store'])->name('responses.store');
        Route::post('posts/{post}/letters', [LetterController::class, 'store'])->name('letters.store');
    });

    Route::delete('responses/{response}', [ResponseController::class, 'destroy'])->name('responses.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
