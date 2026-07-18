# FAMtastic Designs — Prospect → Paid → Site Studio Pipeline (V1)

**Status:** Phase 1 complete (assessment + plan). Phase 2 (implementation) not started — awaiting go-ahead.
**Branch:** `feat/famtastic-prospect-pipeline-v1`
**Working project:** `sites/site-famtastic-designs/v2` (headless Drupal 11 + React 18 SPA)
**Author:** Implementation engineering, FAMtastic Designs
**Date:** 2026-07-17

---

## 0. How to read this document

This is the Phase 1 deliverable required before any code is written. It separates
**confirmed facts** (verified by direct inspection of the repository and the live site)
from **assumptions / decisions** (choices this plan proposes). Every confirmed fact cites
a file path. Every proposal explains why it fits the existing project.

---

## 1. Current-state assessment (confirmed facts)

### 1.1 There are two different things called "v2" — do not conflate them

| Thing | What it is | Where | Role |
|---|---|---|---|
| **The `v2/` directory** (this project) | Headless **Drupal 11** JSON:API backend + **React 18 / Vite** SPA | `sites/site-famtastic-designs/v2/` | The **architecture foundation** we build on. Currently a Phase-1 evaluation scaffold. |
| **The live site** | A **Nuxt / Vue** marketing app | git root `sites/site-famtastic-designs/` (`pages/*.vue`, `data/famtastic/*.ts`) | Public reference for messaging/offers only. **Not** the source architecture. |

The assignment's "Drupal" framing is **correct for `v2/`**. The Nuxt app is the outer marketing
site and is out of scope except as a content/offer reference.

**Confirmed:** the entire `v2/` tree is currently **untracked in git** (`git status` shows `?? v2/`).
Nothing in `v2/` is committed yet.

### 1.2 Backend — headless Drupal 11 (`v2/backend`)

Confirmed from `backend/composer.json`, `backend/setup.sh`, `backend/phase2-setup.sh`,
`backend/web/sites/default/settings.php`, `backend/web/sites/default/services.yml`:

- **Drupal 11**, docroot `backend/web`, PHP ≥ 8.3, Drush 13. Install profile: `standard`.
- **Contrib on disk:** `admin_toolbar`, `consumers`, `jsonapi_extras`. **`simple_oauth` is declared in
  composer but NOT installed on disk** — the OAuth flow is *scaffolded, not active*.
- **Enabled (per `setup.sh`):** `jsonapi`, `serialization`, `rest`, `jsonapi_extras`, `consumers`,
  `admin_toolbar`; `navigation` uninstalled. Custom admin theme `famtastic_admin` (dark Claro sub-theme).
- **No custom modules.** `backend/web/modules/custom/` does not exist.
- **`config/sync/` is EMPTY** (only `.gitkeep`/`.htaccess`; no `core.extension.yml`). Active config
  lives only in the installed SQLite DB. Config export/import is wired but unused.
- **Content model exists as UN-IMPORTED YAML** at `backend/config/phase2/`: node types `client_project`,
  `service_package`, `testimonial` with fields. May or may not be imported into the running DB.
- **Database:** `settings.php` defaults to **SQLite** (`web/sites/default/files/.ht.sqlite`);
  a `DB_*` env override selects MySQL/MariaDB; a `PLATFORM_RELATIONSHIPS` branch handles Platform.sh.
- **Private files:** `../private` (outside docroot), hardened deny-all `.htaccess`. This is also where
  OAuth RSA keys would be generated.
- **CORS** (`services.yml`): enabled, `allowedOrigins: http://localhost:5173`, `supportsCredentials: true`,
  methods `GET/POST/PATCH/DELETE/OPTIONS`.
- **No test framework.** No `require-dev`, no `phpunit.xml`, no project `tests/`.
- **No lead / prospect / order / payment / Stripe / intake / campaign / customer / Site Studio code
  anywhere** in the backend. This is greenfield.

### 1.3 Frontend — React 18 SPA (`v2/frontend`)

Confirmed from `frontend/src/**`, `frontend/vite.config.js`, `frontend/.env`:

- **React 18.3 + react-router-dom 6.26 + Vite 5.4.** No state library, no form library, no data-fetch
  library — plain Context + `fetch`.
