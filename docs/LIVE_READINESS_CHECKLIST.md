# Live Readiness Checklist

## 2026-06-25 — Public rescue readiness pass

Status: local rescue build is ready for authenticated production cutover; live deployment is still blocked by missing verified host access.

### Passed locally
- Homepage loads
- Services loads
- Pricing loads
- Packages loads
- Work loads
- Contact loads
- Get Started loads
- Portal loads with access-info posture
- Client Portal Login loads with invitation-only posture
- Thank You loads
- Privacy Policy loads
- Terms of Service loads
- Cookie Policy loads
- Sitemap page loads
- `sitemap.xml` loads
- `robots.txt` loads
- `/admin-proof` returns 404 when `ENABLE_ADMIN_PROOF=false`
- Cookie banner renders and dismisses
- Public CTA links for consultation/audit route safely to `/get-started` paths
- Form posture is manual fallback, not fake success

### Verified production-safe posture in this branch
- No public `/admin-proof` access by default
- No public fake PayPal checkout
- No public fake booking links
- No fake portal-auth claims
- No dependency on Directus for the public rescue site
- No requirement to deploy `.env`, `.data`, Directus DB, uploads, or `node_modules`

### Remaining blockers before live
- Authenticated production deployment lane not verified from this session
- No live backup path created yet because host access is unavailable
- Live form-delivery behavior is intentionally manual fallback until a real production capture/notification lane is enabled

### Required production env posture for cutover
- `NUXT_PUBLIC_SITE_NAME=FAMtastic Designs`
- `NUXT_PUBLIC_SITE_URL=https://famtasticdesigns.com`
- `NUXT_PUBLIC_CMS_MODE=local`
- `NUXT_PUBLIC_LEAD_CAPTURE_MODE=manual`
- `NUXT_PUBLIC_PAYMENT_MODE=mock`
- `NUXT_PUBLIC_PORTAL_MODE=preview`
- `ENABLE_ADMIN_PROOF=false`
- `NUXT_PUBLIC_ENABLE_ADMIN_PROOF=false`
- `NUXT_PUBLIC_ENABLE_PAYMENT_PROOF=false`
- `BOOKING_PROVIDER=manual`

### Decision
Do not cut over live production until the GoDaddy/cPanel/host lane is authenticated and a rollback backup is created first.