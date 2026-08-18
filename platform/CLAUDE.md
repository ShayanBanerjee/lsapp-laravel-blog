# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Inkfathom** — a writing platform where each writer holds several personas, each living in one of six themed universes. The universe a piece belongs to determines how the entire interface looks while reading or writing it.

**Read [../STRATEGY.md](../STRATEGY.md) before adding features.** It sets out why this platform exists (what text can do that video structurally cannot) and derives the feature set from that. Every feature here should be traceable to a claim in it; anything that is not is decoration.

This directory is the active app. The repo root holds the frozen Laravel 5.6 original; see [../CLAUDE.md](../CLAUDE.md).

## Commands

```bash
composer run dev
```

That one command runs the server, queue listener, log tailer and Vite together — it is the normal way to work. Individually: `php artisan serve`, `npm run dev`.

To work with server-side rendering on (builds the SSR bundle, then runs the Node
renderer alongside the server):

```bash
composer run dev:ssr
```

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


### The community layer — the core loop

The product thesis in one line: **the passage is the atom, and writers quit because of silence.** Both lead to the same primitive.

- **`highlights`** — a mark on a passage, not a like on an article. Anchored as *(block index, start offset, end offset)* into that block's plain text. Whole-document offsets would invalidate every mark in a piece the moment an author edits an earlier paragraph; per-block offsets only break marks in the block that changed. `quote` is stored so a drifted mark can be re-anchored by search.
- **`responses`** — anchored to a highlight where possible. Pointing at the text you are answering removes most arguments-with-things-nobody-said.
- **`letters`** — private reader→writer notes. No audience, therefore no performance. Never make these public or countable in public; that is the entire feature.
- **`circles`** — community by subject, not by follower graph.
- **`prompts`** — per universe, because the blank page (not lack of ideas) is what stops pieces starting.

`Post::markedPassages()` groups marks in SQL so identical passages collapse to one row with a count. That is the "which sentence worked" answer shown on the writer's desk, and the trending signal used *instead of* view counts.

`Readable` (`components/readable.tsx`) paints marks into the rendered body. It repaints from `innerHTML` on every change rather than incrementally — nesting `<mark>` wrappers on update is the obvious bug otherwise — and wraps per text node rather than using `Range.surroundContents`, which throws whenever a selection crosses an inline element.

### Ads and premium

`App\Support\Ads` returns `[]` for an entitled user, so **no creative is serialized into the page at all**. Hiding ads client-side would leave them in the HTML, which is not what the customer paid for. Ads never appear inside a reading view — only between and after pieces.

### Concurrency and performance

- **Query budgets are tested.** `QueryBudgetTest` asserts query count does not grow with row count. An N+1 under load is not a slow page, it is connection-pool exhaustion presenting as site-wide timeouts.
- **`Model::preventLazyLoading()`** is on outside production, so a missing eager load fails loudly in tests rather than quietly in prod.
- **Universes are cached** (`Universe::cachedAll()`), invalidated by model events. They are read on every request and written approximately never.
- **SQLite is configured for concurrency**: WAL journal mode plus a 5s busy timeout. The Laravel defaults (no WAL, zero timeout) make any two simultaneous writes fail with "database is locked". Verified with 8 parallel processes × 25 writes: zero failures. *This makes SQLite viable for dev and small deployments — it is still not the right production database. Use Postgres at real concurrency.*

### Server-side rendering

`resources/js/ssr.jsx` → `bootstrap/ssr/ssr.js`, run by `php artisan inertia:start-ssr`. Build it with `npm run build:ssr`; `composer run dev:ssr` does both and runs it alongside the server.

**The failure mode is silence.** When the SSR process throws, Inertia catches it and falls back to client rendering: the page still returns 200 and looks perfect in a browser, while every scraper sees an empty shell. Never conclude SSR works because the site looks right — read the SSR process output, or check that `<div id="app">` has rendered children. `SsrTest` guards the parts that can be asserted from PHP.

Three things break it, all of which have already bitten:

