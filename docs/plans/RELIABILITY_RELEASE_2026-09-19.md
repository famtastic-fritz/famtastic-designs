# Reliability release — September19 UTC / September18 Eastern

## Deployed and production smoke-tested

Exact reviewed source `378c3d869814cce733d0225fd1f6edfea037869b` was pushed to
main and deployed through both canonical scripts from a clean matching worktree.
Backend marker:03:50:31Z, PHP8.3.32. Frontend marker:03:53:03Z, Node22.23.2.
Server preflights, dependency validation, database updates, cache/package/catalog
and protected-proof checks passed. Frontend verified216 route shells and compiled
asset status/MIME. Existing creator-credit and portal-navigation fixes are retained.

Private backups under `/home/xrdj7j99xhzt/backups/`:

- Backend module, themes, services, database, dependencies and commercial config:
  `*-20260919T034814Z-378c3d869814cce733d0225fd1f6edfea037869b.*`;
  exact paths remain in `public_html/.backend-release`.
- Frontend: `famtastic-frontend-20260919T035141Z-378c3d869814cce733d0225fd1f6edfea037869b.tgz`.

CUA reloaded the actual deployed apex and www pages. Both roots populated, their
final URLs stayed on their respective hostnames, and the hydrated main heading was
“Agentic AI Business Solutions Engineering Studio”. Both loaded index-BAbCn33S.js;
www loaded index-C4xyUebf.css. No error/warning entries were observed. Visual inspection
confirmed canonical logo and cursive heading treatment. Initial fallback copy changes
when CMS data arrives; do not report the first render as the settled CMS state.

The existing signed-in Fritz browser correctly refused Brother K's account-bound
project link and displayed the wrong-account notice. At390px the portal was contained
(document375px); no error/warning entries. No logout, customer impersonation, form
submission or acceptance occurred. This is NOT a new signed-out login-return or actual
Brother K browser-session test. Prior dedicated navigation evidence remains separate.

## Observe-only server cron is now proven

The installed health/preflight ran through `/usr/local/bin/php vendor/bin/drush.php`.
At03:54:04Z the reviewed repair replaced only the exact owned legacy scheduler line,
after confirming the preflight SHA256 and backing up the complete prior crontab.

- Before SHA256: `b3ec3305d987be81cc496125ea27224e3957fba2299a9f04cf33d3a72f4535f5`.
- After SHA256: `c1e21b7ad4341a1981425b93360ace24abb41cb084b1489adb22cc67552f4ce0`.
- Backup: `/home/xrdj7j99xhzt/deploy/famtastic-designs/cron-backups/bounded-20260919T035404Z-3055988496.txt` (0600).
- Schedule: every5minutes, explicit CLI PHP, `famtastic:automation-tick`, no `--dispatch`.
- Real scheduler log: `/home/xrdj7j99xhzt/deploy/famtastic-designs/bounded-worker.log`.

The log was absent before installation. Its first actual scheduled entry is
03:55:03Z, `php_sapi=cli`, `mode=observe_only`, `queue_mutations=0`, enrolled0,
reserved0, stop2000cents, authorized2500cents, laptop_independence_proven=false.
No manual automation-tick was run to manufacture this scheduler evidence.

Before/after job totals: completed63, failed9, quarantined242. Notification totals:
sent745, superseded29. Claim and reservation tables remain empty. Exact original/
corrected outboxes767/769/772/773 and Brother K775 remain sent with one attempt and
unchanged sent timestamps. Requests8/16/17 remain customer_ready, no selected
direction, staging not_started. No payment, notice, build or enrollment was initiated.

This fixes the PHP scheduler failure safely; it does NOT activate automatic creative
generation, selected backend implementation, cloud dispatch or final launch.
Do not restore the entire old crontab blindly: reconcile current entries and use the
canonical exact-marker repair. Do not restore the broken broad drain as a workaround.

## Final exact-source local evidence

- 305 PHP tests /1563 assertions,27 Node,86 email,42 serializer and3 intent checks pass.
- 85 installed Drupal/SQLite assertions at
  `.artifacts/selected-staging-drupal/20260919T034306Z-65668/evidence.json`.
- Full fresh canonical journey at
  `.artifacts/fresh-customer-proof/fresh-customer-proof-20260919T034222Z-65677/evidence.json`.
  Both receipts explicitly bind378c3d86 and all assertions pass. Memory mail,
  signed synthetic payment, fixture DNS and isolated deployment are not provider proof.
- 34 portal-DNA and local real-React320–1280px checks passed on unchanged frontend code.

Running both disposable suites concurrently initially exhausted local disk during
core copying. The harnesses cleaned their owned temporary runtimes; no user data was
deleted. Sequential final runs passed. Retain the failed logs; serialize these suites
on this nearly-full Mac instead of discarding data or weakening assertions.

## Finite remaining work and commercial boundaries

Cloud account/project remain unconfigured. No retired agents, unrelated credentials,
Cloud Run execution or laptop-unavailable end-to-end test was used. Mac heartbeat
remains the orchestrated bridge. Static continuation is not WooCommerce fulfillment.

Read-only independent audit of commerce follow-updc3eae6d found four three-way
conflicts: routing.yml, services.yml and both SITE_LEARNINGS files. Integrate only
that follow-up in isolation, preserving current routes/services/receipt/source guards.
Its mock-order/gateway tests do not prove native persistence/refresh, authenticated
HTTP/CSRF, provider webhook/3DS, failure/uncertain outcome/refund or responsive checkout.
Keep request16 checkout flagOFF and capture mail; no real card charge as a test.

Important policy interpretation: Fritz explicitly authorized request16 payment after
direction selection as a private exception. Do not silently replace this with the
ordinary accepted-staging-first requirement. Reconcile the exception explicitly with
current scope/selection-version checks at every payment boundary; subsequent edits
must not reuse stale authority. Payment still does not authorize final launch.
Request17 must resume native order21/payment5 ($200 Zelle), never another sale/grant.

Brother K proof delivery is already complete: campaign24, Build DNA45, outbox775,
SMTP acceptance02:34:27Z. His submitted details are owner-confirmed; speculative
growth ideas remain recommendations. Do not change his verified account or resend.
