# Command-center mockup parity review — 2026-09-09

## Scope and evidence

This is a read-only comparison of the supplied Kimi/Kimmy artifacts with the
current integration worktree and its checked-in runtime captures. It does not
claim that a static prototype is production proof.

Reviewed references:

- `/Users/famtastic-fritz/Downloads/app/index.html` and `desktop.html`, plus
  `FAMtastic-Ops-Design.md`;
- `Kimi_Agent_FAMtastic Desktop Mockup.zip` (the same two HTML artifacts);
- `famtastic-ops-portal-prototype.zip`; its `(1)` copy is byte-identical
  (`3fbbf328…c325`), so it was reviewed once; and
- `Kimi_Agent_Kimi.zip`, classified as a separate public Scout/acquisition
  mockup rather than an Operations or authenticated Portal parity target.

Runtime evidence inspected was
`fd-command-center-integration/.artifacts/runtime-command-center-results`,
including Operations Home, a native node form, login at 390/768/1280, and the
customer portal at 390/768/1280. The full Ops prototype describes live-count
drill-in, marketing queue, independent social gates, three-way proof review,
support/messages, and a customer-side human-approval guard. Those are the
comparison criteria, not a direction to retain fixture-only behavior.

## Verdict

The implementation convincingly adopts the visual language and has a real
Drupal shell, native form, real route, and responsive portal rather than
screenshots. It is **not yet feature-parity evidence for the supplied command
center**. The highest risks are stale/contradictory browser evidence and the
absence of runtime proof for the prototype's staff workflows and mobile staff
surface.

### Matches

| Reference intent | Evidence observed | Assessment |
| --- | --- | --- |
| Near-black panels, lime primary action, restrained borders, uppercase metadata, and semantic status color | Operations, native form, login, and portal captures use the same dark/lime family and card rhythm. | Match |
| Desktop staff left rail | Drupal has a 14rem desktop rail for the FAMtastic navigation at 783px and above. | Match, despite the remaining Drupal toolbar above it. |
| Mobile thumb-zone navigation and 44px targets | Portal 390/768 captures show bottom navigation; theme CSS specifies a six-column fixed staff rail with 44px minimum links. | Design match; staff runtime is not captured at mobile sizes. |
| Native Drupal rather than route-specific mock markup | Captures show authenticated Operations, `/admin/content`, and an Article form inside the theme. Page template preserves `page.pre_content`. | Deliberate and valuable improvement |
| Customer next-decision and staged journey | Portal evidence presents a visible next decision, project progression, help/recovery, and explicit staging-before-payment language. | Deliberate improvement over optimistic mock actions |

### Deliberate improvements worth retaining

- The prototype's fixture-backed count cards and in-memory mutations have been
  replaced by permission-protected Drupal surfaces and an empty-workspace
  portal. That is more truthful than displaying fabricated work.
- The portal's compact bottom navigation and the theme's generic Drupal
  primitives are more reusable than a screen-specific static shell.
- The real security-update alert is a legitimate exception, but its visual
  prominence needs an intentional operational priority model (see repairs),
  not removal or suppression.

### Unacceptable drift or unproven claim

1. **P0 — current browser evidence is internally inconsistent.** The runtime
   verification document says the fresh suite passed and gives login SHA-256
   values, but the three stored login images hash to different values. The
   stored 390 and 1280 captures visibly include `Powered by Drupal`, while the
   current uncommitted test asserts that text has count zero. `.last-run.json`
   says only `passed`; it cannot bind the captures to the current test source.
   Do not use this folder or its document as release proof until regenerated
   from the exact integration revision and hashes are reconciled.
2. **P1 — staff information architecture is materially thinner than the Ops
   prototype.** The evidence shows a long, generic 17-card Operations Home
   grid. The reference calls for a scan-first live-count matrix that opens a
   filtered record drawer, a queue with approve/hold states, recent activity,
   exception/support panels, and a focused proof-review area. The current
   evidence has useful links but no demonstrated count-to-record drill-in,
   queue decision, or contextual record panel.
3. **P1 — the supplied staff mobile experience is unproven.** Login is
   captured at 390/768/1280, but Operations, native forms/tables, settings,
   attention, marketing, social, proof review, and recovery are captured only
   at 1280 or not at all. The reference's mobile control density and fixed
   thumb-zone workflow therefore have no runtime parity evidence.
4. **P1 — customer portal architecture intentionally diverges without a
   documented decision.** The prototype specifies a desktop top-module
   navigation plus a visible Shay rail on Messages; current portal evidence
   uses a persistent left rail and has no captured Messages/Shay state. Either
   restore/prove the message-review pattern or document why the alternate
   navigation preserves its recovery and human-approval affordances.
