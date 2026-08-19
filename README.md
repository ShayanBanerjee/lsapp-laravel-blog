# Inkfathom

A long-form writing platform where **readers mark the exact sentence that
landed**, so writers finally know which line did the work — and get paid for it.

Reading is free and always will be. Writers keep **75% of everything readers
give them**, and card fees come out of the platform's share rather than theirs.

```bash
cd platform && composer run dev
```

Demo login: `writer@example.com` / `password`

---

## What makes it different

**The passage is the atom.** A reader marks a sentence, not an article. Those
marks are the trending signal, the writer's feedback, and the thing a subject
hub opens with — instead of a view counter, which tells a writer nothing they
can act on.

**Silence is the thing being solved.** Writers quit because nothing comes back.
So: marks, responses anchored to the passage they answer, private letters with
no audience and therefore no performance, and money.

**Seven universes.** Design tokens live in the database, not in CSS. A piece is
read in the world it was written in. Adding a universe really is one seeder row:
Signal, the machine room, was added last and needed no component change, and
`UniverseThemeTest` asserts that no component anywhere branches on a universe
slug.

**Nothing is faked.** Where an integration cannot exist, the product says so
rather than shipping a button that appears to work. See the research studio.

---

## The stack

Laravel 12 · Inertia 2 · React 19 + TypeScript · Vite · Tailwind 4 · TipTap ·
SQLite in dev, Postgres in production. SSR enabled.

Two design systems coexist: the product's own (`--u-*`) and the starter kit's
shadcn set. They are reconciled by a token bridge in `app.css` that remaps the
shadcn tokens onto universe tokens inside `.u-root` — see
`ThemeTokenBridgeTest` for the subtlety that makes it necessary.

There is no JSON API and no second auth system: Laravel keeps routing,
validation and session auth, and React replaces only the view layer.

## Repository layout

| Path | What it is |
|---|---|
| **`platform/`** | **Inkfathom. This is the app.** |
| repo root | The original Laravel 5.6 blog it grew out of. Frozen, kept for reference. |

The two share no code, config or database. Work in `platform/` — it has its own
[CLAUDE.md](platform/CLAUDE.md) covering architecture in detail.

## Commands

```bash
cd platform
composer run dev        # server, queue, logs and Vite together
composer run dev:ssr    # the same, with server-side rendering on
php artisan test        # 286 tests
php artisan migrate:fresh --seed
```

Before finishing any change:

```bash
./vendor/bin/pint && npm run lint && npm run format && npx tsc --noEmit
```

---

## How the money works

| Step | |
|---|---|
| A reader tips a piece, or takes a monthly membership in a voice | |
| Platform takes **25% of gross** | Card fees come out of this, not out of the writer's share |
| Writer receives **75%** | Rounding favours the writer where 25% does not divide into whole pence |
| Money clears after **14 days** | The window in which a card payment can still be reversed |
| Payouts above a floor | Below it, balances roll over — a transfer would cost more than it moves |

Every amount is an integer in minor units. There is no float anywhere in the
earnings subsystem, and `RevenueSplit` is asserted to reconcile exactly at every
amount from 1p to £100.

The writer's earnings page states the platform's cut in full. They could work it
out anyway; finding it themselves would be worse than being told.

## The research studio

Prepares a piece for **arXiv, IEEE, ACM, Springer Nature or Nature**: the
manuscript in that publisher's own LaTeX class, the metadata their portal asks
for, a cover-letter draft and a checklist from their author guidelines.

It does **not** submit, and says so on the page. Those publishers receive
manuscripts only through editorial systems that publish no third-party
submission API. What it removes is the day of reformatting.

Zenodo deposit (real DOIs), ORCID (real OAuth, with checksum validation) and
Crossref lookup are wired, because those APIs genuinely exist.

## Courses

Multi-lesson learning paths with a persistent contents panel. A lesson **is** a
`Post`, so marks, responses, letters and bookmarks work inside a course without
a second implementation — a global scope keeps lessons out of every feed that
lists standalone writing.

Reading one is built around finishing it: a scroll progress hairline, per-module
completion, time remaining rather than time total, arrow-key navigation between
lessons, a single "mark done and continue" action, and a real finishing line on
the last lesson.

## Accessibility

Not a checklist item here.

- Author-made themes are **rejected below WCAG AA**, checked server-side —
  because a check that ships in the bundle is one an author can edit out.
- Every shipped palette is held to the same floor, asserted in
  `UniverseThemeTest`. Holding our own worlds to a lower standard than the
  themes we reject would be indefensible.
- Every motion primitive degrades to its *finished state* under
  `prefers-reduced-motion`, never to missing content.
- The Deep Field's zoom is replaced entirely by a plain vertical article
  carrying identical text.
- Atkinson Hyperlegible is offered as a reading face, and reader typography
  (size, leading, measure) follows the account across devices.

## Security

CSP, HSTS and a strict header set · two-tier sanitisation (`clean()` for rich
text, `plain()` for everything stored as text — and `plain()` is not
`strip_tags`, which keeps tag *contents*) · storytelling blocks stored as
validated scalars and expanded to markup only at render · policies rather than
inline ownership checks · rate limits keyed by IP *and* email so an attacker
cannot lock out a real user · third-party credentials encrypted at rest and
never serialized to the browser.

---

## Contributing

Read [STRATEGY.md](STRATEGY.md) before adding a feature. It sets out what text
can do that video structurally cannot, and derives the feature set from it. If a
proposed feature does not trace back to something in there, it is decoration.

Then read [ROADMAP.md](ROADMAP.md) for what is next and what has been checked and
found impossible.
