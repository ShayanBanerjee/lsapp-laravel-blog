# Roadmap — what is built, and what is left

Status as of 18 Aug 2026. Everything in "Built" is working and covered by tests
unless noted. Everything in "Remaining" is deliberately not started.

---

## Built

| Area | Detail |
|---|---|
| Identity | Name **Inkfathom**, `inkfathom.com` registry-confirmed available, ink-drop logo, favicon. No stack names anywhere in the UI. |
| Six universes | DB-driven design tokens; adding a seventh is a seeder row. |
| Personas | Multiple selves per account; switching re-themes the platform. |
| Highlights | Mark a passage, not a post. The core loop. |
| Responses | Anchored to the passage they answer. |
| Letters | Private reader→writer notes. |
| Circles | Community by subject, not follower count. |
| The Deep Field | One continuous zoom through all six universes. |
| Library | Saved + starred, private per reader. |
| Categories | 10 subjects, orthogonal to universes. |
| SEO | Server-rendered OG/Twitter/JSON-LD on every shareable page, sitemap, robots, RSS. |
| Sharing | 8 networks + copy link; shares the *selected passage* when there is one. |
| Reading typography | 6 faces incl. Atkinson Hyperlegible; size, leading, measure — saved per user. |
| Moderation | Narrow lexicon: slurs + directed sexual harassment only. Free expression protected by design and by test. |
| Social sign-in | Google / Facebook / GitHub via Socialite, `social_identities` table, verified-email-only auto-linking. |
| Payments | Provider-agnostic gateway, Stripe driver with real webhook signature verification. Inert without keys. |
| Postgres | Full suite (176 tests) verified green on PostgreSQL 16. |
| SSR | On. Article bodies render server-side; `composer run dev:ssr`. Silent-fallback traps documented in platform/CLAUDE.md. |
| Tutorials | Courses → modules → lessons with a persistent contents panel and per-lesson progress. A lesson **is** a post, so marks, responses and typography work inside one unchanged. |
| Storytelling blocks | Four TipTap blocks — pinned image, step sequence, before/after, data callout — stored as plain semantic HTML and enhanced progressively. |
| Tool integrations | Markdown+YAML export (Obsidian and anything else that reads Markdown), Readwise push. Tokens encrypted at rest. |
| Academic export | IEEEtran LaTeX, DOCX, Zenodo draft deposit, Crossref DOI lookup (no credentials needed). |
| Copy detection | Shingle containment on publish, flagged for a human, never auto-removed. Detects copying **within** the platform only. |
| Writer social | Public profiles, chronological following feed (paginated, finite), notifications that carry the passage rather than a count. |
| Theme editor | Premium palettes layered over any writable universe, gated server-side beside the premium token sets. |
| Vanity handles | Short handles are the paid tier; reserved words blocked for everyone; `/@handle` URLs. |
| Security | CSP, HSTS, rate limits, policies, two-tier sanitisation. |
| Email verification | Enforced: writing gated on a verified address, reading is not. Resend flow, nightly prune of abandoned signups. |

---

## Remaining

Everything on the original roadmap is now built. What is left is smaller and
mostly follows from having shipped the above.

### 1. ORCID
The one item from "academic export" not built. It is pure OAuth — a client id
and secret from ORCID, then a token exchange linking a verified researcher
identity to an account. There is nothing to design; it needs credentials, and
building it blind would produce code nobody can run.

**Effort: small, once credentials exist.**

### 2. Open-web copy detection
`CopyDetection` reliably catches copying *within the platform*. Catching copying
*from the open web* needs an index of the open web — Copyleaks, Originality.ai
and similar sell one, per check. The integration point is a single call in
`CopyDetection::check()`; the decision is commercial, not technical.

**Effort: small to integrate, ongoing per-check cost.**

### 3. Editing UI for courses
Courses, modules and lessons are seeded and fully readable, but there is no
authoring screen — a course is currently created from a seeder. The reading
half is the hard half and it is done; this is CRUD over three tables.

**Effort: medium.**

### 4. Notifications beyond marks and responses
Letters have their own page and are deliberately excluded from the notification
stream. Worth revisiting only if writers say they miss them — deliberately not
adding "someone followed you", which is standing rather than feedback.

**Effort: small.**

### 5. Real billing
Unchanged from before: `UpgradeController::activate()` still flips
`users.is_premium` with no payment taken, and disables itself the moment
`services.stripe.secret` is set. Real billing means Cashier plus a webhook
writing `theme_entitlements` rows. The access-control code should not need to
change — the theme editor and vanity handles both read `is_premium` through the
same gate.

**Effort: medium.**

---

## Checked and not possible as described

**Direct submission to Springer / IEEE / National Geographic.**
IEEE and Springer accept manuscripts only through editorial systems
(ScholarOne, Editorial Manager, Snapp) that publish **no third-party submission
API**; submission requires a human in their portal. Springer Nature's public
APIs are for *reading* metadata, not depositing work. National Geographic
commissions through editors, with no API.

A button labelled "submit to IEEE" could not work. The LaTeX and DOCX exports
are the honest version of the same goal: the file their portal asks for, which
is the step immediately before the portal anyway.

**SimHash for copy detection.**
Built, measured, and replaced. Over pieces this short it scored a three-word
edit at Hamming distance 18 against unrelated writing at 26 — too thin a margin
to threshold safely — and it could not see a single copied paragraph inside a
longer piece at all. Shingle containment separates the same cases 0.84 against
0.00, and catches the lifted paragraph at 0.55. The measurements are in the
`post_fingerprints` migration; do not reintroduce SimHash here without redoing
them.
