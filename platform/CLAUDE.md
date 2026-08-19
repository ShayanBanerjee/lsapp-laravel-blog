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

```bash
php artisan test
```

A single test file or filter: `php artisan test --filter=MonetizationTest`.

Reset to a known state (drops everything, re-seeds the six universes and demo content):

```bash
php artisan migrate:fresh --seed
```

Server-side rendering is enabled. To run the app with it:

```bash
composer run dev:ssr
```

That builds the SSR bundle and runs `php artisan inertia:start-ssr` alongside the server. Plain `composer run dev` skips SSR and client-renders, which is fine for most work — but anything touching a **page or layout** should be checked under SSR, because reading `window` at render time takes the SSR process down for every page and Inertia silently falls back to client rendering rather than erroring.

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

## Courses, and the one global scope

**A lesson is a `Post`** with a `course_module_id`, not a row in a separate table. Lessons want everything writing already has here — marks, responses, letters, bookmarks, sanitisation, reading time, SEO — and a parallel table would mean reimplementing all of it and then keeping two implementations in step.

The cost is that lessons must never appear where standalone writing is listed. That is handled by **`StandalonePostScope`**, a global scope on `Post` that hides them from every query by default. Reading lessons requires opting out explicitly:

```php
Post::withoutGlobalScope(StandalonePostScope::class)
```

`CourseModule::lessons()` and `Course::lessons()` already do, so ordinary course code never thinks about it. The default runs this way round on purpose: forgetting to *apply* a filter would publish something that should not be public, while forgetting to *remove* one merely hides something.

`{readable}` is a route binding (registered in `AppServiceProvider`) that resolves any post, lesson or not. The interaction endpoints — highlights, responses, letters, bookmarks — use it so a lesson supports all four without a duplicate set of routes.

## Storytelling blocks

Stored as one `<figure data-story="…">` carrying **scalar attributes and no children**, and expanded into full markup at render time by `StoryBlocks::expand()`. That split is what makes rich blocks safe in a body rendered with `dangerouslySetInnerHTML`: the only thing ever validated on write is a fixed set of scalars, and the HTML a reader receives is generated from escaped values.

`halo`-style free-form values are never accepted from the client — the author picks a named shape and the gradient is composed server-side.

Expansion must stay **deterministic**. Marks are anchored by block index into the rendered body, so restructuring an existing block type would move every mark after it. Add new types; do not reshape old ones.

## Two design systems, one bridge

The product's own tokens (`--u-*`) and the starter kit's shadcn set both exist.
They are reconciled in `app.css` by remapping the shadcn tokens onto universe
tokens **inside `.u-root`**.

The subtlety, which has its own test: Tailwind v4 declares
`--color-background: var(--background)` at `:root`, and a custom property is
substituted where it is *declared*, not where it is used. Redefining
`--background` deeper in the tree therefore never reaches `bg-background` —
only redefining `--color-background` does. Both families are set. Deleting the
"duplicate" block reintroduces invisible text on every auth form.

## Money

Everything in `App\Support\Earnings`. Three rules, all enforced in code and asserted in tests:

1. **No floats, anywhere.** Every amount is an integer in minor units with an explicit currency. `0.1 + 0.2 !== 0.3`, and a platform that computes a revenue share in floats will eventually owe someone a fraction of a penny it cannot account for.
2. **`platformFee + writerNet === gross`, exactly.** `RevenueSplit` derives the writer's share by *subtraction* rather than a second multiplication, so the two halves cannot drift by a rounding unit. `RevenueSplitTest` asserts this exhaustively for every amount from 1p to £100.
3. **Rounding favours the writer.** The fee floors; the spare penny goes to the person who wrote the thing.

The platform pays card fees out of its own 25%, not off the top. That is what keeps a £1 tip worth making — see the class comment for the arithmetic.

`Ledger` computes balances from the transaction rows rather than caching a running total on the user, and separates **lifetime / clearing / available** deliberately: paying out inside the chargeback window means reclaiming money from a writer who has already been told it is theirs.

`Contributions::settle()` is idempotent. Processors retry webhooks; a second delivery must not pay twice. The `provider_ref` unique index is the hard guarantee.

## The research studio

`App\Support\Academic\Venues`. **Nothing here submits to a publisher, and nothing can** — IEEE, ACM, Springer Nature and Nature all receive manuscripts through editorial systems that publish no third-party submission API. The page says so, in those words.

What it does is remove the day of reformatting: `SubmissionPackage` builds a zip containing the manuscript in the venue's own LaTeX class, a DOCX, BibTeX, the metadata their portal asks for field by field, a cover-letter draft with visible gaps, and a checklist.

Two rules when adding a venue:

- **The checklist is phrased as things to confirm, never as facts about current rules.** Author guidelines change, and a stale requirement stated confidently is worse than none. Every venue carries a live `guidelinesUrl` and the UI says the publisher's guide is authoritative.
- **Never invent a field.** The corresponding-author email is left `null` in `metadata.json` rather than guessed, because a portal rejects a wrong one and an obvious gap is better than a plausible error.

## Motion and the design system

The display face is **Fraunces**, driven on its `SOFT`, `WONK` and `opsz` axes — `.font-display`, `.font-display-sm`, `.font-brand` in `app.css`. The axes are the identity; a static face at three sizes would look like every other publication.

Buttons (`.u-btn`) do four things on interaction — lift, specular sweep, magnetic lean, press — all transform/opacity only, so they run on the compositor and never trigger layout. `useMagnetic` writes `--u-mx`/`--u-my`, coalesced to one write per frame. All four are neutralised under `prefers-reduced-motion`, where the button keeps its colours and loses its movement.

**The Deep Field's zoom does not go through React.** `useDeepZoom` writes `transform` and `opacity` directly onto the layer elements in a rAF loop; React renders the stage once and is only told when the integer layer changes (~6 times for the whole descent). Rendering it from state reconciled the entire stage sixty times a second and was the reason it stuttered. Measured after the change: 16.7ms median frame, zero frames over 32ms across a full descent.

Layers stay mounted for the whole descent and are hidden with `visibility` rather than unmounted — unmounting meant re-decoding a photograph at exactly the moment of transition, which is when a hitch is most visible.

## Known gaps

- **Billing is a stub.** `UpgradeController::activate()` flips `users.is_premium` with no payment taken, and is disabled the moment `services.stripe.secret` is set. Contributions settle inline in demo mode and say so. Real billing means Stripe Checkout for premium, Stripe Connect for payouts, and a webhook calling the existing `Contributions::settle()` — the ledger, split, hold period and payout threshold are already built and tested.
- **Copy detection is platform-local.** `LocalCopyDetector` compares against everything published here and nothing else. Web-wide detection needs a third-party index; `CopyDetector` is an interface bound in the container for exactly that swap.
- **No staff role.** Moderation flags and copy flags are recorded and the author can dismiss flags on their own work, but there is no reviewer-facing queue.
