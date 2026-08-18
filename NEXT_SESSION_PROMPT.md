# Handoff prompt — Inkfathom

Paste everything below the line into a fresh Claude Code session started at the
repo root. It assumes nothing about prior conversation.

---

You are continuing work on **Inkfathom**, a writing platform in this repo. The
core, the community layer, tutorials, storytelling blocks, integrations, copy
detection, the writer social layer, theming and SSR are all built and tested.
Everything you need is committed — no context from earlier chats is required.

## 1. Orient yourself first

Read these before writing any code, in this order:

1. `ROADMAP.md` — what is built, what is left, and **two things that were built
   and then deliberately removed after measurement**. Do not rebuild either.
2. `STRATEGY.md` — the product thesis. **Every feature must trace back to a
   claim in here.** If it cannot, it is decoration; say so rather than building it.
3. `platform/CLAUDE.md` — engineering guide for the active app. Long, and worth
   all of it: most of the expensive lessons are recorded there.
4. `readme.md` — architecture with diagrams.

Active app is `platform/`. The repo root holds a frozen Laravel 5.6 original —
**do not touch it** unless explicitly asked.

Get it running and green before changing anything:

```bash
cd platform && composer run dev
```

With server-side rendering on (builds the SSR bundle, runs the Node renderer):

```bash
cd platform && composer run dev:ssr
```

```bash
cd platform && php artisan test
```

Baseline is **176 passing tests**. If that is not what you see, stop and report
it rather than building on a broken base.

## 2. Invariants — violating these breaks the product

These were expensive to learn. Treat them as constraints, not suggestions.

**Theming**
- Components **never** branch on universe. No `if (universe === 'jungle')`.
  Token *values* live in the database; a seventh universe is a seeder row, and a
  user's custom theme is a row of overrides in exactly the same shape.

**Security**
- Premium theme tokens, custom palettes and ad payloads are gated **server-side**
  and never serialized for unentitled users. `MonetizationTest` and
  `ThemeAndHandleTest` assert this — keep them passing.
- `HtmlSanitizer` keeps a strict **per-tag attribute allowlist** with a validator
  per value. Never add `style` or anything matching `on*`. Image `src` must stay
  https-or-root-relative — `data:` URIs are an SVG-script vector.
- Theme override values must match a strict hex pattern. They land in a `style`
  attribute, so an unvalidated one is CSS injection.
- Use `HtmlSanitizer::plain()` for any stored text, **never bare `strip_tags`**:
  strip_tags keeps tag *contents*, so `<script>alert(1)</script>` survives as the
  visible string `alert(1)`.
- Cross-object references must be scoped in validation (a response's
  `highlight_id` must belong to *that* post; a lesson must belong to *that*
  course), or you have an IDOR.
- Third-party tokens are `encrypted` cast **and** `$hidden`, so they cannot be
  serialized into an Inertia page.

**SSR — the trap is silence**
- SSR is **on**. When the Node process throws, Inertia catches it and falls back
  to client rendering: the page still returns 200 and looks perfect in a browser,
  while every scraper sees an empty shell. **Never conclude SSR works because the
  site looks right** — read the SSR process output, or check `<div id="app">` has
  rendered children.
- Three things break it, all of which have already bitten: browser globals at
  module scope (the bundle imports every page eagerly), `route()` not existing in
  Node, and duplicate head tags. All three are documented in `platform/CLAUDE.md`.
- `@inertiaHead` is deliberately absent from `app.blade.php`. Do not add it back.
- Any new shareable page renders its meta **server-side** via
  `->withViewData(['seo' => Seo::forPage(...)])`. `SsrTest` asserts every one.

**CSS**
- Custom classes go in `@layer components`. Unlayered CSS outranks every
  Tailwind utility and silently breaks things like `md:hidden`.
- Grid and flex children need `min-w-0` before `truncate` or `line-clamp` will
  shrink; without it the page blows out horizontally on mobile.

**Motion**
- Everything degrades under `prefers-reduced-motion` to the **finished state**,
  never to missing content. Anything that starts at `opacity: 0` needs a failsafe
  that settles it regardless — a background tab never fires IntersectionObserver.
- Scroll-driven work uses the rAF loop in `hooks/use-motion.ts`
  (`useScrollProgress`), not `scroll` events — unreliable during iOS momentum
  scrolling.

**Data**
- Read post covers through `Post::coverUrl()`; `cover_image` holds two shapes.
  Gate deletions on `hasUploadedCover()`.
- **A lesson is a post** (`kind = 'lesson'`). `Post::scopePublished()` excludes
  lessons, which is why no listing needed touching. Add new listings through that
  scope, not around it.
