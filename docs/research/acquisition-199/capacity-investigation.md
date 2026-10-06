# Native mail capacity investigation

Purpose: give the narrow native sender a fresh, honest pacing budget without requiring an unreadable provider remaining-quota counter.

Goal

Separate GoDaddy's published ceiling, observed runtime usage, owner-authorized reserves and actual provider quota. Preserve the exact-message sender and its failure stops.

Tasks

- [x] Revisit FAMtastic definition/startup guidance and re-anchor the acquisition worktree.
- [x] Check primary current provider limits and independently investigate SSH, cPanel APIs, log visibility and the account portal.
- [x] Collect aggregate native campaign/transactional usage without contacts or credentials.
- [x] Implement and verify the owner-authorized conservative-budget interface.

Status: completed investigation/source calculator; scheduler installation/execution belongs to root/backend.
Started: 2026-10-06. Ended: 2026-10-06.
Execution: `codex/acquisition-199-20261005`, acquisition worktree; root owns source landing/commits. Native investigation was read-only, with no SMTP test, send, restart, quota change or remote file write.
Research: primary current GoDaddy documentation, authenticated SSH, cPanel executable/API availability, browser authentication state, fresh native database counters, aggregate Sent-folder metadata.
Review: `changed` for the new calculator/tests; actual runtime proof covers aggregate collection/calculation only. Owner-approved timetable and reserve policy are separate from owner acceptance of scheduled execution.
Skills: existing FAMtastic startup/review guidance; no new specialist package or provider integration.

## Finding and runtime scope

GoDaddy publishes **500 messages per email user per day**, with **500 messages per hour shared across all users on the hosting account**. This establishes a published product ceiling, not this account's remaining quota or an account-specific override. [GoDaddy hosting email relay limits](https://www.godaddy.com/en-uk/help/hosting-email-relay-limits-27150?sc_lang=en-GB).

The production SMTP From and username both match the selected native `hello@famtasticdesigns.com` identity. Fresh native records cover campaign mail and the transactional notification outbox. They cannot establish all mailbox, other application or account-wide relay traffic.

| Aggregate source | Eastern today | Rolling 24 hours | Rolling hour |
| --- | ---: | ---: | ---: |
| Native message ledger | 2 | 2 | 0 |
| Transactional notification outbox | 5 | 10 | 0 |
| Conservative sum | 7 | 12 | 0 |

The runtime snapshot is `2026-10-06T18:44:40Z`. Zero pending/retry rows and zero unresolved exact dispatch attempts were observed. The broader native message ledger contains 273 historical records. Four account mailboxes had zero Sent-copy files; that absence is **not** proof of zero outside usage. The new collector queries fresh counters every call; this dated snapshot never enables tomorrow's sending by itself.

Independent access checks:

- SSH/Drupal bootstrap succeeded against the actual native account; Drupal 11.4.8/MySQL was observed.
- Both `uapi` and `cpapi2` independently failed because `/usr/local/cpanel/cpanel` is absent inside the restricted shell. System Exim logs and `/var/cpanel` limit files were unavailable there.
- The existing browser had no authenticated GoDaddy session; the visible login form was left untouched.
- The available configured cPanel token belongs to a different configured username and hostname. No cross-account authentication or token transplant was attempted. No token/password/signing-key value was printed or copied into evidence.

## Authorized fallback and interface

The owner explicitly authorized the fallback **`published_limit_with_reserved_budget`**, with `budget_kind=conservative_budget`, reserves of **250/day** for mailbox transactional/unobserved traffic and **400/hour** for shared-account transactional/unobserved traffic. Outside usage remains unknown. These reserves are an authorized pacing assumption, **not** a measured guarantee, verified quota remaining or proven lower bound.

`AcquisitionWindowCapacity::read(array $config): array` in `scripts/acquisition-window-capacity.php` requires the signed binding's current sender-account SHA, mode, Eastern timezone, day cap 200, window cap 50, and reserve fields. It verifies the actual native sender identity and reads aggregate native `sent_at` counts, including both message and transactional tables. The observed daily deduction is the greater of Eastern-today and rolling-24-hour totals because the provider's reset timezone is unknown. The hour uses a rolling hour. Potential source overlap only reduces the budget. Queued records and local quota reservation slots are not falsely counted as already-sent provider usage.

The calculation is:

```text
available_today = max(0, min(200, 500 - day_reserve - observed_today))
available_hour  = max(0, min(50, 500 - hour_reserve - observed_hour))
```

At this snapshot, those values are **200/day and 50/window**. The separate native quota enforces the cumulative campaign ceiling, including followups; this result is not an extra allowance on top of that ceiling. The four approved windows remain 9/10/11/noon America/New_York, without catch-up. All October 6 windows had passed; the next is October 7 at 9 a.m. Eastern, subject to a fresh read and the other exact-message guards. No clock or cron was installed by this lane.

The result always labels this mode `verified=false`, `outside_usage_unknown=true`, `provider_remaining_verified=false`, `safe_lower_bound_proved=false`. Missing native sources, malformed/stale evidence, wrong account/policy or reserves below the approved amounts reject the read. Unresolved exact reserved/uncertain transport returns a zero budget. The executor must stop on the first SMTP failure or uncertain result and must not replay it.

Proof

- `php scripts/acquisition-window-capacity-test.php`: **28 synthetic checks passed**, including daily/hour exhaustion, transactional deductions, account/policy binding, freshness, malformed counts, unresolved transport, UTC/Eastern boundaries and fall DST.
- `php -l scripts/acquisition-window-capacity.php`: passed.
- Actual calculator executed against production via ephemeral Drush evaluation, reading aggregates only. No remote source installation or transport occurred.
- Private ignored runtime receipt: `.data/acquisition-199/capacity-runtime-library-20261006.json`, mode 0600, SHA-256 `ad4bb34fa80e4886a9069a1d6f91e3f6f564d850970daefc01e7a13a41378a14`.
- Earlier aggregate native read: `.data/acquisition-199/capacity-native-usage-20261006.json`, mode 0600, SHA-256 `a75fcf96695da1d96488f785e7c2f3ea67d4c495a8e85319321a45421669738e`.

Post-evaluation: cPanel credentials are account-specific; a discovered Studio token is not evidence of agency account access. Published limits plus explicit reserves can support an honestly labelled pacing budget when the owner authorizes that assumption. The opportunity remains to add a matching-account authenticated provider usage report later; its absence must never be silently promoted to complete usage coverage.
