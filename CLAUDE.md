# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Two applications live in this repo

| Path | What it is | Status |
|---|---|---|
| repo root (`app/`, `resources/views/`, …) | The original Laravel 5.6 blog | Legacy — frozen, kept for reference |
| **`platform/`** | **Inkfathom** — the Laravel 12 + Inertia + React rewrite | **Active development** |

**Work in `platform/` unless you are specifically asked to touch the legacy app.** The two do not share code, config, or a database. The rest of this file documents the legacy app; `platform/` has its own [CLAUDE.md](platform/CLAUDE.md).

Run the new app:

```bash
cd platform && composer run dev
```

---

## Legacy app (repo root)

### Stack reality check

This is **Laravel 5.6 on PHP >=7.1.3** — both long past end-of-life. Before suggesting any modern Laravel idiom, check that it existed in 5.6:

- Assets build with **Laravel Mix v2 (webpack)**, not Vite.
- UI is **Blade + Bootstrap 4 + jQuery**. There is no frontend framework in use — Vue 2.5 is in `package.json` and `resources/assets/js/components/ExampleComponent.vue` exists, but nothing mounts it.
- Forms use **`laravelcollective/html`** (`Form::` / `Html::` facades), which has no official support past Laravel 8.
- Post bodies are edited with **`unisharp/laravel-ckeditor`**, loaded via a `<script>` tag in the Blade views.
- Assets live in `resources/assets/` (the pre-5.7 location), not `resources/js` + `resources/css`.

A migration to Laravel 12 + Inertia + React is planned. See [FRONTEND_UPGRADE.md](FRONTEND_UPGRADE.md) — read it before making structural changes, so work isn't invested in code slated for replacement.

## Commands

First-time setup:

```bash
composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan storage:link
```

`php artisan storage:link` is **required**, not optional — cover images are written to `storage/app/public/` and served from `/storage/...`, which 404s without the symlink.

Run the app (two terminals):

```bash
php artisan serve
```

```bash
npm run watch
```

Other build targets: `npm run dev` (one-off development build), `npm run prod` (minified), `npm run hot` (HMR via webpack-dev-server), `npm run watch-poll` (for filesystems without inotify).

Tests:

```bash
vendor/bin/phpunit
```

A single test class or method:

```bash
vendor/bin/phpunit --filter ExampleTest
```

Only the two default stubs exist (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`) — there is **no real coverage** of posts, auth, or ownership rules. Assume any change is unguarded.

**There is no linter or formatter configured** — no Pint, PHP-CS-Fixer, ESLint, or Prettier. Match surrounding style by hand.

## Architecture

The app is a single-resource CRUD blog. The pieces that aren't guessable from the directory listing:

- **Models live directly in `app/`** (`app/Post.php`, `app/User.php`), not `app/Models/` — the pre-Laravel-8 convention. `User hasMany Post`; `Post belongsTo User`.
- **Routing** is three static `PagesController` pages, one `Route::resource('posts', 'PostsController')`, `Auth::routes()`, and `/home` (per-user post dashboard). Controllers are referenced by **string name**, not `::class` — 5.6 style.
- **There is no API layer.** `routes/api.php` contains only the untouched default `auth:api` `/user` stub, and neither Passport nor Sanctum is installed. Any JSON endpoint would be built from scratch.
- **Auth** is the pre-Fortify/Breeze Laravel UI scaffolding: session-based, with `Auth\{Login,Register,ForgotPassword,ResetPassword}Controller`. `PostsController` guards everything except `index`/`show` via `$this->middleware('auth', ['except' => ...])`.
- **Authorization is ad-hoc**, not Policies or Gates. The same inline ownership check is copy-pasted into `edit`, `update`, and `destroy`:
  ```php
  if(auth()->user()->id !== $post->user_id){ return redirect('/posts')->with('error','Unauthorized Page'); }
  ```
  Add the check manually if you add another mutating action.
- **Cover images** are uploaded with `Storage::storeAs`, stored under `storage/app/public/`, and fall back to the sentinel filename `noimage.jpg` when absent. Deleting a post is expected to delete its image.

## Conventions

- Flash messages are set with `->with('success', ...)` / `->with('error', ...)` and rendered centrally by `resources/views/inc/messages.blade.php`, which is included in `layouts/app.blade.php`. Don't render flash output per-view.
- Listings paginate with `->paginate(10)` and render `{{ $posts->links() }}`.
- Forms are built with `Form::open(...)` / `Form::close()` from `laravelcollective/html`, including the `DELETE` method spoofing on the delete button.

## Known latent bugs

These are pre-existing. Fix them rather than reproducing them — and when porting to Laravel 12, do not carry them across:

- **Cover-image path mismatch — uploaded images never render.** Uploads are stored to `public/cover_image` (**singular**) at [PostsController.php:74](app/Http/Controllers/PostsController.php#L74) and [:150](app/Http/Controllers/PostsController.php#L150), but both views request `/storage/cover_images/` (**plural**) — [show.blade.php:6](resources/views/posts/show.blade.php#L6), [index.blade.php:10](resources/views/posts/index.blade.php#L10) — and [:190](app/Http/Controllers/PostsController.php#L190) deletes from the plural path too. Net effect: every uploaded cover image 404s, and nothing is ever actually deleted. Pick one spelling and apply it in all four places.
- **`Post` model property typos.** [app/Post.php](app/Post.php) declares `public $primarykey` and `public $timestamp` — Eloquent reads `$primaryKey` and `$timestamps`, so both are silently inert dead code. It also sets `protected $table = 'Posts'` with a capital P, which works on case-insensitive MySQL/macOS but breaks on case-sensitive setups where the migration created `posts`.
- **No foreign key on `posts.user_id`.** [The migration](database/migrations/2018_03_05_065955_add_user_id_to_posts.php) adds a bare `integer` with no constraint, no index, and no `unsigned` — so it doesn't match `users.id` (an unsigned increment), orphan rows are possible, and the join is unindexed.
- **`Post` has no `$fillable` or `$guarded`.** Every write is manual property assignment. Introducing `Post::create()` or `->update()` with request data would currently be a mass-assignment hole.
- **`resources/views/layouts/app_copy.blade.php` is dead** — an unused duplicate of `app.blade.php`.
- **`PostsController::show`/`edit`** use `Post::find($id)` without a null check, so a missing id yields a 500 on property access rather than a 404. `findOrFail` is the fix.

## Notes

- `config/services.php` contains a **Stripe placeholder block** from the Laravel 5.6 default skeleton. It is boilerplate — there is no payment code, no Cashier, and no Stripe env vars anywhere. Do not read it as evidence of an existing integration.
- `readme.md` is the **stock unmodified Laravel framework README** with no project-specific content. This file is the real orientation document.
- `.env` is not committed; only `.env.example` (MySQL defaults, Mailtrap placeholder, unused Redis/Pusher entries).
- Post bodies are stored as **CKEditor HTML** and rendered with `{!! $post->body !!}` — unescaped. Treat body content as untrusted when changing how it is displayed or when moving to a new editor.
