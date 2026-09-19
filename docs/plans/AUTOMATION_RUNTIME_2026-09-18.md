# Bounded automation runtime: source implemented, activation pending

Owner policy: routine submitted-request research, three independently reviewed
proofs, authorized notice and selected-site continuation proceed without another
Fritz gate. Scope, spending, rights, security, merchant authority and repeated QA
failures escalate. Exact client acceptance and settled payment still precede final
launch; no worker can infer either. The approved exceptions for the two current
clients belong to their account-bound commercial records.

## Current truth (read-only September 18)

| Stage | Authority / host | Scheduler / trigger | Actual evidence | Recovery / boundary |
| --- | --- | --- | --- | --- |
| Requests, proof campaigns, selection, payment | Agency Drupal; `xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net`, `/home/xrdj7j99xhzt/public_html` | HTTP intake / authenticated choice | QA-gate backend marker independently observed `988d9d6d`, 23:46:20Z; main separately owns subsequent commercial release | Read actual release marker; never infer state from a local folder |
| Broad legacy worker | Same agency host | Five-minute `vendor/bin/drush famtastic:lifecycle-run` | Cron PATH `/usr/bin:/bin` invokes PHP CGI, not CLI. `/usr/local/bin/php` reports CLI | **Not fixed live here**. Do not change to CLI then accidentally drain old jobs/mail |
| Proof dispatch | Drupal AutomationWorker / external producer | `proof.generate` | Existing job can complete with `waiting_callback`, zero variants | Handoff complete is not generation/QA/notification complete. This lane adds no proof generator |
| QA release | Agency `AutomatedProofRelease` | Trusted independently authenticated orchestrator | Gate deployed as `988d9d6d`; 15 SQLite/filesystem tests, 64 assertions | Exact artifact/research/policy/reviewer plus personal notice; no owner uid impersonation; deployment alone does not send |
| Selected planning/static continuation | Site Studio Next | Opt-in selected runtime | Repair branch `9d0f6a2`, prior synthetic cPanel proof; not activated here | Preserves selected source. Planning can return `executable:false`; this is not WooCommerce |
| Running Studio | Mac LaunchAgent `com.famtastic.studio-next`, Node24, port3400 loopback | Login/KeepAlive | Runtime checkout `fbca6d1`; data root `Development/famtastic-wt-phase-0/.studio-next-data` | Laptop/login/Keychain dependent. Do not expose Studio UI/API publicly |
| New central claims | Drupal `WorkerCoordinator` | Explicit fresh exact-job enrollment; signed Mac/cloud request or bounded server tick | SQLite tests cover fencing, replay, budget, identities and interruption | Disabled/unactivated. Empty new tables never enroll or touch old jobs |
| Cloud runner | Proposed on-demand Cloud Run job | One execution on demand, no idle model loop | Node runner locally tested; no cloud deploy. `gcloud auth list` empty, project unset | Requires authorized account, least-privilege secret references and real headless Studio target |

Credentials stay in installed Drupal Settings, Secret Manager-mounted files, or
existing restricted Mac/Keychain mechanisms. Never commit tokens or use raw keys
as CLI arguments. The shared cPanel customer host remains separate from agency
Drupal. Do not copy customer credentials or target paths across projects.

The cloud-cost audit identified project `gen-lang-client-0744578052`; its separate
audit task remains the owner of retired-VM evidence. Nothing here restarts those
VMs/agents, adopts their keys or claims their latest state from old records.

## What this source actually implements

The existing new-request path is not a general creative worker:
`AutomationWorker::generateProof()` reuses the request-bound campaign. When
`SiteStudioProofClient` has a configured endpoint it submits a signed asynchronous
handoff; otherwise, with stub flags disabled, `ProofCampaignService` creates a
`local-*` waiting campaign and a `proof.waiting_for_creative_provider` event, with
zero variants. The job can return `waiting_callback` and be marked handoff-complete.
No autonomous consumer is started by that result. Stub/no-image flags belong only
to isolated tests, not a way to claim a customer's three designs are complete.

- `WorkerCoordinator` admits only an exact fresh unattempted
  `site_studio_staging_prepare` job with a frozen payload and supported static
  build class. No automatic admission of history, generic proof generation,
  marketing, payments, domain purchase or launch.
- Admitted jobs use `worker_queued` / `worker_running` in **the same Drupal ledger**.
  Existing broad workers only claim `queued`, so they cannot duplicate enrolled work.
  Cloud and Mac contend for the same database lock, claim and cost reservation.
- Random lease tokens are stored hashed, bound to the installed worker identity,
  renewed for 90 seconds, fenced by a 300-second execution deadline plus 30 seconds
  of grace. Losing a heartbeat does not prove the old process stopped: replacement
  waits out the absolute runtime fence. Tokens from old attempts cannot complete.
- At most one active attempt; at most three attempts, exponential backoff. Immutable
  payload hashes and the existing selected-packet preflight reject changed inputs.
