# Moving Postiz off the workstation

**Status**: ready to execute; host not yet chosen (spend decision is the owner's)
**Created**: 2026-09-03
**Prerequisite reading**: `docs/marketing/CAMPAIGN_POSTING_ARCHITECTURE.md`

This is the permanent fix for the reason campaign drops do not fire: sending
currently requires the operator Mac awake, colima running, the Postiz container
healthy, an ngrok tunnel up, and a human at a terminal — all at once, at the
scheduled minute.

**2026-09-03 supplied the proof.** Postiz's publishing worker was OOM-killed
inside the 3GiB colima VM continuously from 2026-08-25, because that VM also
hosts WordPress and a full Temporal stack. Nine days of campaign posts were
destroyed by memory contention from unrelated software on a laptop, and nothing
in the pipeline reported a problem — every layer above the worker returned
success. Sizing this host correctly (§3.1: 4 GB floor, and never share it) is
therefore not a nice-to-have; it is the failure that already happened.

---

## 1. Why a new host is required

Checked before proposing any paid service, per the repository's provider rule:

| Existing resource | Can it host Postiz? |
|---|---|
| GoDaddy cPanel shared hosting (`132.148.233.159`, `p3plzcpnl497512.prod.phx3.secureserver.net`) | **No.** Shared cPanel cannot run Docker or long-lived containers. It runs Drupal and cron only. |
| Anything else in `marketing/providers.json` | **No.** The registry holds creative, model, storage, payment and analytics providers. There is no compute host among them. |

So there is nothing to reuse, and off-workstation hosting means one new paid
service. Two shapes:

| | Approach | Rough cost | Notes |
|---|---|---|---|
| **A** | Self-host on a small VPS (Hetzner, DigitalOcean, Vultr, Lightsail) | ~$6–12/month | 2 vCPU / 4 GB is comfortable; 2 GB is the floor — the workstation instance has already been OOM-killed at 3 GB (exit 137, recorded 2026-08-25). You patch the host. Everything in this repo works unchanged. |
| **B** | Postiz Cloud (vendor-hosted) | vendor subscription | No infrastructure to run or patch. Same API, so the scripts still work; you give up control of the data and the upgrade cadence. |

The rest of this document is the procedure for **A**. For **B**, skip to §6 —
only the base URL and API key change.

Add whichever is chosen to `marketing/providers.json` before it is paid for.

---

## 2. What is already built

| Piece | Path |
|---|---|
| Server compose stack (Postiz + Postgres + Redis + Caddy TLS) | `marketing/engine/postiz/compose.server.yaml` |
| Caddy front door | `marketing/engine/postiz/Caddyfile` |
| Deploy primitive (dry by default, `--apply`, `--status`, `--backup`) | `scripts/deploy-postiz-server.sh` |

Deliberate differences from the workstation stack:

- **Caddy terminates real TLS** on a hostname you control and renews
  automatically. This replaces the ngrok free static domain, which is up only
  while the Mac runs ngrok — the single largest reason a scheduled drop vanishes.
- **`NOT_SECURED` is absent.** It is set on the workstation only because
  plain-HTTP loopback cannot carry secure cookies.
- **Registration is closed by default.** A publicly reachable Postiz with open
  registration lets a stranger create an org on your instance.
- **Postiz publishes no host port.** Caddy is the only public listener, so the
  API is never exposed unauthenticated on the public IP.

---

## 3. Procedure

### 3.1 Provision

A VPS with 2 vCPU / 4 GB / 40 GB, Docker Engine and the compose v2 plugin.
Open **80 and 443 only** — Caddy needs both (80 for the certificate challenge,
443 for traffic). Do not expose 4007 or 5432.

### 3.2 DNS

Point a hostname at the VPS with an A record.

`docs/SYSTEMS.md` records that DNS is on GoDaddy by owner decision and must not
be migrated without Fritz. Adding one A record on a subdomain is not a
migration — it is a record on the existing GoDaddy DNS, and the note stands.
`scripts/restart-postiz-tunnel.sh` claims a custom subdomain "is not available";
that was about lacking a *server to point it at*, which this step supplies.

Verify before deploying — Caddy cannot issue a certificate until it resolves:

```bash
dig +short postiz.famtasticdesigns.com A
```

### 3.3 Secrets

Outside every repository, mode 0600. The deploy script refuses to run otherwise
and never prints a value.

```bash
mkdir -p ~/.config/famtastic && chmod 700 ~/.config/famtastic
umask 077
cat > ~/.config/famtastic/postiz-server.env <<EOF
POSTIZ_DOMAIN=postiz.famtasticdesigns.com
POSTIZ_ACME_EMAIL=fritz.medine@gmail.com
POSTIZ_JWT_SECRET=$(openssl rand -hex 48)
POSTIZ_DB_PASSWORD=$(openssl rand -hex 32)
POSTIZ_DISABLE_REGISTRATION=false
EOF
chmod 600 ~/.config/famtastic/postiz-server.env
```

Then append the per-platform OAuth app credentials (the same app IDs and secrets
the workstation instance already uses — the *apps* do not change, only their
redirect URLs).

Registration starts open for exactly as long as §3.5 takes.

### 3.4 Deploy

```bash
./scripts/deploy-postiz-server.sh            # preflight; changes nothing
./scripts/deploy-postiz-server.sh --apply    # pull, start, wait for HTTPS
```

Preflight verifies Docker, compose validity, env file presence and mode,
required secrets, and DNS resolution. It backs up automatically before changing
an existing stack.

### 3.5 Owner account, then close the door

Open `https://postiz.<domain>`, create the single owner account, then:

```bash
sed -i 's/^POSTIZ_DISABLE_REGISTRATION=false/POSTIZ_DISABLE_REGISTRATION=true/' \
  ~/.config/famtastic/postiz-server.env
./scripts/deploy-postiz-server.sh --apply
```

Do not skip this. An open instance on a public IP is an open instance.

### 3.6 Re-authorize the five channels

Each platform's developer portal needs the **new** HTTPS redirect URL added
(replacing the ngrok domain), then reconnect in the Postiz UI.

Known constraints, carried over from `docs/playbook/RECIPES/SOCIAL_POSTING.md`:

- **Instagram** — connects **@famtasticdesigns ONLY, never @famtstic**. Standing hard rule.
- **TikTok** — sandbox; tokens expire roughly daily until the app audit clears. Expect to re-auth often, and do not treat a TikTok drop as reliable yet.
- **YouTube** — testing mode.
- **X** — OAuth 1.0a.
- **Facebook** — the workstation token was valid to 2026-10-10; the server connection issues a fresh one.

The ngrok domain can be removed from each portal once every channel is
reconnected and verified — not before.

### 3.7 Data: start fresh

Recommended. Every channel is re-authorized anyway, and the only durable state
worth keeping is drafts. The 12 existing days 1–3 drafts carry stale 2026-08-23
dates and are blocked by the stale-date guard regardless, so re-queueing them
from the manifest is cleaner than migrating them:

```bash
python3 scripts/queue-campaign-drops.py --campaign <slug>
```

If you would rather migrate, `--backup --apply` produces a `pg_dump` plus an
uploads tarball; restore both into the new volumes before first start. Media
paths are stored relative to the upload directory, so uploads must come across
with the database or every draft loses its media.

### 3.8 Point the tooling at it

In the operator environment (and later the cron job's environment) — never in a
committed file:

```bash
export FAMTASTIC_POSTIZ_BASE_URL=https://postiz.famtasticdesigns.com/api/public/v1
export FAMTASTIC_POSTIZ_API_KEY=<org key from the Postiz UI>
```

Both `queue-campaign-drops.py` and `publish-executor.php` read these. The
loopback-only fallback that reads the key out of the local postgres container no
longer applies once the base URL is not loopback — the key must be set
explicitly, which is the intended behavior off-workstation.

### 3.9 Verify end to end

```bash
curl -fsS -H "Authorization: $FAMTASTIC_POSTIZ_API_KEY" \
  "$FAMTASTIC_POSTIZ_BASE_URL/is-connected"

python3 scripts/queue-campaign-drops.py --campaign cost-is-not-the-reason --dry-run
python3 scripts/queue-campaign-drops.py --campaign cost-is-not-the-reason
```

Only then arm a real send. Note that video media resolves **on the host running
the script** — if you run the queue on the server, the mp4s must be there too,
or those drops report BLOCKED by name.

---

## 4. Then, and only then, move the trigger

With Postiz reachable at a stable HTTPS URL, server-side scheduling becomes
possible. This is the part deliberately not built yet — building it against the
laptop instance would have baked in the flaw.

1. A `QueueWorker` plugin in `famtastic_pipeline` (none exist today) that
   converts due, approved drafts and records provider IDs.
2. `famtastic_pipeline_cron()` enqueuing due records. **Note the pilot
   exact-dispatch lock currently short-circuits all general automation there** —
   it must be resolved or explicitly scoped around first.
3. `FAMTASTIC_MARKETING_PUBLISH=true` set in the **server** environment, so
   arming stays an infrastructure act rather than a commit.
4. cPanel cron quirks that already cost weeks once, per
   `docs/RETROSPECTIVE-2026-08-22-25.md`: cron resolves `/usr/bin/php` to the
   CGI wrapper, so set `PATH=/usr/local/bin:/usr/bin:/bin`, keep stderr in a
   per-job log, and remember drush exits 255 on this host even on success.
5. Deploy per `docs/BACKEND_DEPLOYMENT.md`.

---

## 5. Rollback

The workstation stack is untouched by any of this. To revert, unset
`FAMTASTIC_POSTIZ_BASE_URL`/`FAMTASTIC_POSTIZ_API_KEY` and run
`./scripts/postiz-local.sh start` — every script falls back to
`127.0.0.1:4007`. Take `--backup --apply` from the server first if it holds
drafts you want.

---

## 6. If you choose Postiz Cloud instead

Skip §§3.1–3.5 and §3.7. Create the account, connect the five channels (§3.6
constraints still apply), take the org API key, and do §3.8 with the vendor's
API base URL. `compose.server.yaml`, the Caddyfile and the deploy script become
unused — leave them; they are the escape hatch if the subscription ends.

---

## 7. Open decision

**Which host.** Until that is answered, sending stays on the workstation and a
drop can only fire while that machine is awake with colima and ngrok up.
Everything else here is built and dry-run verified.
