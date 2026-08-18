# Frontend Upgrade & Architecture Spec

Target: turn this Laravel 5.6 CRUD blog into a modern writing platform — React frontend, a "metallic modern" visual system, writer personas rooted in themed **universes**, and a premium tier that monetizes those universes.

This is the design contract. For the current state of the code, see [CLAUDE.md](CLAUDE.md).

## Status

**Built and running in [`platform/`](platform/)** — Laravel 12, Inertia 2, React 19, Tailwind 4, six seeded universes, personas, post CRUD with TipTap, follows, premium gating, and 50 passing tests. See [platform/CLAUDE.md](platform/CLAUDE.md) for how it fits together.

Still outstanding against this spec:

- **SSR** (§2.1) — not enabled. The single most consequential gap for a blog.
- **Billing** (§2.5) — `/upgrade` flips a flag; no Stripe, no Cashier.
- Custom theme editor and vanity handles — advertised on the pricing page, not built.
- Follows are stored but no feed is composed from them yet.

---

## 1. Why this is a rebuild, not a refactor

The existing app is Laravel 5.6 on PHP 7.1 — both years past end-of-life. The Inertia Laravel adapter requires Laravel 9+, Vite requires Laravel 9.19+, and Tailwind 4 requires a modern build pipeline. None of that can be retrofitted onto 5.6.

The upgrade path chosen is **scaffold fresh on Laravel 12 and port across**, rather than six sequential major-version upgrades. This is only reasonable because the app is genuinely small: two models, three real controllers, five migrations, no API, and no test coverage worth preserving. The real asset here is the domain idea, not the code.

### Kept

`User` and `Post` models · the three migrations (with the bugs in CLAUDE.md fixed) · `PostsController` business logic, rewritten for Inertia · the ownership rules, promoted to a Policy

### Discarded

All Blade views · Laravel Mix / webpack · jQuery · Bootstrap 4 · `laravelcollective/html` · CKEditor · `app_copy.blade.php`

---

## 2. Target stack

| Layer | Choice |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Frontend bridge | Inertia.js 2 |
| UI | React 19 + TypeScript |
| Build | Vite |
| Styling | Tailwind CSS 4 |
| Motion | Framer Motion |
| Editor | TipTap (replaces CKEditor) |
| Payments | Laravel Cashier + Stripe |
| Tests | Pest |

### The rendering contract

Inertia is the key decision. Laravel keeps routing, validation, and session auth; React replaces only the view layer. There is **no REST API and no second auth system**:

```
routes/web.php → PostsController@show → Inertia::render('Posts/Show', ['post' => ...])
                                                    ↓
                                    resources/js/pages/Posts/Show.tsx
```

**SSR is required, not optional.** This is a blog — content must be in the initial HTML response for search engines and link previews. A client-only render would be a product failure, not just a performance one.

Why not a decoupled SPA: it would mean building a JSON API and Sanctum auth from scratch, then solving SEO separately. Inertia avoids all three for a project with no mobile client on the roadmap.

---

## 3. Data model — universes and personas as content structure

The persona system is a **content dimension**, not a skin. A writer holds several personas; each belongs to a universe; each carries its own identity, voice, and following. Readers follow personas or whole universes, and their feed is composed from both.

### New tables

**`universes`** — the themed worlds. Seeded, not user-created.
`slug` · `name` · `tagline` · `theme` (JSON: palette, metal, texture, motion tokens) · `is_premium` · `sort_order`

**`personas`** — a writer's identity within one universe.
`user_id` → users · `universe_id` → universes · `handle` (unique) · `display_name` · `bio` · `avatar_path` · `is_active`

**`follows`** — polymorphic, so a reader can follow a persona *or* a universe.
`user_id` → users · `followable_id` + `followable_type` · unique on (`user_id`, `followable_id`, `followable_type`)

**`theme_entitlements`** — which premium universes a user has unlocked.
`user_id` → users · `universe_id` → universes · `source` (subscription / grant) · `expires_at` (nullable)

### `posts`, extended

Existing: `title`, `body`, `user_id`, `cover_image`.
Added: `persona_id` → personas · `universe_id` → universes (denormalized from persona for cheap filtering) · `slug` (unique) · `excerpt` · `status` (draft / published) · `published_at` · `reading_time`

Keep `user_id` alongside `persona_id`: ownership and billing follow the human, while attribution and display follow the persona. Deleting a persona must not orphan the account's posts.

### Relationships

```
User ──< Persona ──< Post
 │         │
 │         └──> Universe ──< Post
 └──< Follow >── Persona | Universe
 └──< ThemeEntitlement >── Universe
```

---

## 4. The universes

Six at launch. Each is a **complete token set** — surface treatment, typography, motion, and imagery — not a recolor. Free tier gets Cosmos and Nature; the other four are the paid unlock.

| Universe | Material | Palette direction | Character |
|---|---|---|---|
| **Cosmos** | iridescent titanium | deep indigo, starfield black, spectral bloom | vast, still, weightless |
| **Nature** | patinated copper, verdigris | moss, loam, filtered sunlight | warm, growing, close |
| **Mountains** | brushed steel, slate | granite grey, snowfield white, cold blue | severe, clear, high-altitude |
| **Jungle** | oxidized brass | canopy green, humid amber | dense, alive, overgrown |
| **Abyss** | blackened chrome | trench blue, bioluminescent cyan | pressured, dark, luminous |
| **Desert** | aged bronze | dune ochre, dusk violet | spare, sunbleached, wide |