- POST signatures bind method, canonical route, installed worker, timestamp, nonce
  and body digest. Ninety-second clock window; durable unique nonce rejects replay.
  HTTP surface is TLS-only and disabled until explicit installation configuration.
- Finish is idempotent for the same token and exact receipt, and only reports
  `handoff_completed` / `accepted_waiting_callback`. Generation, signed staging
  receipt, QA, exact client acceptance and live launch remain separate authorities.
- A single Node24 invocation exits when there is no work. Dispatch and finish retries
  are bounded. No recursive builds, automatic model loops, mail calls or subscriptions.

The coordinator's static capability is deliberately narrow. A selected ecommerce
proof must go to an implementation worker that creates the real WordPress/WooCommerce
site, preserves chosen pages/assets and verifies backend behavior. The shipped static
adapter cannot do that. An exact-selection Codex heartbeat is an **orchestrated bridge**,
not laptop-independent cloud automation or completed Woo fulfillment. Record its actual
job/selection/artifact identity and stop the bridge once the native worker is proven.

### Known paid-exception selection gate

The selected-repair branch rejects a populated `commerce_order_id` with "A paid
request cannot start pre-payment staging." Commercial source `e692d890` adds
`OfflinePrepaymentService::permitsSelectedStaging()` for the exact authorized
prepaid exception. Before releasing the combined selected repair, use that helper
at both selection and selected-revision guards and test its authoritative bindings.
The isolated integration retains this helper, including the locked selection
re-read. This does not authorize changing the normal converted-order field: source
association, packet registration and receipt CAS also intentionally require NULL.
Supporting an early populated field end-to-end is a separate cross-service policy
change, not a selector-only patch. Current native prepayment is compatible through
its intentionally NULL normal field and private offer/order binding.
Do not globally bypass paid checks. Rawls' receipt records native order21/payment5;
the request's normal checkout field intentionally remains NULL while the private
offer and order data provide the bidirectional request/customer/payment binding.
That is not missing payment evidence and must not be "fixed" by creating a sale.
This lane does not mutate Commerce or customer rows.

The main lane reports `review-selected-site-automation-implementation` active at
15-minute intervals for the exact current deliveries. This is an orchestrated Mac
fallback, not immediate cloud execution or evidence that WooCommerce is implemented.

## Budget and costs

The owner authorized **$25/month incremental worker spend**. The application stops
new reservations at **$20**, retaining **$5 headroom** for billing delay, lightweight
triggers, storage and accounting uncertainty. Each attempt requires a conservative
25–250-cent pre-reservation. Reservations are never refunded automatically, including
crashes, lost callbacks or month rollover; an unknown cost remains held in its month.
The cap serializes concurrent reservations with claims. A failed reservation cannot
start a provider call. Tests exhaust the budget and verify the job remains unclaimed.

