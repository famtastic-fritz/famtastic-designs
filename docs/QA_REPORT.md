# QA Report

## 2026-06-25 — FAMtastic Designs public rescue preflight

### Scope
Public marketing rescue only for `site-famtastic-designs`.
Backend branch was explicitly excluded from deploy consideration.

### Branches
- Source proof branch: `famtastic/site-v1-production-proof`
- Working rescue branch: `famtastic/prod-public-rescue`
- Backend branch skipped: `famtastic/backend-v1-directus-paypal`

### Build checks
- `pnpm install` ✅
- `pnpm typecheck` ✅
- `pnpm lint` ✅
- `pnpm build` ✅

### Local production-safe preview checks
Preview command used:
`HOST=127.0.0.1 PORT=3001 ENABLE_ADMIN_PROOF=false NUXT_PUBLIC_ENABLE_ADMIN_PROOF=false NUXT_PUBLIC_ENABLE_PAYMENT_PROOF=false NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001 NUXT_PUBLIC_CMS_MODE=local NUXT_PUBLIC_LEAD_STORAGE_MODE=local NUXT_PUBLIC_LEAD_CAPTURE_MODE=manual NUXT_PUBLIC_PAYMENT_MODE=mock NUXT_PUBLIC_PORTAL_MODE=preview BOOKING_PROVIDER=manual node .output/server/index.mjs`

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
- `/admin-proof` returns 404 ✅

### Safety findings
- Admin proof content no longer applies automatically when the proof flag is off.
- Static `public/robots.txt` was removed so the guarded route wins.
- `robots.txt` now disallows `/admin-proof` and `/payment-proof`.
- Portal pages present access/info posture without claiming real auth is active.
- Consultation forms now use manual email-draft fallback instead of pretending a backend submission pipeline is live.
- Payment/booking CTAs route into safe consultation paths instead of exposing fake checkout or dead booking links.
- Cookie banner rendered and dismissed in browser automation.

### Known limitations
- Live deployment not executed because authenticated production host access was unavailable.
- Production lead handling is intentionally manual fallback for this rescue pass.
- Directus, real portal auth, live PayPal, live scheduler integration, and production email automation remain pending backend work.

### Verdict
Local public rescue build is safe enough for production cutover once host access is authenticated and a rollback backup is created first.