Adding a seventh universe should require a seeder row and a token block — nothing else. If it requires a component change, the theming layer is wrong.

---

## 5. "Metallic modern" design system

The look is metal treated as light behavior, not as a gradient preset. Five primitives, defined once and composed everywhere:

1. **Specular surface** — directional highlight that responds to element position, giving panels a sense of being lit rather than filled.
2. **Anisotropic brush** — fine directional grain, the difference between "grey" and "brushed steel."
3. **Chromatic edge** — a thin tinted rim on raised elements; the universe's metal reads primarily through this.
4. **Frost overlay** — translucent blur for layered surfaces (nav, modals, reading controls).
5. **Weighted elevation** — shadow scales with a surface's implied mass, not with an arbitrary z-index scale.

### Theming mechanism

One token contract, six value sets. The universe is set as `data-universe` on the root element and every primitive reads CSS custom properties:

```css
[data-universe="mountains"] {
  --metal-base: …;  --metal-sheen: …;  --metal-edge: …;
  --grain-angle: …; --surface-1: …;    --surface-2: …;
  --accent: …;      --text-primary: …; --text-muted: …;
}
```

Components never branch on universe. No `if (universe === 'jungle')` anywhere in the React tree — a component that needs to know its universe is a component that will break when the seventh is added.

### Non-negotiables

- **WCAG AA contrast in every universe.** Cosmos and Abyss are dark and Desert is low-contrast by nature; each palette must be validated, not eyeballed.
- **All sheen, parallax, and drift gated behind `prefers-reduced-motion`.** Metallic motion is the most likely thing here to trigger vestibular discomfort.
- **The reading view stays legible above all else.** Texture belongs on chrome and navigation, never behind body text.

---

## 6. Monetization — premium personas & themes

One model: cosmetic and identity expansion. No ads, no reader paywall — the writing stays free to read, which keeps SEO and sharing intact.

| | Free | Premium |
|---|---|---|
| Universes | Cosmos, Nature | All six |
| Personas | 1 | Unlimited |
| Theme editor | — | Custom token overrides |
| Persona handle | shared path | vanity handle / subdomain |

### Enforcement

Entitlements are checked **server-side, in the controller and Policy layer** — a `UniversePolicy` gates persona creation and theme selection, and Inertia only ever serializes token sets the user is entitled to.

This is a real security boundary, not a UI nicety: theme tokens ship to the browser. Gating premium universes purely in React means anyone can read the paid palettes out of the bundle and flip `data-universe` in devtools. The server must never send tokens the user hasn't unlocked.

Stripe integration lands via Cashier subscriptions, with `theme_entitlements` rows as the source of truth so grants and comps don't require a fake subscription.

---

## 7. Migration sequence

Each phase should leave the app bootable.

1. **Scaffold** — `laravel new` with the React starter kit (Inertia 2, Vite, Tailwind 4, TypeScript preconfigured) into a scratch directory. Do not overwrite the working tree.
2. **Port the core** — `User` and `Post`, plus the three migrations, with the CLAUDE.md bugs fixed: unsigned FK + index on `posts.user_id`, corrected `$primaryKey` / `$timestamps` / lowercase `$table`, `$fillable` added, and one consistent cover-image path.
3. **Inertia-ify posts** — rewrite `PostsController` to return `Inertia::render`. Replace the three copy-pasted ownership checks with a single `PostPolicy`. Swap `find` for `findOrFail`.
4. **Rebuild views as React** — `Posts/{Index,Show,Create,Edit}.tsx` and the static pages, built against the token system from day one so nothing needs re-theming later. TipTap replaces CKEditor; sanitize on the way in, since existing bodies are raw CKEditor HTML rendered unescaped.
5. **Add tests** — post CRUD and ownership authorization. This is the first real coverage the project has had; it should exist before personas complicate the model.
6. **Personas & universes** — new tables, seeders for the six universes, persona switcher, universe browse and follow.
7. **Monetization** — Cashier, entitlements, `UniversePolicy`, upgrade flow.

Work lands on `feat-UI-upgrade-and-new-architecture-introduced`. The current app is the only copy of the working code, so nothing gets deleted from the working tree without an explicit go-ahead.

---

## 8. Risks

- **PHP and Composer are not installed locally** (Node 22 is present). Phase 1 is blocked until `brew install php composer`. Laravel 12 needs PHP 8.2+.
- **Zero existing test coverage.** Nothing will tell you the port broke behavior. This is why tests land at step 5, before the model gets more complex — not at the end.
- **SEO must be re-verified after the Blade → Inertia switch.** Confirm rendered post content appears in `curl` output, not just after hydration. This is the single highest-consequence regression available in this migration.
- **Existing post bodies are unescaped CKEditor HTML.** Migrating them into TipTap is a sanitization boundary, not just a format change.
- **Don't start Stripe before the persona model is stable.** Billing built against a schema still in flux is the most expensive kind of rework.
- **Six universes is a real content design cost.** Each needs a validated, accessible palette. Shipping two well-made universes beats six unfinished ones.