- **Browser globals at module scope.** The SSR bundle imports every page eagerly, so a `window.matchMedia(...)` in a module body crashes the process at import time, before any render. Guard with `typeof window === 'undefined'` and resolve lazily — see `hooks/use-appearance.tsx` and `hooks/use-motion.ts`.
- **`route()` is not global in Node.** The browser gets it from the `@routes` directive; the SSR process does not. `ssr.jsx` sets `globalThis.Ziggy` and `globalThis.route` from the `ziggy` shared prop, which `HandleInertiaRequests` sends **on full page loads only** — Inertia navigations render in the browser where the global already exists, so re-sending several KB of route table would buy nothing. `vite.config.js` aliases `ziggy-js` to the composer package, which is where that client actually ships.
- **Duplicate head tags.** `@inertiaHead` is deliberately absent from `app.blade.php`. With SSR on it emits a second `<title>` and `<meta name="description">` from each page's `<Head>`, competing with the server-rendered ones. The Blade tags win because they are the only ones that survive the SSR process being down.

### Tutorials

`courses` → `course_modules` → lessons, where **a lesson is a row in `posts`** (`kind = 'lesson'`, plus `course_module_id` and `sort_order`).

That is the load-bearing decision. Lessons are not a parallel content type, so highlights, responses, letters, bookmarks, reading typography and the sanitiser all apply to them with no polymorphic second case anywhere. A lesson that could not be marked would be a worse lesson.

`Post::scopePublished()` excludes lessons, which is why no existing listing needed touching — feed, sitemap, RSS, universe, subject, circle and home all go through that one scope, so a lesson cannot appear stranded out of its course. Course pages ask for lessons explicitly via `scopePublishedLessons()`. `/posts/{slug}` 301s a lesson into its course.

`CourseController::outline()` builds the contents panel in three queries regardless of course size; `QueryBudgetTest` asserts it does not grow.

### Storytelling blocks

Four TipTap nodes — pinned image, step sequence, before/after, data callout — stored as `<figure data-story="…">` with plain block content inside.

**Nothing about the behaviour lives in the markup.** The static CSS stands alone, so a block is completely readable server-rendered, in an RSS reader, and under reduced motion; `hooks/use-story-blocks.ts` layers the scroll behaviour on afterwards as an enhancement. That is also why it is progressive enhancement rather than React islands: `Readable` replaces `innerHTML` wholesale on every mark, so anything React mounted inside would be destroyed the moment a reader marks a passage. The hook re-attaches after every repaint.

Steps start at `opacity: 0`, so they carry a 3s failsafe that settles them regardless — same belt-and-braces as `.u-reveal` being forced visible in CSS. A tab never brought to the foreground does not fire IntersectionObserver, and stranded invisible prose is far worse than an un-animated reveal.

`HtmlSanitizer` now keeps a **strict per-tag attribute allowlist** (`ALLOWED_ATTRIBUTES`) with a validator per attribute value — `data-story` must be one of four literals, `src` must be https or root-relative (no `data:`, which is an SVG-script vector). Tags are rebuilt from scratch rather than filtered, so a duplicated or malformed attribute cannot survive in the part of the string we did not rewrite. Never add `style` or anything matching `on*`.

### Integrations and export

- **Markdown + YAML frontmatter** is the whole Obsidian integration, and that is not a compromise: a vault is a folder of files on someone's disk, so a file in their format *is* the integration. Works for everyone with no account anywhere.
- **Readwise** maps exactly — their unit is a highlight with a source, and so is ours. Needs the reader's own token.
- **LaTeX/DOCX** are the honest version of "submit to IEEE". `ManuscriptExporter::inlineToTex()` tokenises formatting *before* escaping; escaping first and un-escaping after does not work, because `escapeTex` also escapes the braces of the commands you just inserted.
- **Zenodo** creates a **draft** and stops. Publishing mints a permanent DOI and is irreversible, so the last step stays a human decision on their page.
- **Crossref** needs no credentials at all.

Tokens live in `integrations.token`, `encrypted` cast and `$hidden`, so a leaked backup does not hand over live write access to someone's third-party account, and the credential cannot be serialized into an Inertia page by accident. `IntegrationTest` asserts both.

### Copy detection

Shingle **containment** — overlapping four-word runs, measured as "how much of the smaller piece appears in the larger". Run on publish only; drafts cannot have been copied *from*.

SimHash was built first and rejected on measurement (numbers in the `post_fingerprints` migration). Containment rather than Jaccard because Jaccard punishes a copy for being pasted into a longer piece — a lifted paragraph scores 0.26 by Jaccard and 0.55 by containment, and the second number describes what happened.

The `post_shingles.shingle_hash` index is what makes this a lookup rather than a scan; without it, publishing gets slower with every piece ever written.