5. **P2 — staff shell is visibly split by the unthemed core toolbar.** The
   FAMtastic left rail begins below a dense Drupal toolbar in the Operations
   and native-form captures. That is understandable for native administration,
   but it breaks the prototype's single calm command-center hierarchy. It
   needs a documented, accessible toolbar integration or an explicit
   staff-route decision.

### Missing states

- Marketing queue empty/loading/error/hold and an audited approve action.
- Social Content, Media, and Publish gates demonstrated independently.
- Safe/Wild/OMG selection, zero/one/multiple approval, and a truthful client
  share preparation state.
- Portal messages with the Shay draft visibly unable to send until a human
  confirms; support escalation/recovery at mobile and desktop.
- Authenticated staff mobile screenshots and keyboard traversal, including
  focus order through the fixed navigation, a narrow native table, and a
  details/error-recovery page.
- A non-admin staff role and customer role proving both least privilege and
  the absence of staff navigation/routes.

## Exact highest-priority repairs

1. **Regenerate authoritative evidence first.** From one clean, fresh SQLite
   copy of the integration revision, run the complete browser suite, write its
   git revision and command into `.last-run.json`, replace all artifacts,
   regenerate every recorded SHA-256, and fail the evidence check when a
   screenshot contains forbidden public-theme branding. Do not retain old
   captures under a passing result.
2. **Make Operations a real scan-to-record workflow.** Keep the truthful empty
   state, but on real records make each live count link to a permission-checked
   filtered native list; add a record detail/triage route or accessible drawer
   only where the action exists. Include explicit pending/held/failed/recovery
   states rather than decorative counts.
3. **Add hermetic runtime cases and captures at 390, 768, and 1280** for
   Operations, table/form, settings/error recovery, Marketing, social gates,
   proof review, Portal Messages, and Support. Assert action destinations and
   role boundaries, then exercise keyboard focus and the fixed nav's 44px
   targets.
4. **Resolve the staff hierarchy deliberately.** Either integrate/suppress the
   core toolbar on command-center routes without removing native accessibility,
   or document it as an intentional administrator-only utility bar and give
   the FAMtastic rail the primary visual/keyboard order.

## Cross-lane safety and functional review

The integration working-tree diff was inspected with `git diff --check` (no
whitespace errors). This review found no production, email, payment, DNS, or
deployment action in the diff. It did find the following release concerns:

| Priority | Finding | Why it matters | Required resolution |
| --- | --- | --- | --- |
| P0 | Evidence/document mismatch described above. | A passing claim currently cannot be traced to the shown images or current test assertions. | Regenerate and bind evidence before acceptance. |
| P1 | `famtastic_pipeline.info.yml` now hard-requires Commerce, Webform, Node, and Views to install the pipeline module. | A reusable visual theme should not make a compatible staff site unable to enable its pipeline solely because optional Commerce/Webform modules are absent. | Move login theme negotiation into the theme or a minimal companion module, or document/split the product-specific dependencies and test both supported installation profiles. |
| P1 | `FamtasticAdminThemeNegotiator` always returns `famtastic_admin` and has no availability/active-theme contract. | Enabling the pipeline module without that custom theme becomes an undocumented compatibility hazard. | Add an explicit install/enable contract and a graceful guard or make the small companion extension depend on the theme installation path. |
| P1 | Permission proof is only anonymous 403 plus a login as `admin`; portal evidence is route-mocked. | It does not prove staff-role least privilege, customer/staff isolation, or actual lifecycle data. | Add a non-admin staff fixture and customer fixture; state mock-backed limits in release evidence. |
| P2 | The portal test calls no visible action and only checks the `Open Projects` button's size, focus, contrast, and containment. | The claim of all visible links/actions being reachable is broader than the exercised behavior. | Traverse/click every displayed action in the fixture and assert the registered destination or explicit disabled semantics. |
| P2 | The theme-negotiator unit test is source-string inspection, not negotiator behavior against route matches/theme discovery. | It can pass while the service is misregistered or returns a non-existent theme. | Add an actual unit/kernel test with route-match doubles and a theme-enabled integration case. |

The recent committed staging/receipt work was not reimplemented or mutated in
this lane. Its reviewed UI-facing result is positive: the current portal copy
says staging review precedes checkout. The reviewer did not treat that copy as
proof of an actual payment/dispatch lifecycle; the existing isolated portal
test uses mocked empty workspace responses.

## Review limitations

No browser was driven and no application code, database, customer record,
network endpoint, payment, mail, deployment, or production system was touched
for this report. Visual findings are based on the supplied captures and source
contracts. The report deliberately does not promise parity for proprietary
module markup outside the documented generic Drupal primitive layer.
