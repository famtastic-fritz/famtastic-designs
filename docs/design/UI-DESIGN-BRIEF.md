# FAMtastic UI Design Brief — Command Center + Client Portal
**Prepared for**: Codex (design-generation pass) and any UI agent · **Date**: 2026-08-24 · **Owner**: Fritz · **Author**: fam-ceo lane
**Status**: ACTIVE BRIEF — supersedes the muapi comps in `marketing/design-mockups/2026-08-24/` (those are layout references only; their text is AI gibberish and their information density is toy-level).

---

## 0. Why this redesign exists (the problems we actually hit)

These are real incidents from production, not hypotheticals. A designer who ignores them will re-create them.

| # | Incident | Date | Design lesson |
|---|---|---|---|
| P1 | Five proof-ready projects crushed into auto-fit columns; fixed 3-col concept grids made ~90px cards; titles overflowed, badges scattered | 08-24 | Card grids must be fluid (`auto-fit, minmax`) and one focus-request at a time |
| P2 | "0/4 approved" hardcoded on all 17 calendar days; static times ignored the manifest | 08-24 | Never render invented numbers. Every figure comes from a query or an explicit unknown-state |
| P3 | Attention items were bold text styled as buttons ("Review calendar →") | 08-24 | Action-styled elements must be real links/forms |
| P4 | 14+ badge/icon variants shipped with zero CSS rules | 08-24 | New classes ship with their rules; crawler enforces |
| P5 | Customer emails: links truncated at first "s" by a bad regex; customers couldn't open anything | 08-24 | Email links are real anchors, tested with full query strings |
| P6 | Prospect edit form rendered ZERO fields (Authorized checkbox unreachable) | 08-24 | Every list column must be editable or explicitly read-only-with-why |
| P7 | Synthetic test data ("Production two-way mailbox proof", "controlled customer reply") visible in the owner's real workspace | 08-24 | Test data is severity-1 pollution; crawlers hunt it |
| P8 | Client proof concepts publicly fetchable at guessable URLs | 08-24 | Auth doors are cosmetic unless storage is closed |
| P9 | Paying customers land in the ADMIN THEME at checkout | 08-24 (audit) | The money step is the most designed moment, not the least |
| P10 | "300+ leads waiting" claimed; 31 exist. Catalog advertised 16 SKUs; 14 sellable | 08-24 | Claims reconcile to DB or they don't ship |
| P11 | Stale cached shells rendered unstyled soup; missing assets returned 200-with-HTML | 08-24 | no-cache shells; assets 404 loudly |
| P12 | 8 portal sections unreachable; nav lied about the product's size | 08-24 | Nav = the real IA; dead code is IA debt |

## 1. Brand system (non-negotiable)

- Background near-black `#070907`; panels `#101310`–`#141814` with 1px `#252b25` borders, 18–20px radii
- Signature lime `#7cfc00` — the ONLY accent. Lime = "alive / action / attention"
- Type: Inter. Uppercase letterspaced micro-labels for eyebrows; clamp() display sizes
- Clay-warmth texture accents allowed on customer portal marketing moments (see /portal tutorial art)
- Dark mode only, both surfaces. Mobile: single column, 44px touch targets, sticky action bars

## 2. Innovation directives (bold and fearless, on purpose)

1. **Glow = gaze.** The ONE thing a user must do next gets `box-shadow: 0 0 24px rgba(124,252,0,.35)` + a soft pulse (2s, prefers-reduced-motion respected). Never more than one glowing element per view.
2. **Hover reveals depth.** Cards tilt 1deg / lift 2px on hover; hidden metadata (IDs, UTMs, last-touched) fades in on hover for operators; customers get outcome hints instead ("Choose → proofs lock in 24h" style staging).
3. **Count-up numbers** on KPI cards when they mount (400ms, once). Dead-letter counts count UP in red; zero renders as a calm "0 — clean".
4. **Gates feel like gates.** Approval toggles are chunky physical switches; approving fires a single lime ripple across the row, then the row settles to "approved" state. Revoke asks confirm.
5. **Skeletons that teach.** Loading states show the page's real silhouette (table ghost rows, card ghosts), never spinners alone.
6. **Money is first-class.** Revenue renders in a mono/tabular font; negative adjustments strike through in red.
7. **Micro-copy is plain-language**, second person, no tech-stack names ever (no "Drupal", no "queue table"). We say "your secure request record".
8. **Motion budget**: transforms/opacity only, ≤300ms, stagger ≤60ms/item. If it doesn't guide the eye, cut it.

## 3. Page-by-page specification

### 3.1 ADMIN — Operations Home (`/admin/famtastic`)
**Does**: answers "what needs me in the next hour?" in one glance.
- Hero: today's pulse — leads in, replies waiting, gates open, dead letters (must be 0), renewals due ≤30d, revenue MTD (count-up)
- Cards (tap-through, real links): Prospects, Website Requests, Proof QA queue, Social gate queue, Support drafts, Replies, Paid orders, Revenue by campaign (mini bar list), Renewals due, Workers, Grant codes, Launch approval, Channel health
- **Accept**: every card = live query; crawler asserts no fake arrows; one glow max (on the largest open gate count)

### 3.2 ADMIN — Email Center (`/admin/famtastic/metric/notifications` + compose)
**Does**: total trust in outbound mail + one-click triage.
- Tabs: All / Queued / Retry / Sent / Superseded / Dead-letter
- Row actions: **Retry** (resets attempts→0, status→queued), Inspect (full body drawer), Copy message-id
- Compose drawer: template picker, merge-token chips, test-send to owner
- KPI header: sent today, failure rate sparkline, queue age
- **Problem solved**: P3/P4; today's dead-letters require drush — they must never again
- Mobile: rows collapse to cards; Retry stays thumb-reachable

