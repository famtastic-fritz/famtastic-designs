# Production readiness and Postiz diagnosis — September 30, 2026

Read-only live audit, plus independent source regression review. No deployment, service start, tunnel start, default change, paid model call, campaign schedule/publish or real email send occurred.

## Actual Postiz failure

Production was queried through the canonical SSH target and installed `public_html/vendor/bin/drush`. The command read only the configured base URL components, module marker presence, and the existing Postiz service's sanitized health booleans/count. No settings dump, API key, password, customer data or provider response body was printed.

| Observation | Result |
|---|---|
| Production configured scheme / host / path | `https` / `designate-vacation-shadiness.ngrok-free.dev` / empty |
| Existing authenticated channel service | configured `true`, reachable `false`, platform count `0` |
| Deployed module campaign snapshot marker | absent |
| Public `https://designate-vacation-shadiness.ngrok-free.dev/api/public/v1/integrations` | HTTP 404; response header `ngrok-error-code: ERR_NGROK_3200` |
| Local `http://127.0.0.1:4007` | connection refused |
| Local ngrok agent `http://127.0.0.1:4040/api/tunnels` | connection refused |
| `docker ps` for documented Postiz containers | Docker unavailable to this session; no healthy container evidence |
| Documented migration example `postiz.famtasticdesigns.com` | name resolution failed; not a verified replacement |

The ngrok error explicitly means the endpoint is offline. The official explanation identifies a missing/stopped agent or wrong endpoint address: https://ngrok.com/docs/errors/err_ngrok_3200 . Combined with the authoritative configured static hostname in `docs/SYSTEMS.md`, `scripts/restart-postiz-tunnel.sh` and local refused ports, this supports an unavailable documented tunnel/service chain. It does not prove Docker data was removed, which process stopped it, or that migration occurred.

The production base is already host-only. Therefore the new URL normalizer fixes a genuine compatibility bug for full API bases but cannot repair this observed outage. Do not change production settings to a guessed URL or report the 404 fixed by deploying the normalizer.

## Concrete recovery handoff

The existing approved static public hostname remains the only verified configured target. Recover the documented Postiz stack and ngrok endpoint serving local port 4007, then re-run authenticated integrations health from Drupal. `scripts/restart-postiz-tunnel.sh` documents the recovery lane, but is **not safe to invoke blindly**: it starts/restarts processes, updates local environment and media URLs, and assumes running containers. Restoring a publishing service can resume existing queued jobs; that consequential effect requires explicit release/recovery scope and inspection of pending schedules before workers resume.

Before recovery: determine whether the existing Docker engine/compose data is available, inspect pending schedules through an isolated/read-only data method, and preserve its data. Once authorized, restore the same hostname and verify HTTP 200 authenticated integrations count and actual enabled channel records. A successful integration listing still does not prove publication. If laptop-independent operation is selected instead, `docs/marketing/POSTIZ_SERVER_MIGRATION.md` is a proposed migration contract; its example hostname has no verified live endpoint and requires separate infrastructure/DNS/config authority.

## Release prerequisites and exact proof boundaries

- Finish independent mobile flow acceptance and commit/push reviewed source. Record the resulting exact SHA in the release handoff; this audit intentionally does not invent a final commit while files are still being finalized.
- Canonical backend release only from reviewed current GitHub main, with existing DB/code backup and update8066 migration. Immutable campaign snapshot staging, marker promotion and marker-coupled rollback are prepared locally; production marker was absent during this audit.
- Production apply and backup/rollback rehearsal remain an explicit release action. Read-only diagnosis does not authorize it.
- AI tasks remain disabled by default. Select an actual configured OpenAI chat default and explicitly enable desired tasks only under approved configuration scope. Local adapter tests prove the interface; a bounded authorized provider call is still needed for live response/latency/cost evidence.
- Draft/template/save/preview acceptance is synthetic runtime proof. No customer send or inbox delivery proof has been added.
- Postiz recovery is an operational prerequisite distinct from code release; current behavior must display unavailable until live authenticated health succeeds.

## Closing anti-pattern regression

The reply form now catches Drupal database exceptions/PDO exceptions before domain RuntimeExceptions. Independently executed `test-draft-error-message.php` against the patched isolated runtime: PASS, SQL/body marker absent and safe message present.

The locator now treats a valid module marker's snapshot as authoritative for both read and discovery. Independently copied the exact class to an isolated PHP fixture: no marker discovers a mutable-only campaign; marker present with missing snapshot returns null and excludes that campaign rather than silently using mutable data. PASS.

The release helper document now begins with the final immutable snapshot contract and marks mutable promotion helper as not used by canonical deployment. Integration evidence records final contract and explicitly retains live endpoint proof gap. These close the anti-pattern report's two source findings and documentation reconciliation; no new release authority follows.
