<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\CopyFlagController;
use App\Http\Controllers\CourseAuthorController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeepFieldController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\EarningsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FollowingFeedController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileViewController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\UniverseController;
use App\Http\Controllers\UpgradeController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Machine-readable surfaces. Reading is public precisely so these pay off.
Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [FeedController::class, 'robots'])->name('robots');
Route::get('feed.xml', [FeedController::class, 'rss'])->name('rss');

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

/*
 * Taking a piece out of the platform. Reading is public, so the citation and
 * Markdown formats are too — see ExportController for which are the author's
 * alone.
 */
Route::get('posts/{post}/export/{format}', [ExportController::class, 'show'])->name('posts.export');

Route::get('upgrade', [UpgradeController::class, 'show'])->name('upgrade.show');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

/*
 * Courses. `create` is declared ahead of the {course} slug route so it is not
 * swallowed by slug matching, exactly as posts do.
 */
Route::get('courses', [CourseController::class, 'index'])->name('courses.index');

Route::get('circles', [CircleController::class, 'index'])->name('circles.index');
Route::get('circles/{circle}', [CircleController::class, 'show'])->name('circles.show');

/*
 * Public persona profiles.
 *
 * The `@` prefix keeps handles in their own namespace, so a new top-level page
 * can never collide with someone's name — and the reserved-word list in
 * App\Support\Handles is belt to this braces.
 */
Route::get('@{persona}', [ProfileViewController::class, 'show'])->name('profiles.show');

// The Deep Field — one continuous descent through all six universes.
Route::get('deep-field', DeepFieldController::class)->name('deep-field');

