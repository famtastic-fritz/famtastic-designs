# Production Form Behavior

## 2026-07-06 — FAMtastic Designs public form/admin/payment behavior truth

### Scope
This note documents what the public site actually does right now in the stabilization lane.
It separates honest current behavior from backend/proof aspirations.

## Runtime switches that control behavior
Defined through `nuxt.config.ts` and consumed by page/components/server routes.

### Public behavior flags
- `NUXT_PUBLIC_LEAD_CAPTURE_MODE`
  - `manual` -> public forms open an email draft and then route to `/thank-you?mode=manual`
  - `api` -> public forms POST to `/api/leads`
- `NUXT_PUBLIC_LEAD_STORAGE_MODE`
  - `local` -> `/api/leads` writes to `.data/famtastic-leads.json`
  - `directus` -> `/api/leads` tries Directus first, then falls back to local storage on failure
- `NUXT_PUBLIC_PAYMENT_MODE`
  - `mock` -> public payment/audit links fall back to safe intake routes
  - non-mock -> payment helpers may use configured PayPal/Stripe URLs
- `BOOKING_PROVIDER`
  - `manual`, `mock`, or blank -> booking resolves to `/get-started`
  - provider-specific values can resolve to configured external booking URLs
- `ENABLE_ADMIN_PROOF` and `NUXT_PUBLIC_ENABLE_ADMIN_PROOF`
  - when false, `/admin-proof` should not be publicly available and local override content should not auto-apply

## Form surfaces

### 1. Short consultation form
File: `components/ShortConsultationForm.vue`
Used on:
- `pages/index.vue`
- `pages/contact.vue`

#### Current behavior
- Captures name, email, phone, business name, service needed, budget, message, UTM fields, referrer, landing page, device type, submitted timestamp, and fixed `form_type=consultation`.
- Reads `leadCaptureMode` from runtime config.
- Manual mode is active unless `leadCaptureMode === 'api'`.

#### Manual mode behavior
- Builds an email body from the entered values plus source-tracking data.
- Opens a `mailto:` draft using the FAMtastic site contact email.
- Immediately routes the browser to `/thank-you?mode=manual`.

#### API mode behavior
- Sends `POST /api/leads` with the form body.
- On success, routes to `/thank-you`.

#### Public production truth
- This is an honest fallback surface, not a proven CRM lane.
- It is acceptable for the public rescue/stabilization lane because it does not pretend there is invisible lead capture when none has been proven.

### 2. Full intake form
File: `pages/get-started.vue`

#### Current behavior
- Captures project type, business details, URLs, goals, timeline, budget, contact data, message, UTM fields, referrer, landing page, device type, submitted timestamp, optional package, and fixed `form_type=full_intake`.
- Reads `leadCaptureMode` from runtime config.
- Manual mode is active unless `leadCaptureMode === 'api'`.

#### Manual mode behavior
- Builds a project-request email body.
- Opens a `mailto:` draft to `site.contactEmail`.
- Routes to `/thank-you?mode=manual`.

#### API mode behavior
- Sends `POST /api/leads`.
- On success, routes to `/thank-you`.

#### Public production truth
- Same posture as the short form: honest fallback, not a proven automated backend.

## Lead ingestion route
File: `server/api/leads.post.ts`

### What it validates
- Requires valid `name` and `email`.
- Requires `service_needed` for consultation requests.
- Requires `business_name` for full intake requests.

### What it stores
Normalized fields include:
- `form_type`
- `project_type`
- `business_name`
- `industry`
- `current_website`
- `social_url`
- `location`
- `goals`
- `timeline`
- `budget`
- `name`
- `email`
- `phone`
- `preferred_contact_method`
- `best_time_to_reach`
- `message`
- `service_needed`
- UTM fields
- `referrer`
- `landing_page`
- `device_type`
- `submitted_at`
- `status`
- `notes`

### Storage behavior
- Default/current safe expectation: local JSON storage.
- Local write target: `.data/famtastic-leads.json`
- If `NUXT_PUBLIC_LEAD_STORAGE_MODE=directus` and `directusUrl` is configured, the route attempts Directus first.
- If Directus write fails, the route logs the failure and falls back to local storage.

### Public production truth
- This route is useful for proof and preview.
- It is not yet proven as a real production lead-notification or CRM-delivery lane.
- Without a verified production host path and a proven writable runtime/storage model, public deployment should not depend on this route alone for business-critical lead capture.

## Booking and payment CTA behavior
File: `app/utils/proof-links.ts`

### Booking link resolution
- `manual`, `mock`, or blank booking provider -> `/get-started`
- Provider-specific config can resolve to external booking URLs only when intentionally configured.

### Audit/payment link resolution
- `paymentMode=mock` -> `/get-started?intent=audit`
- payment options fallback -> `/get-started?intent=payment-options`
- non-mock payment mode can use configured PayPal/Stripe links

### Public production truth
- The stabilization lane avoids fake checkout and fake scheduling.
- CTAs must either go somewhere real or fall back into a human-reviewed intake path.

## Portal/admin/payment proof behavior

### Portal pages
Files:
- `pages/portal.vue`
- `pages/client-portal-login.vue`

Expected posture:
- Informational/access posture only.
- No claims of live authenticated client self-service unless that is actually implemented and verified.

### Admin proof
Files/routes include:
- `pages/admin-proof.vue`
- `server/utils/admin-proof.ts`
- admin-proof API routes

Expected public behavior:
- Must stay unavailable in production unless intentionally enabled for private proof work.
- Local admin override content must not auto-apply when proof mode is off.

### Payment proof
Expected public behavior:
- Must stay hidden from search/public navigation.
- Robots should disallow `/payment-proof`.

## Live-risk summary
1. Manual form mode is honest and safer than pretending invisible automation exists.
2. API form mode is only production-safe when writable runtime, storage target, and notification/follow-up lane are verified on the actual host.
3. Admin-proof and payment-proof must remain blocked from public indexing and public navigation.
4. Deployment must not proceed on assumptions about GoDaddy runtime write behavior; the lane still needs authenticated verification.

## Recommended public production posture right now
- Keep public forms in `manual` mode unless the real production host proves a durable API/storage/notification lane.
- Keep booking in safe intake fallback unless a real booking URL is configured.
- Keep payment CTAs in safe consultation fallback unless a real payment link is configured.
- Keep `/admin-proof` unavailable and `/payment-proof` disallowed.
- Treat Directus/backend/payment work as a separate lane from the public rescue/stabilization lane.