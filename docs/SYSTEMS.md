# FAMtastic Systems Inventory

**Rule zero: runtime state lives in systems, not documents.** This file tells agents WHERE systems are and HOW to interrogate them. Never assert a system's state without probing it. Last full review: 2026-08-24.

## Postiz — social publishing engine (local)

| What | Value |
|---|---|
| Purpose | Scheduling/publishing to FB, IG (+ X/YouTube/TikTok pending setup) |
| UI/API public | `https://designate-vacation-shadiness.ngrok-free.dev` (ngrok static domain — permanent) |
| Local port | `http://127.0.0.1:4007` |
| Containers | `postiz`, `postiz-postgres`, `postiz-redis` (compose project `famtastic-postiz`) |
| DB access | `docker exec postiz-postgres psql -U postiz-user -d postiz-db-local` — Integration table = connected channels; Media table stores ABSOLUTE urls |
| Owner login | `fritz.medine@gmail.com`; password in macOS Keychain service `FAMtastic Postiz Local` |
| Secrets/env | `~/.config/famtastic/postiz.env` (0600) — NEVER print/commit; preserved across restarts by script merge logic |

### Known quirks (each cost us hours once — don't relearn them)
1. **Auth cookie is domain-bound**: browse via the ngrok hostname only. Mixing `localhost`/`127.0.0.1`/ngrok = infinite login spinner.
2. **Media paths are absolute**: changing the public URL requires rewriting `Media.path` (script does this automatically).
3. **ngrok free interstitial** ("Are you the developer?") appears once per browser; can intercept OAuth callbacks — click through, or use API-minted handshake links.
4. **Instagram standalone ≠ regular Instagram provider.** Standalone needs `INSTAGRAM_APP_ID/SECRET` (Meta "API with Instagram Login" product); regular needs FB-page-linked business IG (not our path).
5. **Dev-mode Meta apps**: OAuth only works for accounts with an ACCEPTED role invite (Instagram → Settings → Website permissions → Apps and websites → Tester Invites — NOT email, NOT "Active apps").

### Health checks
```
docker ps --filter name=postiz            # all three Up & healthy?
curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:4007   # expect 307
curl -s https://designate-vacation-shadiness.ngrok-free.dev -o /dev/null -w '%{http_code}'  # expect 307
docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -c 'SELECT name,"providerIdentifier","tokenExpiration"::date FROM "Integration" WHERE "deletedAt" IS NULL;'
```
If broken or spinner returns → run `scripts/restart-postiz-tunnel.sh` (heals tunnel + env + media paths; prints URL).

## FAMtastic Designs — production platform

| What | Value |
|---|---|
| Site | famtasticdesigns.com → cPanel box `132.148.233.159` (GoDaddy DNS by owner decision — do not migrate without Fritz) |
| Backend | Drupal 11, custom module `famtastic_pipeline`, ~65 routes under `/api/*` + `/admin/famtastic/*` |
| Mail | PHPMailer→SMTP via contrib smtp.settings (config lives ONLY in prod DB — verify before assuming); outbox list `/admin/famtastic/metric/notifications`, replies `/admin/famtastic/metric/replies` |
| Cron | cPanel `*/5 * * * * drush famtastic:lifecycle-run` — heartbeat visible at `/admin/famtastic/metric/workers`; NOTE: drush exits 255 on this host even when successful |
| Deploys | Only from clean committed SHA via `scripts/deploy-{frontend,backend}-godaddy.sh` (`--apply` gated by Fritz) |
| Repo | `~/Development/FAMtastic/sites/site-famtastic-designs` (origin: github famtastic-designs.git) |

## Other estate

| System | Where | Notes |
|---|---|---|
| Monorepo | `~/Development/FAMtastic` (github famtastic.git) | Contains Sites/, Apps/, tools/postiz-* snapshots, docs doctrine |
| By The Numbers app | `~/Development/FAMtastic/Apps/famtastic-by-the-numbers` | Live: famtastic-by-the-numbers.netlify.app — STALE: production runs Jul-2 main; 29-commit `rebuild/app-experience` branch unmerged |
| Worktrees | `~/Development/famtastic-wt-{alpha,bravo,charlie,...}` | Studio rebuild bake-off lines |
| 1000-ideas catalog | monorepo `1000-IDEAS.md` | Raw input for PRODUCT_PIPELINE recipe |

## Doctrine pointers
- Operating rules: `docs/playbook/README.md` · Master plan: `docs/playbook/MASTER-PLAN.md`
- Onboarding runbooks: `docs/playbook/RUNBOOKS/`
- Capability evidence levels: `docs/CAPABILITY_REGISTRY.md` — never downgrade claims without proof.