This is a conservative application admission control, **not a Google account spending
cap**. Only reviewed adapters whose worst-case total cost fits their reservation may
be enabled. Model/asset generation is not implemented by this dispatcher and cannot
be smuggled behind its static reservation. Cost reconciliation and verified provider
ceilings remain activation gates; current holds are not actual billed-cost receipts.
Google explicitly warns that [budget alerts do not cap spending](https://docs.cloud.google.com/billing/docs/how-to/budgets).

Cloud settings for initial synthetic proof: one task, parallelism1, max-retries0,
300-second timeout, 1CPU/512MiB, no idle service/minimum instances, dedicated service
account, only the two required secret-version reads. Use a digest-pinned Node24 image
and worker image. Do not build/download large images on the disk-constrained Mac.
Invoke on demand first; [Cloud Scheduler can invoke jobs](https://docs.cloud.google.com/run/docs/execute/jobs-on-schedule),
but do not install a five-minute cloud model loop or assume an empty cloud invocation
is free. Enable a dispatcher/trigger only after its headroom and no-work behavior are measured.

## Deployment and exact schedule repair

1. Coordinate with main's current release. Fetch/reconcile its exact SHA; preserve
   customer email/payment work. Use canonical backend preflight/apply, never copy
   module source directly to production. Update8065 creates only empty worker tables.
2. Run `/usr/local/bin/php vendor/bin/drush.php famtastic:automation-health` under
   cron's PATH. It reports counts/config policy only, no claims/provider/mail work.
3. Preflight `scripts/repair-bounded-lifecycle-schedule.php` through installed Drush
   `php:script`. It requires exact production root, CLI SAPI and installed schema.
   Record its `before_sha256`, proposed command and observe-only mode.
4. Only after shared release coordination, set `FAMTASTIC_REPAIR_LIFECYCLE=apply` and
   `FAMTASTIC_CRONTAB_CONFIRM_SHA256` to that exact preflight hash. Default mode is
   **observe-only**. The exact marker/command is replaced with explicit CLI PHP,
   `drush.php famtastic:automation-tick` and a retained operational log. Unrecognized
   or duplicate schedulers fail closed. A private mode0600 full backup is retained;
   a changed crontab fails rather than overwriting other operator edits.
5. Observe a real subsequent scheduled log timestamp and CLI health before claiming
   cron fixed. The current task did **not** install it; the old cron remains unchanged.
6. Dispatch is a separate activation: private Settings
   `famtastic_bounded_dispatch_enabled=true`, no pilot lock, exact Studio endpoint,
   signed callback, current customer-bound protected hosting binding, verified recipe
   and cost ceiling. Set `FAMTASTIC_BOUNDED_MODE=dispatch` only after these gates. It
   runs at most one explicitly enrolled static handoff, not any generic queued job.

Canonical backend deployer now validates the exact existing legacy/bounded pair
in preflight and rechecks the unchanged snapshot after promotion. It preserves
observe/dispatch mode and never re-adds a legacy worker when the bounded marker
is present. Missing schedule remains explicitly `none_not_activated`; deployment
is not scheduler authorization. Unknown/altered/duplicate entries fail closed.
The regression executes the actual extracted Bash classifier without SSH/cron
writes, including the bounded-plus-legacy case that caused the reinsertion risk.

Enrollment command (never run on an old failed job to "unstick" it):
`famtastic:worker-enroll ID --key=EXACT --confirm=EXACT --sha256=PAYLOAD_HASH --reserve-cents=25`.
An orchestrator may enroll routine supported work automatically after these admission
checks; this source has no automatic customer enrollment hook yet. Unsupported work
must remain visibly implementation-needed, not spend three static retries.

Private HTTP worker installation uses `famtastic_bounded_workers_enabled=true` and
`famtastic_worker_registry[worker-id] = {secret: ..., capabilities: [...]}`. Give
builders only `selected-static-dispatch-v1`; grant `proof.review` only to the
independent reviewer identity. The reviewer operation derives `automation:<worker-id>`
from the authenticated key; it cannot pick Fritz's uid or a different reviewer name.
Node environment: `FAMTASTIC_WORKER_ID`, `FAMTASTIC_WORKER_API_BASE` (HTTPS `/web`),
`SITE_STUDIO_STAGING_URL` (exact HTTPS acceptance path), and mounted
`FAMTASTIC_WORKER_SECRET_FILE` / `STUDIO_DISPATCH_SECRET_FILE`.

## Recovery and pending acceptance

- Stop only this bounded schedule/disable worker Settings; preserve leases, receipts,
  source artifacts and all customer/payment records. Do not reinsert a whole old
  crontab backup or revive the failing broad scheduler during rollback.
- Existing deploy backups restore exact previous code; incompatible DB rollback needs
  separate approval. Empty new tables can remain when rolling code back.
- On worker loss, wait out the fence, reconcile the exact selected packet and existing
  Studio idempotency receipt, then retry only remaining work. Never regenerate the
  winning design or resend an uncertain customer notice.
- Last focused local checks: 80 relevant PHP tests / 418 assertions (existing PHPUnit
  metadata deprecations), 24 Node tests (16 actual Bash deployer cases plus eight
  runner cases). Real SQLite/transaction/filesystem
  operations plus controller authentication; mocked entity lookup and lock backend.
  **Not multi-process MySQL lock proof**. Email presentation: 72 assertions passed.
- After explicit authorization to copy the bounded disposable runtime, the fresh
  canonical customer-journey runner was executed. Intake v2 and proof-ready v4
  expectations were corrected without weakening delivery assertions (`8aaec4ab`).
  The next run passed catalog/SEO/email and stopped at old selected-source fixture
  expectations (receipt `fresh-customer-proof-20260918T235348Z-80024`). The separate
  fixture migration exercises complete source, signed callback, replay and exact
  customer acceptance. The complete fresh canonical run subsequently passed at
  2026-09-19T00:00:11Z, retained under `.artifacts/fresh-customer-proof/` run
  `fresh-customer-proof-20260918T235933Z-82730/evidence.json`. Source receipt names
  base8aaec4ab plus then-uncommitted fixture/worker changes; fixture was committed
  as27ab74fa. This is a disposable synthetic lifecycle, not customer/cloud delivery.
- After integrating reviewed commercial main de3aa707 in isolated39ea4a56, the
  full fresh canonical run passed again, then passed with actual installed Drush
  `automation-health` / default `automation-tick` checks added. Latest receipt:
  `fresh-customer-proof-20260919T001047Z-87924/evidence.json` (base7bd14d79 plus
  then-uncommitted health fixture). Queue/outbox/claim/budget counts stayed unchanged.
  The private native-payment service remains byte-identical to reviewed main;
  portal source guards reuse its exact reconciliation helper, not duplicate logic.
- No Cloud Run provider execution, production claim/nonce test, scheduler tick,
  automatic target allocation, ecommerce adapter or laptop-unavailable end-to-end
  execution has occurred. Laptop independence remains **unproven**.
- Before enabling cloud delivery: prove bad signatures/replays, simultaneous Mac/cloud
  claim, worker interruption, stale token, lost callback/acknowledgement, exhausted
  budget and same immutable receipt after retry on an isolated synthetic target; then
  run a complete unattended test with the Mac/laptop worker unavailable and verify
  Drupal records, served bytes and signed callback. Only then upgrade the capability.