- **Routes:** `/` (Home), `/content/:type`, `/node/:uuid`, `/login`, `/admin` (protected). Catch-all → `/`.
- **API client `src/api/drupal.js`:** anonymous JSON:API reads (with graceful stub fallback) + an OAuth
  **password-grant** flow (`POST /oauth/token`, client_id `famtastic_spa`) with tokens in **localStorage**
  (code comments flag the XSS risk). Authenticated `client_project` CRUD helpers exist.
- **Auth `src/auth/UserContext.jsx`:** restores/refreshes token on mount; user email derived from the
  stored token bundle (no `/me` fetch).
- **`AdminDashboardPage`** does `client_project` list/create/delete against JSON:API.
- **Design tokens** (`src/index.css`, 788 lines, hand-written): dark theme, `--fam-bg:#0a0a0a`,
  `--fam-surface:#141414`, lime accent `--fam-lime:#7cfc00`, Inter font, BEM class names.
- **Vite dev proxy covers `/jsonapi` only — NOT `/oauth`** (a gap for the current auth flow).
- **`.env` and `.env.example` are byte-identical**; both contain only non-secret local placeholders
  (`VITE_DRUPAL_BASE_URL`, `VITE_OAUTH_CLIENT_ID`).
- **No prospect / lead / order / payment / intake / offer concept** anywhere in the frontend.

### 1.4 Deployment & docs

- `docker-compose.yml` (MariaDB + backend + frontend) and `.platform.app.yaml` (Platform.sh) exist but
  are **explicitly untested** ("NOT yet tested" in their own headers).
- `v2/README.md` / `PHASE2_PLAN.md` describe the scaffold and a planned OAuth phase; both confirm no
  hardening/auth/tests yet.

### 1.5 Live-site offers (confirmed reference, from `data/famtastic/packages.ts` + live fetch)

- Live one-time packages: **Starter $1,999**, Business $3,999, Premium $6,999, Landing Page (quote).
- Live monthly plans: Care **$149/mo**, Growth $299/mo, Partner $599+/mo.
- Every CTA funnels to `/get-started`, which emails a manual project request (`mailto` fallback). **No
  live checkout, no live booking, no proven CRM capture.**
- **There is no $199 offer anywhere** (live site, `packages.ts`, `pricing.ts`, or strategy docs).

---

## 2. Key facts vs. the assignment (call-outs)

1. **The $199 "basic website" offer is new.** It does not map to any existing package. The spec
   explicitly authorizes it as the first proof scenario, so this plan **creates a `basic_199` proof
   package** used only by the prospect pipeline. It is intentionally separate from the live $1,999+
   ladder. *(Open question 8.1: is $199 a proof-only price or a public offer? Plan assumes proof-only.)*
2. **The prospect is NOT a Drupal user.** They authenticate with a secret link token, not OAuth.
   This means the whole customer-facing flow needs **no `simple_oauth`** — a major simplification.
   OAuth stays optional and is only relevant for Fritz's staff access (and the existing Drupal admin UI
   already covers staff needs for V1).
3. **`config/sync` is empty and there are no custom modules.** The clean path is a new custom module
   with code-defined content entities; almost nothing depends on the un-imported `config/phase2` model.

---

## 3. Proposed data model

**Decision: a custom Drupal module `famtastic_pipeline` defining four custom _content entities_.**

### 3.1 Why custom content entities (not nodes, not config entities)

