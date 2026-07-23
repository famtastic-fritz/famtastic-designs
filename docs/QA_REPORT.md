# QA Report

## 2026-07-06 — FAMtastic Designs production stabilization verification

### Scope
Public marketing/stabilization lane only for `site-famtastic-designs`.
Backend proof, Directus runtime, real portal auth, and live payment proof remain outside deploy scope unless they create public risk.

### Branches
- Proof base branch: `famtastic/site-v1-production-proof`
- Earlier rescue branch: `famtastic/prod-public-rescue`
- Current stabilization branch: `famtastic/prod-stabilization-tv-review`
- Backend branch still excluded from public deploy consideration: `famtastic/backend-v1-directus-paypal`

### Build checks
- `pnpm install` ✅
- `pnpm typecheck` ✅
- `pnpm lint` ✅
- `pnpm build` ✅

### Code-level fixes verified in this pass
- `data/famtastic/site.ts` now uses `hello@famtasticdesigns.com` instead of the mismatched `.co` address.
- `components/ShortConsultationForm.vue` no longer hardcodes a stale email address and now resolves the consultation mailto target from site content.
- `docs/PRODUCTION_STABILIZATION_AUDIT.md` now records the live-vs-local gap and deploy-lane block truth.
- `docs/PRODUCTION_FORM_BEHAVIOR.md` now records the real public form/admin/payment behavior instead of implied behavior.

### Local production-safe preview checks
Preview command used:
`HOST=127.0.0.1 PORT=3001 ENABLE_ADMIN_PROOF=false NUXT_PUBLIC_ENABLE_ADMIN_PROOF=false NUXT_PUBLIC_ENABLE_PAYMENT_PROOF=false NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001 NUXT_PUBLIC_CMS_MODE=local NUXT_PUBLIC_LEAD_CAPTURE_MODE=manual NUXT_PUBLIC_PAYMENT_MODE=mock NUXT_PUBLIC_PORTAL_MODE=preview BOOKING_PROVIDER=manual node .output/server/index.mjs`

Verified locally:
- `/` ✅
- `/services` ✅
- `/pricing` ✅
- `/packages` ✅
- `/work` ✅
- `/contact` ✅
- `/get-started` ✅
- `/portal` ✅
- `/client-portal-login` ✅
- `/thank-you` ✅
- `/privacy-policy` ✅
- `/terms-of-service` ✅
- `/cookie-policy` ✅
- `/sitemap` ✅
- `/sitemap.xml` ✅
- `/robots.txt` ✅
- `/admin-proof` returns `404` when proof is disabled ✅

### Safety findings
- Local robots output now disallows `/admin-proof` and `/payment-proof`.
- Local legal/privacy routes are present and load correctly.
- Public booking/payment links remain safe-fallback routes in mock/manual posture.
- Consultation forms stay in manual mode unless API mode is intentionally enabled.
- Root/contact pages now expose `hello@famtasticdesigns.com` consistently.
- Admin-proof content is not publicly available in the production-safe preview.

### Live production findings
- `https://famtasticdesigns.com/` returns `200`.
- `https://famtasticdesigns.com/pricing/` returns `200`.
- `https://famtasticdesigns.com/work/` returns `200`.
- `https://famtasticdesigns.com/portal/` returns `200`.
- `https://famtasticdesigns.com/privacy-policy/` returns `404`.
- `https://famtasticdesigns.com/robots.txt` still does not disallow `/admin-proof` or `/payment-proof`.

### Known limitations
- Live deployment was not executed because authenticated production host access is still unavailable in-session.
- Browser access to GoDaddy lands on the sign-in page, not an authenticated dashboard.
- SSH to `xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net` still fails with `Permission denied (publickey,password)`.
- Production lead handling remains intentionally manual fallback for the public stabilization lane.
- Directus, real portal auth, live PayPal, live scheduler integration, and production email automation remain backend-lane follow-up work.

### Verdict
The stabilization branch is locally build-clean and production-safer than the current live site.
The remaining blocker is not code quality; it is authenticated deployment access to the real GoDaddy production lane.