// Server-to-server; CSRF-exempt, so signature verification is the whole defence.
Route::post('billing/webhook', [BillingController::class, 'webhook'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('billing.webhook');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::get('write', [PostController::class, 'create'])->name('posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Authoring. Declared before the public slug routes are reached because
    // they live in this group, which is matched first.
    Route::get('courses/new', [CourseAuthorController::class, 'create'])->name('courses.create');
    Route::post('courses', [CourseAuthorController::class, 'store'])->name('courses.store');
    Route::get('courses/{course}/edit', [CourseAuthorController::class, 'edit'])->name('courses.edit');
    Route::post('courses/{course}', [CourseAuthorController::class, 'update'])->name('courses.update');
    Route::delete('courses/{course}', [CourseAuthorController::class, 'destroy'])->name('courses.destroy');
    Route::post('courses/{course}/reorder', [CourseAuthorController::class, 'reorder'])->name('courses.reorder');

    Route::post('courses/{course}/modules', [CourseAuthorController::class, 'storeModule'])->name('courses.modules.store');
    Route::put('courses/{course}/modules/{module}', [CourseAuthorController::class, 'updateModule'])->name('courses.modules.update');
    Route::delete('courses/{course}/modules/{module}', [CourseAuthorController::class, 'destroyModule'])->name('courses.modules.destroy');

    Route::post('courses/{course}/modules/{module}/lessons', [CourseAuthorController::class, 'storeLesson'])->name('courses.lessons.store');
    Route::put('courses/{course}/modules/{module}/lessons/{lesson}', [CourseAuthorController::class, 'updateLesson'])->name('courses.lessons.update');
    Route::delete('courses/{course}/modules/{module}/lessons/{lesson}', [CourseAuthorController::class, 'destroyLesson'])->name('courses.lessons.destroy');

    Route::post('courses/{course}/{lesson}/progress', [CourseController::class, 'toggleProgress'])->name('courses.progress');

    // Zenodo deposit. Creates a draft only — minting the DOI is done on
    // Zenodo, deliberately not from here.
    // Cross-posting. Every destination creates a draft, never a live post.
    Route::post('posts/{post}/share/{provider}', [IntegrationController::class, 'publish'])->name('posts.crosspost');

    /*
     * The research studio. Nothing here submits to a publisher — see
     * App\Support\Academic\Venues\Venue for why that is not possible — it
     * prepares the package a human uploads.
     */
    Route::get('posts/{post}/studio', [StudioController::class, 'show'])->name('posts.studio');
    Route::get('posts/{post}/studio/{venue}', [StudioController::class, 'download'])->name('posts.studio.download');

    Route::post('posts/{post}/deposit', [DepositController::class, 'store'])->name('posts.deposit');

    Route::post('copy-flags/{flag}/clear', [CopyFlagController::class, 'clear'])->name('copy-flags.clear');

    Route::get('personas', [PersonaController::class, 'index'])->name('personas.index');
    Route::post('personas', [PersonaController::class, 'store'])->name('personas.store');
    Route::put('personas/{persona}', [PersonaController::class, 'update'])->name('personas.update');
    Route::delete('personas/{persona}', [PersonaController::class, 'destroy'])->name('personas.destroy');
    Route::post('personas/{persona}/switch', [PersonaController::class, 'switch'])->name('personas.switch');

    Route::post('universes/{universe}/follow', [FollowController::class, 'toggleUniverse'])->name('universes.follow');
    Route::post('personas/{persona}/follow', [FollowController::class, 'togglePersona'])->name('personas.follow');

    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('upgrade', [UpgradeController::class, 'activate'])->name('upgrade.activate');
    Route::delete('upgrade', [UpgradeController::class, 'deactivate'])->name('upgrade.deactivate');

    Route::post('circles/{circle}/membership', [CircleController::class, 'toggle'])->name('circles.toggle');
    Route::get('letters', [LetterController::class, 'index'])->name('letters.index');

    // The read side of follows: what the people and worlds you follow published.
    Route::get('following', FollowingFeedController::class)->name('following');

    /*
     * Reader → writer money. Rate limited with the prose limiter: these create
     * pending charges, and a hot loop of them is abuse rather than generosity.
     */
    Route::middleware('throttle:prose')->group(function () {
        Route::post('posts/{post}/tip', [SupportController::class, 'tip'])->name('support.tip');
        Route::post('personas/{persona}/membership', [SupportController::class, 'subscribe'])->name('support.subscribe');
    });

    Route::delete('personas/{persona}/membership', [SupportController::class, 'cancel'])->name('support.cancel');

    Route::get('earnings', [EarningsController::class, 'index'])->name('earnings.index');
    Route::post('earnings/withdraw', [EarningsController::class, 'withdraw'])->name('earnings.withdraw');

    Route::get('activity', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('activity/read', [AlertController::class, 'readAll'])->name('alerts.read');

    Route::get('library', [LibraryController::class, 'index'])->name('library.index');
    Route::post('posts/{readable}/bookmark', [LibraryController::class, 'toggle'])->name('library.toggle');

    /*
     * User-generated content endpoints are rate limited.
     *
     * Marking is deliberately generous — an engaged reader genuinely marks many
     * passages in one sitting — while writing prose is throttled harder, since
     * that is where spam and abuse actually arrive.
     */
    Route::middleware('throttle:marks')->group(function () {
        Route::post('posts/{readable}/highlights', [HighlightController::class, 'store'])->name('highlights.store');
        Route::delete('highlights/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy');
    });

    Route::middleware('throttle:prose')->group(function () {
        Route::post('posts/{readable}/responses', [ResponseController::class, 'store'])->name('responses.store');
        Route::post('posts/{readable}/letters', [LetterController::class, 'store'])->name('letters.store');
    });

    Route::delete('responses/{response}', [ResponseController::class, 'destroy'])->name('responses.destroy');
});

/*
 * Course slug routes are registered last on purpose.
 *
 * `{course}` and `{lesson}` are wildcards that would otherwise swallow
 * `courses/new` and `courses/{course}/edit`, since Laravel matches in
 * registration order. Anything more specific must come first.
 */
Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
Route::get('courses/{course}/{lesson}', [CourseController::class, 'lesson'])->name('courses.lesson');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