- `Model::preventLazyLoading()` is on outside production. A new N+1 will fail
  tests — fix it, do not disable the guard. Add a `QueryBudgetTest` case for any
  new listing.

**Imagery**
- Bundled photos are Unsplash-licensed with credit stored per universe. **Never**
  add imagery from Pinterest or web search — copyrighted and not licensed.

## 3. The work

Roadmap items, in the order I would take them:

1. **ORCID** *(small, blocked on credentials)* — pure OAuth; the only piece of
   academic export not built.
2. **Editing UI for courses** *(medium)* — courses/modules/lessons are seeded and
   fully readable, but authoring is a seeder today. The reading half is the hard
   half and it is done.
3. **Real billing** *(medium)* — `UpgradeController::activate()` still flips
   `is_premium` with no payment taken. Cashier plus a webhook writing
   `theme_entitlements`. Access control should not need to change.
4. **Open-web copy detection** *(small to integrate)* — one call site in
   `CopyDetection::check()`. The decision is commercial, not technical.
5. **Notification scope** *(small)* — only if writers ask. Do **not** add
   "someone followed you"; that is standing, not feedback.

## 4. Subagent strategy

Subagents start **cold** and re-derive context, so they are worth it only for
genuinely isolated work. Most of what is left is either blocked on credentials or
touches shared files, so the honest answer now is: **mostly do it yourself.**

If you do parallelise, these touch disjoint files:
- ORCID (new service class)
- Open-web copy detection (one service, one call site)

**Do NOT parallelise** anything that edits these shared files — they will
conflict, and this is the single biggest source of wasted work here:
- `routes/web.php`
- `resources/js/layouts/site-layout.tsx` (nav)
- `app/Http/Controllers/PostController.php`
- `resources/css/app.css`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Support/HtmlSanitizer.php`
- `app/Support/UniverseContext.php`

**You (the lead) own those.** Have subagents report the routes, nav entries and
shared props they need, then wire them in yourself.

## 5. Verification gate — all five, every time

```bash
cd platform && ./vendor/bin/pint && npm run lint && npx tsc --noEmit && npm run build && php artisan test
```

- `npm run build` does **not** typecheck. `npx tsc --noEmit` is not optional —
  it has caught runtime crashes the build waved through.
- **New pages must be built before feature tests pass** — Laravel resolves them
  through the Vite manifest, so a missing `npm run build` shows up as
  "Unable to locate file in Vite manifest", not as a page error.
- Anything visible in a browser must be verified in a browser, not assumed.
  Check the console for errors too.
- Adding a route means adding a test. Adding a listing means adding a query
  budget assertion.

Before declaring anything done, confirm on **both** databases:

```bash
cd platform && php artisan test
```

```bash
cd platform && DB_CONNECTION=pgsql DB_DATABASE=inkfathom_check php artisan test
```

The Postgres run needs the database to exist and a role that is not `root` —
`createdb inkfathom_check`, and pass `DB_USERNAME` if your local role differs.

## 6. How to work

- **Report honestly.** If tests fail, say so with the output. If something in
  the roadmap turns out to be impossible or a bad idea, say that instead of
  building a version that cannot work — there is precedent in `ROADMAP.md`, and
  two features in there were built, measured, and thrown away for good reasons.
- **Measure before choosing an algorithm.** The copy-detection work went SimHash
  → measured → shingle containment, and the measurements are recorded in the
  migration. That habit is worth keeping.
- **Ask before**: destructive migrations, deleting content, anything outward-facing.
- **Do not ask before**: ordinary implementation decisions covered by §2.
- OAuth, payments, Readwise and Zenodo are **inert without credentials**. Build
  against the interfaces and test with `Http::fake()`; do not claim end-to-end
  verification you cannot perform.
- Keep `platform/CLAUDE.md` and `ROADMAP.md` current as you land features. The
  next session depends on them being true.

## 7. Known rough edges

- **Course authoring** is seeder-only (see §3.2).
- **`ThemeController::activate()`** has a convoluted toggle expression that works
  but reads badly — worth simplifying next time it is touched.
- **The `pinned` storytelling block** assumes its first child is an `<img>`; a
  block authored the other way round still renders, just without the sticky
  effect.
- **Browser automation note:** in this app's forms, `ref`-based clicks did not
  move focus reliably in the preview pane — coordinate-based clicks from a fresh
  screenshot did. Also, the preview pane can report `document.hidden`, which
  stops IntersectionObserver and CSS transitions; verify those via DOM/CSS
  assertions rather than screenshots when that happens.
