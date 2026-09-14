# Tighten Up Your Locs — LOCAL release candidate

## September14 maintenance (supersedes historical candidate/analytics status below)

Public launch and dedicated Owner Desk are deployed; see Designs release plans for exact evidence. Site-specific rules now live at `../../design.md`. The current maintenance source removes logo underlining, relaxes headline spacing, supplies #booking navigation and a named request section, and preserves the existing owner-reviewed booking backend. #request remains compatible.

The large analytics-choice panel is replaced by a concise disclosure. Existing GA4 G-V8M437DWV0 loads on the exact production apex/www with all analytics/ad storage denied, ads redaction enabled and no URL passthrough. Default cookie-free measurements are not a visitor consent grant or legal-compliance certification. Prior explicit refusal, DNT and GPC block loading. Old GA cookies are removed; no new preference writes. Local previews cannot emit production analytics. Events remain allowlisted and sanitized. Cookie-free pings do not promise full cookie-based session/user reporting or eligibility for GA behavioral modeling. Official reference: https://developers.google.com/tag-platform/security/concepts/consent-mode . Live acceptance is recorded separately from local tests.

This is a technical adaptation of the owner-approved Open Chair / Ruby Signal direction, not a deployed site. No provider writes, emails, charges or live request submissions were performed. Production publication is blocked.

## Package and provenance

Publishable-file allowlist (ONLY after gates): index.html, styles.css, site.js, config.js, assets/archive-private-concept.png.
Never publish this whole directory: test code, screenshots, Build DNA and internal notes are not public assets.
The original proofs remain unchanged. Hybrid palette, type stack, section structure, hero composition and existing media are preserved. The former owner-concept panel becomes the real form; review/operator copy is removed. No portfolio, review, booking, price or calendar claims were invented.
The reused generated hero remains visibly labeled Illustration — not client work. Original source records it as PRIVATE CONCEPT MEDIA: public-use/rights approval remains a launch gate. No new image was generated.
No street address, phone, hours, email or map is published because permission/current public values were not independently verified in this bounded task.
Noindex remains intentional until full launch certification.

## Configuration gates — do not guess or enable from this package alone

- Existing authoritative binding: site-dffd4cb9c3aa47fd, request12/customer11/project5. Do not create another slug.
- Request route: https://famtasticdesigns.com/web/api/booking-request/site-dffd4cb9c3aa47fd
- Availability route: https://famtasticdesigns.com/web/api/booking-availability/site-dffd4cb9c3aa47fd
- requestsEnabled defaults FALSE, endpoints empty. In production source config only noise-cuts is enabled for requests; availability enabled list is null (parent's read-only provider evidence).
- Before filling endpoints/setting requestsEnabled, verify exact owner binding, site allowlist, permitted public origin/CORS or an existing safe same-origin proxy, persisted request response, ownership denial tests, rate limit and error paths.
- Canonical response is ok:true, status:received, reference:UUID. Generic 200/received without reference is NOT saved proof. This avoids treating honeypot quiet-success as a real receipt.
- No automatic retries. Ambiguous server/network outcome disables resubmission and retains form details only in memory. Parent must provide a verified customer contact fallback before launch.
- Backend booking owner APIs exist; a complete authenticated React owner inbox/availability UI and actual customer-request notification delivery remain unverified. This package does not publish the old owner mockup. Do not enable capture until someone can reliably see and respond to records.
- Consent is purpose-limited request processing, not marketing signup. Full approved privacy disclosure (controller/contact, retention, rights handling) is still required; this explanatory UI is not legal approval.

## Analytics

Dedicated actual GA4 ID G-V8M437DWV0 supplied by parent from verified provider setup. Property553918564 / stream15767069359, America/New_York, USD. Enhanced measurement OFF per parent provider evidence.
No Google tag loads before opt-in. Only consent choice is stored locally. After allow, explicit page_view / request_start / request_saved use a constant page title, origin-only location and empty referrer; never input values, query/hash, source page referrer or identifiers.
Decline disables this measurement ID and removes this site's GA cookies; already-sent data cannot be recalled. No advertising consent is granted. Google Fonts requests are independent of analytics; local screenshots deliberately block external requests, so they use fallbacks. Production font rendering requires separate visual review.
Local tests exercise script insertion and sanitized command queue, NOT real GA collection. Live consent/network/Realtime proof remains required.

## Local proof and remaining rollout gates

Node24.19.0 runtime, not repository-target Node22. Five unit tests passed. Headless Chromium tests passed at390/768/1280 with no horizontal overflow, disabled default form, consent transitions, mocked durable receipt, duplicate submit protection, ambiguous-result retry block and retained input. Mobile screenshot inspected; fonts blocked. Browser tests intercept external services and mock request responses. There is no real appointment, message, email or analytics delivery proof.
Screenshots qa-390.png, qa-768.png, qa-1280.png. Run:
node --test <this-directory>/site.test.mjs
node <this-directory>/browser.test.mjs <repository-root>

Before release: customer registrant/ownership approval; safe isolated hosting and DNS; valid renewable HTTPS; mailbox + SPF/DKIM/DMARC and send/receive tests; authorized final public contact/privacy/media; backend and owner workflow; actual GA collection; Node22 checks; independently reviewed mobile/desktop actual-font screenshots; exact committed/pushed source and approved artifact-aware deployment; durable Drupal receipts; verified owner-approved customer launch notification.
CustomerDeploymentService currently renders a generic placeholder, not this source. Do not run it and claim this design deployed.
