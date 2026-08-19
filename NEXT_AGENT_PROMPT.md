# Handoff prompt — next build phase

Paste the block below to a fresh agent. It is written to be self-contained:
it assumes no memory of the previous session and points at the files that
carry the real context.

---

```
You are continuing work on Inkfathom, a long-form writing platform in
`platform/` (Laravel 12 · Inertia 2 · React 19 · Tailwind 4 · TipTap).
The repo root holds a frozen Laravel 5.6 original — ignore it.

READ FIRST, IN THIS ORDER:
  1. STRATEGY.md        — why the platform exists. Features must trace to it.
  2. platform/CLAUDE.md — architecture, and the invariants that must not break.
  3. ROADMAP.md         — what is built, what is next, what was checked and
                          found impossible.

CURRENT STATE: 275 tests / 12,320 assertions, green. Run them before you start
and do not finish with them red:

  cd platform && php artisan test

Before finishing any change:
  ./vendor/bin/pint && npm run lint && npm run format && npx tsc --noEmit

YOUR TASK: implement ROADMAP.md item 1 — real billing — and then item 4.

Item 1 is deliberately first because it is the only thing between this
codebase and revenue, and because the hard parts are already done:
  - App\Support\Earnings\RevenueSplit computes the 25% split with exact
    integer arithmetic and is asserted exhaustively. DO NOT change its
    arithmetic or its rounding direction.
  - App\Support\Earnings\Ledger already separates lifetime / clearing /
    available and honours the chargeback hold.
  - Contributions::settle() is already idempotent on provider_ref.

  What is missing is only the wiring:
  a) Stripe Checkout for premium (UpgradeController::activate is a stub that
     disables itself when a live key is present — replace it, do not extend it).
  b) Stripe Connect onboarding writing to the existing payout_accounts table.
  c) A webhook that verifies its signature and calls Contributions::settle().
     StripeGateway::verifyWebhook already shows the signature-checking pattern
     — an unverified billing webhook is a free-money endpoint.
  d) Real transfers for Payout rows, with failure_reason populated on failure.

CONSTRAINTS THAT ARE NOT NEGOTIABLE:
  - No floats in anything touching money. Integer minor units only.
  - Never trust an amount from the request. EarningsController::withdraw takes
    its amount from the ledger; keep it that way and add a test if you touch it.
  - Never mark a contribution settled from a browser-initiated request.
  - Third-party credentials are encrypted at rest and never serialized to the
    browser (see App\Models\Integration).
  - Author-made themes are rejected below WCAG AA, server-side. Do not move
    that check into React.
  - Every motion primitive degrades to its FINISHED state under
    prefers-reduced-motion, never to missing content.
  - Do not add imagery from web search. Only the bundled Unsplash-licensed set
    under platform/public/images.
  - Components must never branch on universe. Adding a universe is a seeder row.

HONESTY RULES — these matter more than the features:
  - If something cannot work, say so in the product rather than shipping a
    button that appears to. The research studio is the worked example: it
    states plainly, on the page, that no publisher accepts third-party API
    submission, and then does the part that is genuinely possible.
  - Do not invent statistics, prices, or capabilities in UI copy.
  - If you add a checklist of external requirements, phrase it as things to
    confirm and link the live authoritative source.

WHEN YOU FINISH: update ROADMAP.md (move what you built into Built, re-order
what remains), update platform/CLAUDE.md if you introduced a new invariant, and
add tests for every rule above that you touched.
```

---

## If you want a different next step

Swap the task line for any of these. They are independent of each other and each
is sized for one focused session.

| Roadmap item | Task line to paste instead |
|---|---|
| 2 — Organisations | `Implement ROADMAP.md item 2: organisations and teams. An org owns personas and courses, members hold roles, billing is per seat. Design for SSO (SAML/OIDC) as the next step even if you do not build it.` |
| 3 — Trust console | `Implement ROADMAP.md item 3: the moderation and trust console. Copy flags and moderation verdicts are already recorded with no reviewer surface. Needs roles, a queue, an immutable audit log of every moderator action, and appeals. Ship the audit log first — it cannot be retrofitted.` |
| 4 — Discovery | `Implement ROADMAP.md item 4: discovery built on marks rather than engagement scores. A "what stopped people this week" surface per subject and per circle. Post::markedPassages already groups in SQL; do not add a view counter.` |
| 5 — Paid posts | `Implement ROADMAP.md item 5: per-piece unlocks against a writer's membership. The first N paragraphs must stay public so the piece remains indexable and shareable — enforce that server-side, not in the reading view.` |