### 3.3 ADMIN — Marketing / Campaign Gates (`/admin/famtastic/metric/social-records` + calendar)
**Does**: the 17-day campaign as a mission control: per-record Content/Media/Publish gates.
- Keep: chips per request/day, one expanded record
- Add: bulk-approve by day (gated, confirm), asset thumbnail hover-preview, UTM hover-reveal, Postiz draft deep-link per record
- Calendar: real gate counts per moment; day cells glow when a full day is approved and ready to schedule
- **Problem solved**: P2; publish-batch executor consumes approved records (backend work tracked separately)

### 3.4 ADMIN — Proof QA (`/admin/famtastic/metric/proofs-ready`)
**Does**: quality gate before a customer ever sees concepts.
- Three-up browser-framed previews (live iframes), QA checklist with pass/fail ticks (mobile-safe, brand tokens, content complete), watermark badge "OWNER REVIEW"
- Approve-and-notify button with explicit "customer visibility" gate copy
- Reject loop: reason → back to generation with the reason attached to the record

### 3.5 ADMIN — Support / Reply (`/admin/famtastic/metric/support` + reply form)
**Does**: answer customers without leaving the command center.
- Rows: case, customer, subject, SLA countdown (glows when <2h left), status, **Reply** action
- Reply form: quoted thread context, plain-text, outbox-verified send, case auto-flips to waiting-on-customer
- **Problem solved**: audit critical #2 — staffReply() existed with no UI

### 3.6 ADMIN — Services & Renewals (`/admin/famtastic/metric/services`)
**Does**: make recurring revenue visible and actionable.
- Default sort: renewing soonest; filter "due ≤30d"; MRR total card; per-row Renew action (creates renewal draft order, flagged for approval — never auto-charges)

### 3.7 CLIENT PORTAL — Projects (`/portal?start=website`)
**Does**: convert a lead into a saved brief, then into a confident proof choice, then a purchase.
- Request chips → one expanded request. Proof cards = **live mini-preview iframes** (already shipped) + direction name + Choose (glow on the not-yet-chosen state only when quality gate passed)
- Progressive brief: Step 1 (3 fields) → save → 6 grouped fieldsets, sticky save bar, autoscroll
- Empty states teach ("Start with a short brief — concepts, proofs, purchase live here")
- **Problem solved**: P1, and the save-crash (ReferenceError) that made requests invisible
- Mobile: chips scroll horizontally; proof cards single column; sticky bottom bar

### 3.8 CLIENT PORTAL — Proof Review
**Does**: the proof-first moment — the differentiator.
- Cards: live iframe preview (scaled), name, Available pill, Choose; after selection: dimmed alternatives + confirmation panel with next-step copy
- Sandbox note: previews render via auth'd API with CSP `default-src 'none'`; keep iframe sandbox tight

### 3.9 CLIENT — Checkout (the money step) — **NEW DESIGN REQUIRED**
**Does**: make paying feel as polished as everything before it.
- Today: stock Drupal admin chrome (P9 — embarrassing). Target: minimal customer theme (Olivero base or SPA-hosted), order summary card, card element, terms link, coupon field (FAMFOUNDER pattern), success state that celebrates and routes back to portal with confirmation notice
- Mobile-first: this is where small-business owners pay from their truck

### 3.10 CLIENT — Messages/Billing/Settings
- Messages: two-pane, no overflow (fixed), reply via outbox
- Billing: orders with amounts, renewal dates, next invoice preview
- Settings: topic + email preferences (already built), plain-language

## 4. Acceptance (every surface, no exceptions)

1. `scripts/e2e-admin-links.sh` green (37+ surfaces)
2. `scripts/e2e-portal-links.sh` green INCLUDING geometric overlap detection
3. No fake affordances; no invented numbers; no tech-stack names in customer copy
4. One glow max per view; motion respects prefers-reduced-motion
5. Mobile pass: 390px width, no horizontal scroll, 44px targets
6. Test-data strings absent from customer-visible reads

## 5. For Codex — the failed reference (do NOT imitate)

File: `marketing/design-mockups/2026-08-24/portal-projects.png` (also admin-email-center.png, admin-marketing-center.png, admin-proof-qa.png)
Generator: `scripts/muapi-generate-mockups.sh` (flux-schnell via muapi.ai)

The exact prompt that produced the rejected comp:

> "FAMtastic Designs brand system: near-black charcoal background #070907, signature lime green #7cfc00 accent, soft lime glows, Inter typeface, rounded 18px panels with 1px dark borders, subtle clay-warmth textures, dark-mode professional SaaS aesthetic, clean information hierarchy, generous whitespace, desktop 16:10 UI screenshot, crisp legible text labels, no lorem ipsum. Customer PORTAL PROJECTS PAGE for small-business clients of a web-design agency: request switcher chips across the top, one expanded website request with three live website concept preview thumbnails rendered as mini browser windows inside cards, each card has direction name, Available pill, Choose this direction lime button, a progress stepper (Brief, Concepts, Selection, Purchase, Launch), friendly plain-language status copy, a purchase panel showing Web Basics Bundle price with secure checkout button."

Why it was rejected: AI-gibberish text ("Request Reperts", "Custome Projects"), toy information density, no real data rows, generic SaaS layout with no conversion logic. **Codex should treat it as layout mood ONLY** and rebuild against sections 1–4 above with real labels, real field names, and the acceptance criteria. Generate at 1600×1024 (dims must be multiples of 64 for flux endpoints).
