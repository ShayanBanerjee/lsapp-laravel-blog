# Inkfathom — roadmap

Status: 19 Aug 2026. **286 tests / 12,623 assertions, green.** Everything in
"Built" works and is covered.

The product thesis, in one line: **the passage is the atom, and writers quit
because of silence.** Every feature below should trace back to that or to
paying the people who write. Anything that does not is decoration.

---

## The business

| | |
|---|---|
| **Reading** | Free. Always. It is what makes the writing shareable, indexable and worth doing. |
| **Premium** (writers) | All six universes, unlimited personas, custom themes, short handles, the research studio. |
| **Contributions** | Readers tip a piece or take a monthly membership in a voice. |
| **Platform share** | **25% of gross**, and card fees come out of *our* half — see below. |
| **Ads** | Free tier only, never inside a reading view, never serialized into an entitled reader's page at all. |

### Why the fee is structured this way

The platform pays payment processing out of its own share rather than deducting
it first. On a £1 tip, taking processing off the top would leave the writer with
under half of a gesture the reader thought was whole, and small contributions
would stop being worth making. Rounding also favours the writer: where 25% does
not divide into whole pence, the spare penny goes to the person who wrote the
thing.

Both properties are enforced in `RevenueSplit` and asserted exhaustively — the
split reconciles exactly at every amount from 1p to £100, and the writer's share
is never negative at any configured rate.

---

## Built

### The reading and community layer
Seven DB-driven universes · personas · **highlights** (mark a passage, not a post)
· responses anchored to the passage they answer · private letters · circles ·
the Deep Field · library · 12 subjects · reader typography incl. Atkinson
Hyperlegible · narrow-lexicon moderation that protects strong disagreement ·
public `/@handle` profiles · following feed · aggregated activity alerts.

### Writing and publishing
TipTap editor · **scrollytelling blocks** (pinned image, step-through,
before/after, data callout) · **courses** with a persistent contents panel and
per-reader progress · SSR · server-rendered OG/Twitter/JSON-LD incl. `Course`
and `ProfilePage` · sitemap, robots, RSS · **copy detection** (shingling +
SimHash) · email-verification gate on publishing.

### Money
Tips · monthly memberships · exact-arithmetic 25% split · three-way balance
(lifetime / clearing / available) · chargeback hold · payout threshold ·
earnings dashboard that states the platform's cut in full.

### Taking work elsewhere
Markdown+YAML (Obsidian) · BibTeX · IEEE LaTeX · Word `.docx` written as raw
OOXML with no library · **research studio** producing submission-ready packages
for arXiv, IEEE, ACM, Springer Nature and Nature · Zenodo deposit (real DOIs) ·
ORCID OAuth with checksum validation · Crossref lookup · ten integrations
(Readwise, Notion, Zotero, Ghost, DEV, Hashnode, WordPress, Buttondown, Gist,
Instapaper).

### Craft
Fraunces variable display face driven on its `SOFT`/`WONK`/`opsz` axes ·
magnetic buttons with specular sweep and press physics · every motion primitive
degrades to its finished state under `prefers-reduced-motion` · WCAG AA enforced
server-side on author-made themes **and on every shipped palette** · query
budgets asserted in tests · zero unreferenced frontend modules · a token bridge
reconciling the two design systems, with the failure it prevents recorded in a
test.

### Courses, as a reading experience
Scroll progress · per-module completion · time *remaining* rather than time
total · arrow-key navigation · one-click "mark done and continue" · a real
finishing line on the last lesson.

---

## Recently fixed

- **Invisible text on every auth and settings form.** shadcn primitives read
  light-theme tokens while rendering inside a dark universe: measured contrast
  1.06:1. Fixed with a token bridge rather than per-component patches, so the
  trap is not left set for the next component added. Now 16.78:1.
- **The Deep Field zoom stuttered.** It was React reconciling the stage sixty
  times a second. Now 16.7ms median frame, zero dropped frames.

---

## Next

Ordered by what actually compounds.

### 1. Real billing — **the only thing between this and revenue**
`UpgradeController::activate()` still flips a flag with no payment taken, and
disables itself the moment a live Stripe key is present. Contributions settle
inline in demo mode. What is needed: Stripe Checkout for premium, Stripe Connect
for writer payouts, and a webhook that calls the existing
`Contributions::settle()`. The ledger, the split, the hold period and the payout
threshold are all built and tested — this is wiring, not design.

**Effort: medium. Blocks everything commercial.**

### 2. Organisations and teams
Industry publishing needs shared ownership: an org owns personas and courses,
members hold roles, and billing is per seat. Also unlocks SSO (SAML/OIDC), which
is the first question every enterprise buyer asks.

**Effort: large.**

### 3. Moderation and trust console
Copy flags, moderation verdicts and letters are all recorded and there is no
reviewer-facing surface, because there is no staff role. Needs: roles, a queue,
an audit log of every moderator action, and appeals. Ship before scale, not
after — retrofitting an audit log onto a year of decisions is not possible.

**Effort: medium.**

### 4. Reader-side discovery that is not a ranking algorithm
The trending signal is marks per passage, which is honest and currently
under-used. A "what stopped people this week" surface, per subject and per
circle, would be the discovery mechanism most platforms fake with engagement
scores.

**Effort: small-medium.**

### 5. Paid posts and previews
Memberships exist; per-piece unlocks do not. A writer should be able to put one
essay behind their membership while everything else stays free — with the first
N paragraphs always public, so the piece is still indexable and still shareable.

**Effort: medium.**

### 6. Collaborative drafting
Co-authors, suggestions, and a comment layer on drafts. The highlight primitive
already does most of the anchoring work; this is the same mechanism pointed at
unpublished text.

**Effort: large.**

### 7. Web-wide copy detection
The local index compares against everything published here and nothing else.
Going wider needs a third-party index (Copyleaks, Originality.ai): a per-check
cost, and an outbound copy of unpublished text. `CopyDetector` is an interface
bound in the container precisely so this is a configuration decision.

**Effort: small to integrate, ongoing cost.**

### 8. Native reading apps
The reading view, typography settings and marks are the whole product; offline
reading plus marks that sync is the natural mobile shape.

**Effort: large.**

### 9. Writer analytics beyond marks
Which passage lost readers, not just which one landed. Scroll-depth per block,
aggregated and non-identifying. Must not become a view counter — the entire
point is that this platform does not measure attention that way.

**Effort: medium.**

### 10. Grants and commissioning
Once contributions have a track record, the platform can fund work directly:
open calls per subject, reader-funded pots, matched funding. This is the piece
that turns a payments feature into a reason to be here.

**Effort: large. Do it last, and only once #1 is real.**

---

## Checked and not possible as described

**Direct submission to IEEE / Springer / Nature.** All of them receive
manuscripts exclusively through editorial systems — ScholarOne, Editorial
Manager, Snapp, eJournalPress — and none publishes a third-party submission API.
A "submit to IEEE" button cannot work, and one that appeared to would be worse
than none.

What is built instead is the honest maximum, and it removes the part that
actually costs a day: the research studio produces the manuscript in the
publisher's own LaTeX class, the metadata their portal asks for field by field,
a cover-letter draft, and a checklist from their author guidelines — then links
straight to the portal a human uploads it to. Zenodo (real deposit API, real
DOIs), ORCID (real OAuth) and Crossref (real lookup) are wired because those
*do* exist.

**Medium and Pocket integrations.** Medium withdrew its publishing API in 2023;
Pocket shut down in 2025. There is nothing to integrate with.

**Obsidian sync.** No cloud API exists. The integration is Markdown export with
YAML frontmatter, which a vault reads directly.