- **Not nodes:** nodes carry publishing workflow, revisions, path aliases, and are exposed through
  content listings / JSON:API by default — wrong for PII-bearing transactional records that must never
  be publicly enumerable. The security requirements ("no guessable IDs", "prevent cross-prospect
  viewing", "internal notes hidden") are far easier to guarantee on dedicated entities with their own
  access handler and their own storage tables.
- **Not config entities:** those model configuration, not per-record runtime data.
- **Custom content entities** give typed field schema, dedicated tables, precise access control, and no
  accidental JSON:API/content exposure. This is the idiomatic Drupal pattern for application data and
  fits a project that currently has *no* domain model to disturb.

### 3.2 The four entities (1:1-linked for the proof slice)

```
prospect ─1:1─ order ─1:1─ intake ─1:1─ project
```

Separation mirrors real transaction records: an immutable **order** distinct from mutable **prospect**
data; an **intake** gated behind payment; a **project** as the fulfillment/delivery record. All four are
linked by entity reference and, in the proof slice, are one-to-one.

**`prospect`** — discovered business + outreach + confirmation + token
- Discovered (source = `public`, unverified until confirmed): `business_name`, `business_category`,
  `business_description`, `address`, `service_area`, `public_phone`, `public_email`, `website_url`,
  `hours`, `social_links` (JSON).
- Outreach/campaign: `campaign`, `source` (google/directory/referral/social/…), `discovered_at`,
  `discovery_notes` (**internal, never exposed to prospect**).
- Secure link: `token_hash` (SHA-256 of the raw token — raw token is never stored), `token_expires`,
  `token_revoked` (bool).
- Confirmation / lead: `contact_name`, `contact_method` (email/phone/text), `contact_value`,
  `authorized` (bool — "I am authorized to represent this business"), `confirmed_at`.
- Provenance: `field_confirmed` flag per confirmable field is modeled as a `confirmed_fields` JSON map so
  we preserve "public-source vs owner-confirmed" status (security requirement).
- Lifecycle: `status` — `new → viewed → confirmed → lead → paid → intake_started → intake_complete →
  submitted_to_studio → proof_ready → revision_requested → approved → launched`.

**`order`** — the purchase record
- `prospect_ref`, `package` (`basic_199`), `amount` (19900), `currency` (`usd`),
  `stripe_checkout_session_id`, `stripe_payment_intent_id`, `payment_status`
  (`pending/paid/failed/refunded`), `paid_at`, `created`.

**`intake`** — the website questionnaire + assets (only writable after `order.payment_status = paid`)
- All "Minimum information to structure for Site Studio" fields (see §7 schema), plus
  `asset_refs` (references to Drupal managed `file` entities), `asset_ownership_confirmed` (bool),
  `submitted_at`.

**`project`** — the Site Studio / delivery record (Fritz-facing)
- `prospect_ref`, `order_ref`, `intake_ref`, `studio_brief` (long text, human brief),
  `studio_json` (JSON, machine request), `studio_job_id`, `repo_url`, `proof_url`,
  `delivery_status` (`draft → request_generated → submitted → proof_delivered → revision → approved →
  launched`), `revision_notes`, `approval_status` (`pending/revision_requested/approved`), `approved_at`.

**Not a separate entity: "lead" and "Site Studio request."** A *lead* is simply a `prospect` whose
`status ≥ lead` (confirmation captured). The *Site Studio request* is a **generated artifact** stored on
`project` (`studio_brief` + `studio_json`) plus an export endpoint — no extra table needed.

---

## 4. Proposed customer journey (V1 slice)

1. FAMtastic creates a `prospect` (admin UI / drush) from public info; system issues a one-time secret
   link `https://<frontend>/p/<token>`.
2. Owner clicks the link → SPA `ProspectLanding` calls `GET /api/pipeline/session?token=…` → sees the
   discovered business info. `status → viewed`.
3. Owner confirms/corrects business info, enters contact name + method, checks the **authorization**
   box → `POST /api/pipeline/confirm`. `status → confirmed → lead`.
4. Owner reviews the **$199 basic website** offer → clicks Pay → `POST /api/pipeline/checkout` creates a
   Stripe **test-mode** Checkout Session → browser redirects to Stripe.
5. Stripe redirects back to `/p/<token>/return`; the SPA calls `GET /api/pipeline/order-status`, which
   **verifies server-side** (webhook-confirmed or live Stripe retrieve). Payment is only trusted from the
   server. `status → paid`.
6. Paid unlocks the **intake** form + asset upload → `POST /api/pipeline/intake` + `/api/pipeline/asset`.
   `status → intake_complete`.
7. Fritz (admin) generates the Site Studio request from the intake → `project.studio_brief` +
   `studio_json`; exports it. `status → submitted_to_studio` (manual in V1).
8. Fritz records `studio_job_id` / `repo_url` / `proof_url` on the project → SPA shows the proof to the
   owner → owner **approves** or **requests one revision** via `POST /api/pipeline/approval`.
9. Fritz marks launched. Records preserved throughout.

Steps that stay **manual in V1** (allowed by spec): discovery, choosing who gets outreach, sending the
outreach, reviewing the lead, triggering Site Studio, deploying/verifying the proof, domain connection,
final launch.

---

## 5. API & route plan

### 5.1 Public, token-scoped (served by `famtastic_pipeline` at `/api/pipeline/*`)

| Method | Path | Purpose | Guard |
|---|---|---|---|
| GET | `/api/pipeline/session` | Prospect-safe view + order/intake status | valid token |
| POST | `/api/pipeline/confirm` | Save corrections + contact + authorization → lead | valid token |
| POST | `/api/pipeline/checkout` | Create Stripe test Checkout Session, return URL | token, status ≥ lead |
| GET | `/api/pipeline/order-status` | **Server-verified** payment status | valid token |
| POST | `/api/pipeline/stripe/webhook` | Signature-verified fulfillment | Stripe signature |
| POST | `/api/pipeline/intake` | Save intake | token, **order paid** |
| POST | `/api/pipeline/asset` | Managed file upload → file ref | token, **order paid** |
| POST | `/api/pipeline/approval` | Approve / request one revision | token, proof_ready |

Every non-webhook endpoint re-hashes the presented token, loads the owning prospect, and scopes all reads
/writes to that prospect — **prospect A can never touch prospect B** (security requirement).

### 5.2 Staff/admin (existing Drupal auth / OAuth — no new SPA required for V1)

- Entity list/add/edit UIs at `/admin/famtastic/{prospect,order,intake,project}` (Drupal `EntityListBuilder`
  + forms; permission-gated).
- A "Generate Site Studio request" operation on `project` (builds brief + JSON).
- `GET /api/pipeline/studio-export/{project}` (permission-gated) → downloadable brief + JSON.

### 5.3 Frontend routes (React SPA additions)

- `/p/:token` → ProspectLanding (session → confirm → offer → pay).
- `/p/:token/return` → payment verification → unlock intake.
- `/p/:token/intake` → IntakeForm + asset upload.
- `/p/:token/status` → proof review + approve/request-revision.
- Reuse existing dark/lime design tokens. Add `/api` and `/oauth` to the Vite dev proxy.

---

## 6. Security approach

- **No prospect IDs in URLs.** Access is via a cryptographically random token (≥ 32 bytes, base64url).
- **Token stored hashed** (SHA-256) at rest as `token_hash`; the raw token exists only in the outreach
  link. A DB leak cannot replay links.
- **Expiration + revocation:** `token_expires`, `token_revoked`.
- **Per-prospect scoping** on every request prevents cross-prospect access.
- **Intake gating:** intake/asset endpoints require `order.payment_status = paid` (server-checked).
- **Never trust the browser success redirect.** Fulfillment is driven by the Stripe **webhook**
  (signature-verified with `STRIPE_WEBHOOK_SECRET`) and/or a server-side Checkout Session retrieve.
- **Server-side validation/sanitization** of all public form data in the module (SPA is untrusted).
- **Provenance preserved:** discovered fields marked source=public and unverified until owner-confirmed.
- **Internal notes never serialized** into any prospect-facing payload.
- **No secrets in repo:** Stripe keys, webhook secret, email creds, Site Studio creds come from env →
  Drupal `Settings`/`State`. `.env.example` documents keys with placeholder values only.
- **Intake never collects** domain/email/social/hosting passwords (only registrar *name*, existing
  domain, existing URL — never credentials).
- **Token-based, not OAuth,** for prospects → no localStorage token, no XSS-readable long-lived secret for
  the customer flow.
- *Documented future hardening:* exchange the link token for a short-lived signed JWT scoped to the
  prospect; rate-limit public endpoints; `hook_entity_access` for staff roles.

---

## 7. Site Studio handoff structure

**Adapter boundary:** `SiteStudioAdapterInterface`. V1 ships a `FileExportAdapter` (writes/returns the
JSON + human brief for manual copy or download). Future `ApiAdapter` / `QueueAdapter` / `McpAdapter` /
`FileDropAdapter` implement the same interface — **no assumption about Site Studio's real contract.**

The builder (`SiteStudioRequestBuilder`) turns an `intake` into two representations:

**Machine JSON** (versioned):
```json
{
  "schema_version": "1.0",
  "project_id": "…", "customer_id": "…", "package": "basic_199",
  "business": { "name","category","description","address","service_area",
                "public_contact": {"phone","email","website"}, "hours", "social_links": [] },
  "positioning": { "ideal_customer","customer_problem","desired_outcome",
                   "primary_goal","primary_cta","secondary_cta" },
  "content": { "services": [], "about","differentiators": [], "credentials": [],
               "testimonials": [], "faqs": [], "required_sections": [], "avoid": [] },
  "brand": { "logo_asset","photos": [], "colors": [], "style_preferences","reference_sites": [] },
  "assets": [ { "type","filename","url","owner_confirmed": true } ],
  "domain": { "existing_domain","registrar","existing_website" },
  "constraints": { "info_to_avoid","asset_ownership_confirmed": true },
  "approvals": { "customer_approval_status":"pending" }
}
```

**Human brief:** a Markdown rendering of the same data for a person or an agent prompt.

This covers every field in the assignment's "Minimum information to structure for Site Studio."

---

## 8. Risks & open questions

1. **$199 price point** contradicts the live $1,999 ladder. Plan treats it as a proof-only package.
   *Confirm intent before public exposure.*
2. **Site Studio's real API/contract is unknown** → mitigated by the adapter boundary; V1 is manual export.
3. **Drupal is not currently installed with these entities** and `simple_oauth` isn't installed. The
   prospect flow avoids OAuth, but installing the custom module + running the site needs drush + a DB.
   Local-run instructions will be provided; the SQLite default keeps setup zero-config.
4. **Email creds absent** → default `LogMailer` (writes intended mail to Drupal log); SMTP/transactional
   is a documented config boundary using `support@famtasticdesigns.com`.
5. **Stripe dependency:** plan uses Drupal's bundled Guzzle `http_client` to call Stripe's REST API
   directly (no new SDK), keeping composer light while preserving a clean gateway interface.
6. **`config/sync` empty:** entity definitions are code, not config, so this mostly doesn't bite; any new
   settings config for the module will be exported.
7. **Two divergent pricing files** on the Nuxt site (`packages.ts` vs `pricing.ts`) — noted, **out of
   scope** (non-goal), flagged for later cleanup.
8. **No existing test framework** → plan adds `drupal/core-dev` (dev-only) and unit tests for the pure
   services (token hashing, Site Studio JSON builder, payment-status gating). Confirm this is acceptable.

---

## 9. Definition of done (V1 slice)

The slice proves the customer-facing transaction when, locally:

- A `prospect` can be created and issued a secure one-time link.
- The link renders the discovered business info; the owner can confirm/correct it, provide contact +
  authorization, and become a **lead**.
- The **$199** offer is presented and paid via Stripe **test-mode** Checkout, with payment status proven
  **server-side** (webhook / retrieve), never by the browser redirect.
- Payment unlocks a structured **intake** with asset upload.
- The intake produces a **Site Studio request** in both **human brief** and **machine JSON** form.
- A **project** record holds the studio job id, repo, proof URL, and status, and captures the owner's
  **revision or approval**.
- One prospect can never access another's data; no secrets are committed; internal notes are never
  exposed to the prospect.
- Backend unit tests pass; `vite build` succeeds; commands + results are reported.

---

## 10. Explicit non-goals

Not built in V1 (per spec): a Site Studio replacement; a crawler / Google scraper; a full CRM or
marketing-automation suite; automatic domain purchase / registrar-credential collection / DNS changes;
the 1,000-project platform; every FAMtastic product; **production** Stripe payments; a production
deployment; a full visual rebrand beyond the screens implemented; any television-show content;
reconciliation of the live Nuxt site's divergent pricing files.

---

## 11. Implementation sequence (checkpoint commits)

0. **Baseline** — commit the existing untracked `v2/` scaffold on the feature branch (clean diff base).
1. **Module skeleton** — `famtastic_pipeline` (info.yml, permissions, service wiring).
2. **Entities** — `prospect`, `order`, `intake`, `project` (+ install, +admin list/forms).
3. **Token + prospect session** — issuance (drush command), hashed storage, `GET /session`, `POST /confirm`.
4. **Offer + Stripe test checkout** — `POST /checkout`, `GET /order-status`, `POST /stripe/webhook`
   (Guzzle gateway behind `PaymentGatewayInterface`).
5. **Intake + assets** — `POST /intake`, `POST /asset` (payment-gated).
6. **Site Studio builder + export** — brief + JSON, admin action, `GET /studio-export`.
7. **Approval/revision** — `POST /approval`, project status.
8. **Frontend** — `/p/:token` flow screens; Vite proxy fix; reuse design tokens.
9. **Tests + docs** — unit tests, env docs, run instructions, final report.

Each numbered step is a logical checkpoint commit. No push, no deploy, no merge.