**Scope, and say it plainly to users:** this detects copying *within the platform*. It cannot detect copying from the open web — that needs an index of the open web. Nothing is ever blocked or removed automatically; a flag is a prompt for a human, because near-identical pairs have legitimate explanations.

### Writer social layer

Built against STRATEGY.md's explicit exclusions, which matter more here than anywhere else:

- **No public follower counts.** A profile shows published pieces and which sentences landed. `WriterSocialTest` asserts the *absence*, because absences regress silently.
- **No infinite feed.** `/following` is chronological, unranked and paginated. Ranking would be a claim about what you should read next, and the reader sets the pace.
- **Notifications carry the passage, never a tally.** `quote` is denormalised on `writer_notifications` so the writer keeps the specific praise even after the highlight is deleted. There is no "someone followed you" — that is standing, not feedback.

### Theme editor and vanity handles

Both were advertised on `/upgrade` and are now real.

A custom theme is a set of token **overrides** over a base universe — the same shape a seeded universe has, which is why no component changed. Applied inside `UniverseContext::withCustomTheme()`, on the **entitled branch only**, beside the premium token sets: gating it in React would put the mechanism in the JS bundle. A lapsed subscription stops applying it, which is tested.

Override values are validated to a strict hex pattern and re-filtered on read. They are interpolated into a `style` attribute as CSS custom properties, so an unvalidated value is a CSS injection — matching a pattern removes the class of problem rather than trying to escape it.

Short handles (2–4 characters) are the paid tier; `App\Rules\VanityHandle::RESERVED` blocks impersonation and route collisions for **everyone**, premium or not. A test asserts every top-level route path is in that list, so it stays honest as routes are added.

### Email verification

`User` implements `MustVerifyEmail`, which is what makes the whole scaffolding
live — without the contract, `Registered` sends nothing and the `verified`
middleware passes everyone.

The gate is **writing, not membership**. `verified` guards the editor, publish,
update, responses and letters. It deliberately does *not* guard reading,
marking, saving, following, or `posts.destroy` — marks are the core loop and
gating them would cost real readers to inconvenience spammers who never read,
and taking your own work down must never require clearing a hurdle first.

`users:prune-unverified` (nightly, `auth.unverified_grace_days`, default 30)
deletes abandoned signups. Eligibility lives in `User::scopeAbandonedUnverified`
and excludes any account holding content of any kind, **and any account with a
social identity** — Facebook does not assert a verified address, so a real
person can sit at `email_verified_at = null` forever and must not be swept up.
Use `--dry-run` before trusting a window change.

### Security

- `HtmlSanitizer::clean()` — tag allowlist for post bodies (rich text, rendered with `dangerouslySetInnerHTML`).
- `HtmlSanitizer::plain()` — for everything stored as text: responses, letters, quotes. **Not just `strip_tags`**, which keeps tag *contents*, so `<script>alert(1)</script>` survives as the visible string `alert(1)`.
- `SecurityHeaders` middleware: CSP (the second line of defence behind the sanitizer), plus HSTS, nosniff, frame-deny, Permissions-Policy.
- Rate limits: `marks` is generous (120/min — engaged readers mark a lot), `prose` is tight (12/min), `auth` is keyed by IP *and* email so an attacker cannot lock out a real user by burning their quota.
- Ownership rules live in Policies, auto-discovered by name. Cross-object references are scoped in validation — e.g. a response's `highlight_id` must belong to *that post*, or it could be anchored to a passage in someone else's piece.

### The Deep Field (`/deep-field`)

One continuous zoom through all six universes, cosmos down to abyss. Driven by `useScrollProgress`, which is a **rAF loop gated by IntersectionObserver, deliberately not a `scroll` listener** — scroll events are delayed through iOS momentum scrolling, coalesced in some webviews, and not always emitted for programmatic scrolls. Under `prefers-reduced-motion` the entire mechanism is replaced by a plain vertical article carrying identical text.

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

- **Billing is a stub.** `UpgradeController::activate()` flips `users.is_premium` with no payment taken, and is disabled the moment `services.stripe.secret` is set. Real billing means Laravel Cashier plus a webhook writing `theme_entitlements` rows — the access-control code should not need to change.
- The custom theme editor and vanity handles are advertised on `/upgrade` but not built.
- Follows are recorded but there is no feed built from them yet.
