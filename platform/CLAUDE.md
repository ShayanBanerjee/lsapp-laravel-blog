# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Aetheris** — a writing platform where each writer holds several personas, each living in one of six themed universes. The universe a piece belongs to determines how the entire interface looks while reading or writing it.

This directory is the active app. The repo root holds the frozen Laravel 5.6 original; see [../CLAUDE.md](../CLAUDE.md).

## Commands

```bash
composer run dev
```

That one command runs the server, queue listener, log tailer and Vite together — it is the normal way to work. Individually: `php artisan serve`, `npm run dev`.

```bash
php artisan test
```

A single test file or filter: `php artisan test --filter=MonetizationTest`.

Reset to a known state (drops everything, re-seeds the six universes and demo content):

```bash
php artisan migrate:fresh --seed
```

Formatting and static checks — run these before finishing: `./vendor/bin/pint` (PHP), `npm run lint` (ESLint, autofixes), `npm run format` (Prettier), `npx tsc --noEmit` (types).

Demo login: `writer@example.com` / `password` (premium, six personas).

## Architecture

**Stack:** Laravel 12 · Inertia 2 · React 19 + TypeScript · Vite · Tailwind 4 · TipTap · SQLite (dev).

**Rendering.** Laravel keeps routing, validation and session auth; React replaces only the view layer. There is no JSON API and no second auth system — controllers return `Inertia::render('posts/show', [...])`, resolved to `resources/js/pages/posts/show.tsx`.

### The universe system

This is the part worth understanding before changing anything visual.

- Token *values* live in the database (`universes.theme`, seeded by `UniverseSeeder`), not in CSS. `UniverseRoot` injects them as inline CSS custom properties (`--u-bg`, `--u-metal-sheen`, …) via `themeToCssVars`.
- The metallic primitives in `resources/css/app.css` — `.metal`, `.frost`, `.grain`, `.specular`, `.u-btn` — read only those variables.
- **Components must never branch on universe.** There is no `if (universe === 'jungle')` anywhere and there should not be. Adding a seventh universe should mean one seeder row and nothing else.
- `useUniverse()` resolves which world the page renders in: a page's own `universe` prop wins over the shared `activeUniverse`, because a post is always read in its own world regardless of who is reading it.

### Photography

24 Unsplash photos are bundled under `public/images/` (~8 MB): one hero per universe plus three post covers each. They are seeded, not uploaded — `universes.hero_image` and the demo posts point at absolute public paths.

**Licensing matters here.** Everything bundled is under the Unsplash License (free commercial use). Photographer credit is stored in `universes.hero_credit` and rendered by `<HeroImage>`. Do not add imagery from Pinterest or general web search — those are other people's copyrighted work and are not licensed for redistribution.

`cover_image` therefore holds two shapes: uploads keep the disk-relative path from `Storage::store()`, seeded imagery keeps an absolute `/images/...` path. **Always read covers through `Post::coverUrl()`** and gate deletions on `Post::hasUploadedCover()`, so shared seed files are never deleted. Both are covered by tests.

### Motion

`hooks/use-motion.ts` holds the primitives: `useReveal` (scroll-in), `useParallax`, `usePointerSpecular` (cursor-tracked highlight on metal), and `useCountUp`.

Every one of them checks `prefers-reduced-motion` and **degrades to the finished state, never to missing content** — `useReveal` returns `visible: true` immediately under reduced motion, and `.u-reveal` is forced visible in CSS as a second line of defence. If you add an animation that hides content before revealing it, it must do the same.

`usePointerSpecular` also no-ops on touch (`hover: none`) and coalesces pointer events into one write per animation frame.

### Data model

`User ──< Persona ──< Post`, with `Persona ──> Universe` and `Post ──> Universe` (denormalized from the persona for cheap filtering).

`Post` keeps **both** `user_id` and `persona_id`: ownership and billing follow the human, attribution and display follow the persona. Deleting a persona nulls `persona_id` and leaves the posts with their owner — retiring a voice must not destroy the work.

`follows` is polymorphic (a reader follows a persona *or* a universe). `theme_entitlements` is the source of truth for premium access, deliberately separate from the subscription flag so grants and comps do not need a fake subscription.

### The monetization boundary — read this before touching theming

Premium gates which universes a user may **write in** and receive **full theme tokens** for. Reading stays free everywhere.

Theme tokens ship to the browser, so this must be enforced server-side and is:

- `Universe::preview()` — identity plus a three-colour swatch. Always safe to serialize; it is what card thumbnails are drawn from.
- `Universe::tokens()` — the full set. Only reachable through `UniverseContext::serialize`, which substitutes the free Cosmos palette when the viewer is not entitled.

Gating this in React instead would let anyone read the paid palettes out of the JS bundle and flip `data-universe` in devtools. `MonetizationTest` asserts the real tokens never reach an unentitled viewer — keep that test passing.

### Security

`HtmlSanitizer::clean()` is the stored-XSS boundary for the whole app. Post bodies are TipTap HTML rendered with `dangerouslySetInnerHTML`, so every body passes through a tag allowlist on write, with `<script>`/`<style>` removed *including their contents* and link schemes allowlisted. TipTap is not a security boundary — never trust client-side sanitization here.

Authorization is Policies (`PostPolicy`, `UniversePolicy`, `PersonaPolicy`), not inline controller checks. `Controller` has `AuthorizesRequests` added — Laravel 12's base controller does not include it.

## Conventions

- Cover images go through `Storage::disk('public')` under one directory (`cover-images/`), and the **disk-relative path returned by `store()` is what gets persisted**. Never rebuild that path by hand — the legacy app wrote to `cover_image/` and read from `cover_images/`, so no uploaded image ever displayed.
- Flash messages are `->with('success'|'error', …)`, shared through `HandleInertiaRequests` and rendered once by `<Flash>`.
- Prefer `findOrFail`/route-model binding over `find`. Route keys are slugs for posts and universes, handles for personas.
- Motion: anything that drifts, sweeps or parallaxes must stay behind `prefers-reduced-motion`, which is handled centrally at the bottom of `app.css`.
- Custom component classes belong in `@layer components`. Unlayered CSS outranks every Tailwind utility, which silently breaks things like `md:hidden`.
- Grid and flex children need `min-w-0` before `truncate` or `line-clamp` will actually shrink them; without it they refuse to go below their content width and blow the page out horizontally. `.u-root` carries `overflow-x: clip` as a backstop (`clip`, not `hidden`, so the sticky header survives) — but fix the real cause rather than relying on it.

## Known gaps

- **SSR is not enabled.** Post content currently reaches the browser only inside the `data-page` JSON, not as server-rendered HTML. For a blog this is the most consequential thing still outstanding — it needs a `resources/js/ssr.tsx` entry, `npm run build:ssr`, and a running `php artisan inertia:start-ssr` process.
- **Billing is a stub.** `UpgradeController::activate()` flips `users.is_premium` with no payment taken, and is disabled the moment `services.stripe.secret` is set. Real billing means Laravel Cashier plus a webhook writing `theme_entitlements` rows — the access-control code should not need to change.
- The custom theme editor and vanity handles are advertised on `/upgrade` but not built.
- Follows are recorded but there is no feed built from them yet.
