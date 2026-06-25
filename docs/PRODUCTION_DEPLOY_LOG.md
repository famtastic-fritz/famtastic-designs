# Production Deploy Log

## 2026-06-25 — Public rescue deploy attempt (blocked before live)
- Timestamp: 2026-06-25 local session
- Repo: `~/famtastic/sites/site-famtastic-designs`
- Deploy source branch: `famtastic/prod-public-rescue`
- Deploy source base: `famtastic/site-v1-production-proof`
- Source commit before rescue edits: `75e3fcde`
- Intended production URL: `https://famtasticdesigns.com`
- Intended host/lane: GoDaddy-hosted site for `famtasticdesigns.com`
- Verified domain DNS: `famtasticdesigns.com -> 107.180.51.234`
- Referenced cPanel/host lane from prior repo/session truth: `p3plzcpnl497512.prod.phx3.secureserver.net`
- Referenced account/user from prior repo/session truth: `xrdj7j99xhzt`
- Live deploy status: blocked before live

### Why deployment was blocked
1. Production lane could not be authenticated from this session. Direct SSH probe to `xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net` returned `Permission denied (publickey,password)`.
2. No verified cPanel, SFTP, rsync, Git auto-deploy, or process-manager path was available in this session to replace the live site safely.
3. Hard rule held: no deploy without a verified production lane, no deploy with disappearing leads, and no deploy that risks exposing `/admin-proof` or incomplete backend surfaces.

### Local production-safe rescue changes prepared on this branch
- Disabled admin override application unless `ENABLE_ADMIN_PROOF=true`, so local `.data` proof overrides cannot leak into normal public rendering.
- Removed the static `public/robots.txt` override and routed robots through the guarded server route.
- `robots.txt` now disallows `/admin-proof` and `/payment-proof`.
- Public contact and portal copy were cleaned up to remove proof/mock leakage while staying honest about preview-only backend areas.
- Consultation forms were switched to manual email-draft fallback so the public rescue build does not pretend server-side lead capture is live.
- Payment and booking language were shifted to consultation/manual handling instead of implying live checkout.

### Local QA proof used before the live attempt was blocked
- `pnpm install`
- `pnpm typecheck`
- `pnpm lint`
- `pnpm build`
- Local preview via `node .output/server/index.mjs` with production-safe flags
- Browser verification of `/`, `/portal`, `/client-portal-login`, `/robots.txt`, `/admin-proof`

### Rollback / backup
- Live rollback was not needed because no production deploy occurred.
- Backup path: not created yet because authenticated production access was unavailable.
- Rollback command/method: pending once the real deploy lane is authenticated and inventoried.

### Next required step before live cutover
Provide verified production access for the actual FAMtastic Designs host lane (cPanel/SFTP/SSH/process manager or documented auto-deploy path), then re-run backup -> deploy -> live verification from this prepared branch.