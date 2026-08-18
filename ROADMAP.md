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
| SEO | Server-rendered OG/Twitter/JSON-LD, sitemap, robots, RSS. |
| Sharing | 8 networks + copy link; shares the *selected passage* when there is one. |
| Reading typography | 6 faces incl. Atkinson Hyperlegible; size, leading, measure — saved per user. |
| Moderation | Narrow lexicon: slurs + directed sexual harassment only. Free expression protected by design and by test. |
| Social sign-in | Google / Facebook / GitHub via Socialite, `social_identities` table, verified-email-only auto-linking. |
| Payments | Provider-agnostic gateway, Stripe driver with real webhook signature verification. Inert without keys. |
| Postgres | Full suite (94 tests) verified green on PostgreSQL 16. |
| Security | CSP, HSTS, rate limits, policies, two-tier sanitisation. |

---

## Remaining

Ordered by my recommendation. Nothing here is blocked by anything except where noted.

### 1. Tutorials with a dynamic side panel — *e.g. "Master GitHub Copilot (GH-300)"*
Multi-page, multi-section learning paths with a persistent left-hand contents
panel, progress tracking, and per-section marking.

Needs: `courses` / `modules` / `lessons` schema, a progress table, and a nested
layout. The reading and highlight layers already work inside it unchanged.

**Effort: large.** The single biggest remaining item, and the best fit for the
"technology themes" idea.

### 2. Storytelling tools
Scrollytelling blocks the editor can insert — pinned images, step-through
sequences, before/after, data callouts. The Deep Field proves the mechanics;
this makes them available to writers rather than hardcoded into one page.

**Effort: large.** Needs custom TipTap nodes plus renderers.

### 3. Tool integrations
Real APIs, in order of usefulness: **Readwise** (highlights map exactly onto our
marks), **Notion**, **Zotero**, **Ghost**, **Dev.to**, **Hashnode**,
**WordPress**, **Buttondown**, **GitHub Gist**, **Instapaper**.

- **Obsidian** has no cloud API — integration means Markdown export with YAML
  frontmatter plus the `obsidian://` URI scheme.
- **Medium** API deprecated (2023) and **Pocket** shut down (2025). Both out.

**Effort: medium**, one at a time. Needs API keys per service.

### 4. Academic / publisher export
**Not** direct submission — see the note at the bottom. What is real:

- **Zenodo** — genuine deposit API, mints real DOIs.
- **ORCID** — genuine OAuth, links a verified researcher identity.
- **Crossref** — metadata lookup.
- Export to IEEE LaTeX template and manuscript DOCX.

**Effort: medium.**

### 5. Plagiarism / copy detection
Fingerprint published bodies (shingling + SimHash), compare on publish, flag
near-duplicates for review. Detects copying *within* the platform reliably;
detecting copying *from the open web* needs a third-party index (Copyleaks,
Originality.ai) and is a per-check cost.

**Effort: medium-large.**

### 6. Writer social layer
Profiles, following between writers, a following-feed, notifications.
Follows are already recorded — nothing composes a feed from them yet.

**Effort: medium.** Worth doing *after* tutorials, since it benefits from having
more content to follow.

### 7. Email verification enforcement
The scaffolding exists and social sign-ins set `email_verified_at` when the
provider asserts it. Not yet enforced: gating publishing behind verification,
re-send flow, and expiring unverified accounts.

**Effort: small.**

### 8. Custom theme editor + vanity handles
Both are advertised on `/upgrade` and neither is built. Either build them or
remove the promise — shipping a pricing page that lists absent features is worse
than a shorter list.

**Effort: medium.**

### 9. Server-side rendering
`vite.config.js` now has an SSR entry and `npm run build:ssr` exists, so this is
closer than it was. Turning it on means building the SSR bundle and running
`php artisan inertia:start-ssr`.

Less urgent than it was — the SEO metadata is now server-rendered
independently — but still the right end state for article content.

**Effort: small-medium.**

---

## Checked and not possible as described

**Direct submission to Springer / IEEE / National Geographic.**
IEEE and Springer accept manuscripts only through editorial systems
(ScholarOne, Editorial Manager, Snapp) that publish **no third-party submission
API**; submission requires a human in their portal. Springer Nature's public
APIs are for *reading* metadata, not depositing work. National Geographic
commissions through editors, with no API.

A button labelled "submit to IEEE" could not work. Item 4 above is the honest
version of the same goal.
