# Production Stabilization Audit

## 2026-07-06 — FAMtastic Designs public lane stabilization audit

### Scope
Public FAMtastic Designs site only.
Backend/Directus/payment proof surfaces remain out of production scope except where they create public risk.

### Repo / branch truth
- Repo: `/Users/famtasticfritz/famtastic/sites/site-famtastic-designs`
- Active branch during audit: `famtastic/prod-stabilization-tv-review`
- Active commit at audit start: `8fe9af18`
- Related branches present:
  - `famtastic/prod-public-rescue`
  - `famtastic/site-v1-production-proof`
  - `famtastic/backend-v1-directus-paypal`

### Re-anchor proof
- `pnpm install` passed.
- `pnpm typecheck` passed.
- `pnpm lint` passed.
- `pnpm build` passed.
- Production-safe local preview started on `http://127.0.0.1:3001` with admin proof disabled and public payment/booking set to safe fallback behavior.

### Local preview configuration used
- `ENABLE_ADMIN_PROOF=false`
- `NUXT_PUBLIC_ENABLE_ADMIN_PROOF=false`
- `NUXT_PUBLIC_ENABLE_PAYMENT_PROOF=false`
- `NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001`
- `NUXT_PUBLIC_CMS_MODE=local`
- `NUXT_PUBLIC_PAYMENT_MODE=mock`
- `NUXT_PUBLIC_PORTAL_MODE=preview`
- `BOOKING_PROVIDER=manual`

### Live vs local route comparison

#### Live production checks
- `https://famtasticdesigns.com/` -> `200`
- `https://famtasticdesigns.com/pricing/` -> `200`
- `https://famtasticdesigns.com/work/` -> `200`
- `https://famtasticdesigns.com/portal/` -> `200`
- `https://famtasticdesigns.com/privacy-policy/` -> `404`
- `https://famtasticdesigns.com/robots.txt` -> `200`

#### Local preview checks
- `http://127.0.0.1:3001/` -> `200`
- `http://127.0.0.1:3001/pricing` -> `200`
- `http://127.0.0.1:3001/work` -> `200`
- `http://127.0.0.1:3001/portal` -> `200`
- `http://127.0.0.1:3001/privacy-policy` -> `200`
- `http://127.0.0.1:3001/robots.txt` -> `200`

### Public risk findings

#### 1. Live production is stale relative to the rescue/stabilization branch
Severity: critical

Evidence:
- Local preview serves `/privacy-policy`; live production returns `404`.
- Local preview robots disallow `/admin-proof` and `/payment-proof`; live robots currently only allow all and do not disallow those proof surfaces.
- Local preview titles and copy reflect the hardened public rescue state; live site does not match the verified local branch.

Impact:
- Legal/privacy route is broken live.
- Search crawlers are not being told to stay off proof routes.
- The live site cannot be treated as aligned with the production-safe repo lane.

#### 2. Deployment lane is still not authenticated
Severity: critical

Evidence:
- Browser visit to GoDaddy dashboard resolves to the sign-in page, not an active authenticated session.
- SSH probe to `xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net` returned `Permission denied (publickey,password)`.
- `ssh-add -l` returned `The agent has no identities.`

Impact:
- Safe deploy cannot be executed from this session.
- Backup, cutover, and rollback procedures cannot be verified against the real host.

#### 3. Public consultation flow was carrying a wrong contact-domain dependency
Severity: high

Evidence:
- `data/famtastic/site.ts` used `hello@famtasticdesigns.co`.
- `components/ShortConsultationForm.vue` also hardcoded `hello@famtasticdesigns.co` for manual mailto submission.

Impact:
- Public consultation flow could open an email draft to the wrong address/domain.
- Public contact identity was inconsistent with the `.com` production domain.

Status:
- Patched on the stabilization branch during this pass.

#### 4. Form capture is honest but still fallback-grade
Severity: medium

Evidence:
- Short consultation form and full intake form both run in manual mode unless `NUXT_PUBLIC_LEAD_CAPTURE_MODE=api`.
- Manual mode opens a mailto draft and then routes to `/thank-you?mode=manual`.
- API mode posts to `/api/leads`, which writes to `.data/famtastic-leads.json` locally unless Directus is fully configured.

Impact:
- Good: the public lane no longer lies about a live CRM pipeline.
- Limitation: production lead storage/notification is still not proven.

#### 5. Admin/payment proof surfaces remain sensitive and must stay blocked from public exposure
Severity: medium

Evidence:
- Local preview intentionally returns `404` for `/admin-proof` when proof is disabled.
- Local robots route disallows `/admin-proof` and `/payment-proof`.
- Existing repo still contains admin-proof and backend-proof artifacts for local/private use.

Impact:
- If the wrong branch or stale assets are deployed, proof/admin routes can reappear or be indexed.

### Production-safe public behavior currently expected from repo
- Public booking CTA fallback goes to `/get-started` when booking provider is `manual`, `mock`, or unset.
- Public audit/payment CTA fallback goes to `/get-started?intent=audit` or `/get-started?intent=payment-options` when payment mode is `mock`.
- Portal pages should present an access/info posture without claiming live authentication.
- Consultation forms should either:
  - open a visible email draft in manual mode, or
  - submit to `/api/leads` only when a real lead capture lane is intentionally enabled.

### Decision
Do not claim production is stabilized until the live host is actually updated.
The code lane is in much better shape than the public site. The blocker is deployment access, not repo readiness.

### Immediate next actions
1. Keep working only on the public stabilization branch.
2. Finish public copy/contact-domain cleanup and any remaining CTA consistency fixes.
3. Re-run build + preview verification after patches.
4. Update QA/readiness docs with the new audit truth.
5. Deploy only after authenticated GoDaddy/cPanel/SFTP/SSH access is available and a rollback backup path is verified.

### Known gaps after this audit
- Live GoDaddy deployment lane is still unavailable from this session.
- Production email delivery, CRM capture, Directus runtime CMS, real portal auth, and real payment processing are not yet proven for the public site.
- Root README and backend/proof docs still contain legacy AgencyOS/proof references, but those are repo-doc issues rather than current public-route blockers.