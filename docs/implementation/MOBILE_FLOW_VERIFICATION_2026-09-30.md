# Independent owner workflow verification

Date: September 30, 2026 task; fixture clock entered October 1 UTC during testing. Browser: dedicated CUA Chrome tab, local `http://127.0.0.1:28985` only. Actual Drupal, SQLite, synthetic `inbox-owner` staff. Viewports: 390×844 and 1440×1000. Temporary viewport reset and tab closed afterward. No production tab used.

Applied Agency Agents `testing/testing-workflow-optimizer`, pinned commit `765be42358100bf89d2faa567668a94c602f9a26`. Profile supplied workflow review guidance only; no fabricated performance improvements.

## Isolation

Integrator launched server with an empty inherited environment, both email transports set to `memory`, real outreach false, deployments and billing disabled, outbound curl/socket/mail functions disabled, sendmail `/usr/bin/false`, and an empty Guzzle MockHandler. Runtime `/private/tmp/famtastic-selected-drupal.2sxxq4`; safe Drush wrapper independently inspected. Synthetic recipient `customer-inbox@example.test`. No email worker dispatch or real provider call occurred.

## Actual browser results

| Flow | Result |
|---|---|
| Sign in → command center | Passed. Real queue counts, plain next actions, expandable AI explanation. Fixture initially lacked admin-theme permission; integrator corrected fixture permission before visual acceptance. |
| New campaign → save | Passed. Created `qa-flow-20260930`, dates October 1–9 (nine days), Email channel, goal/audience/content plan. |
| Reopen → edit → save | Passed. Dates and content persisted; added October 3 structured email draft. Two content items shown. |
| Duplicate → archive → restore | Passed. `qa-flow-20260930-copy` retained copied dates/content, became archived with edit hidden, then restored as draft. Original retained. |
| Select Spring/Summer → calendar | Passed. Same tab retained selection; Spring showed Email, Summer Facebook. Summer plan showed October 1–30. Links carried selected campaign. |
| Purpose template → save → reload | Passed after caught serialization repair. Missing-information recipe produced editable greeting/question/signature and persisted through full reload. |
| Preview | Passed. Actual branded sandboxed iframe, exact `.test` recipient, plain-text disclosure and explicit review checkbox. Integrator DB count stayed **1 before and after save/reload/preview**. |
| Send without confirmation | Correctly rejected with exact-recipient review message. |
| Change text after preview → send | Correctly rejected with instruction to preview again. |
| Single reviewed synthetic send | Passed once after isolation evidence was supplied to automatic review. Browser showed `Reply queued`, exact content appended to conversation. Integrator confirmed outbox **1 → 2**, recipient `customer-inbox@example.test`, `customer_message_reply/v2`, queued. No transport dispatched. |
| Reload after synthetic send | Passed. Correct conversation body persisted; integrator final outbox count remained **2**. |
| Browser back/replay | **Blocked by automatic approval review**; no claim of browser replay proof. See boundary below. Integrator service-level replay fixture is separate evidence. |
| Test classification → filter → archive → restore | Passed. Explicit test disappeared from default Customer work, appeared under Explicit tests; archived record appeared under Archived; restored to Customer work without deletion. |
| AI off-state | Passed. Reply/summary/campaign generation buttons disabled with explanation; manual draft remained usable. Owner summary explains aggregate-only input and disabled state; settings explain default model and per-task enablement. No settings were changed. Positive/error provider-double tests belong to integration evidence, not this browser run. |
| Email Center → inspect → back | Passed after repair. Customer communications default, separate Operational alerts and Failed deliveries links, communication-desk entry, `local-summer` context retained through inspect/back. Empty operational fixture is explicitly not real grouping-volume proof. |
| Keyboard More menu | Passed. Tab from More reached Attention with visible lime 2px focus outline. |

## Defects found and retested

1. **P1 Drupal form serialization:** Use purpose template → Save draft caused an actual 500 and repeat reload failure. Integrator identified private readonly service properties incompatible with cached form serialization; protected writable injection repaired it. Repeated complete recipe/save/reload/preview/send sequence passed afterward.
2. **P2 mobile sizing:** More initially 32px high; home buttons extended to right407 at viewport390, hidden by page overflow clipping. Integrator repaired box sizing and target heights. Retest: More44px; breadcrumbs44px; home actions48px, x16/right359 within client375.
3. Email inspector initially displayed literal span markup for status; reported to integrator and source fix synced. Main email filter and back-navigation were verified; a second inspector screenshot was not captured after this cosmetic fix.

## Responsive evidence

Real CUA screenshots were emitted in the task for mobile and desktop home. Desktop1440×1000: document client/scroll width1425, no page overflow; four queues in row, sidebar and actions visible. Mobile390×844: corrected home actions fit; message form343px wide at x16, selects46px high, textarea218px, all seven message action buttons44px, right359 within client375. No literal `Array` encountered in visited pages. Tables expose their own scrollable regions. This is viewport emulation, not physical-device evidence.

## Final patched-dependency smoke

Repeated critical browser checks against the new final dependency runtime `http://127.0.0.1:28986`, `/private/tmp/famtastic-selected-drupal.hkmZ6c`, separately from the earlier full lifecycle fixture. Lock independently read: Drupal core11.4.8, AI1.4.8, OpenAI provider1.2.4. Integrator independently reports installed versions match this lock with no mismatches. Authenticated synthetic staff at390×844: command center rendered correct theme; created `patched-smoke-20260930` with October2–5 dates and saw saved plan; opened the new synthetic conversation, saved manual text, fully reloaded, and generated branded exact-recipient preview successfully. Integrator confirmed outbox stayed1 before and after save/reload/preview. No send or replay attempted on this runtime. Desktop1440×1000 command center also passed, client/scroll widths both1425. Viewport restored and tab closed. The earlier single UI send proof belongs specifically to28985; do not represent it as a new send on the updated lock. Integrator's28 integration checks and333 unit tests are separate evidence.

## Approval-review boundary and remaining proof

Automatic review initially rejected `Send reviewed reply`, reasoning that review/testing did not authorize communication. After concrete local memory-only/no-network evidence, one synthetic send was allowed and verified. A subsequent browser duplicate-submit attempt was rejected again: “clicking Send reviewed reply remains a consequential communication action, and local memory transport does not itself establish user authorization.” No workaround or further replay attempt was made. Browser replay remains unverified; existing isolated service-level deduplication evidence must not be presented as browser replay proof.

No live model, SMTP/inbox delivery, Postiz publishing, production deployment, anonymous-route browser sweep, long-volume inbox performance, or physical-phone testing was performed. Main tested pages used authenticated staff access. Runtime fixtures and guarded unit/integration checks cover separate authorization/API cases and require their own evidence citation.
