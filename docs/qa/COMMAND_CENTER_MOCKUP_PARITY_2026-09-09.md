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

## Verdict after integration repairs

The implementation now establishes the supplied prototype's command-center
language as a real reusable Drupal theme and customer portal, rather than a
screen-specific CSS patch or a screenshot. The exact isolated runtime renders
login/reset, Operations, native Drupal tables/forms, settings, every Operations
record-list destination, Attention, and every customer-portal section without
a dead placeholder link or user-scrollable page overflow. Phone, tablet, and
desktop captures were regenerated from the current working tree.

This is parity for the shell, navigation model, live-count drill-in, mobile
containment, and truthful empty/recovery states. It does not turn the static
prototype's invented queue data into product data. Marketing, social,
proof-review, messaging, and support remain real product workflows with their
own permission and lifecycle gates; staging acceptance must verify them against
the protected environment's sanitized fixture before release.

### Matches

| Reference intent | Evidence observed | Assessment |
| --- | --- | --- |
| Near-black panels, lime primary action, restrained borders, uppercase metadata, and semantic status color | Operations, native form, login, and portal captures use the same dark/lime family and card rhythm. | Match |
| Desktop staff left rail | Drupal has a 14rem desktop rail for the FAMtastic navigation at 783px and above. | Match, despite the remaining Drupal toolbar above it. |
| Mobile thumb-zone navigation and 44px targets | Portal and authenticated staff Operations/native-form captures now cover 390/768/1280. Portal targets are measured at 44px or larger and staff navigation remains reachable without page overflow. | Match |
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

### Resolved findings and deliberate boundaries

1. **Resolved — evidence mismatch.** The old captures were replaced, public
   `Powered by Drupal` branding is absent from the auth shell, and the runtime
   document records the regenerated hashes. Release evidence is rerun again
   after the implementation commit so the tested source revision is explicit.
2. **Resolved — generic Operations grid.** Operations now begins with fourteen
   live counts and six focused workspaces. Every live count links to its exact,
   permission-checked record list; the isolated browser gate opens all fourteen
   destinations and Attention at 390px.
3. **Resolved — missing staff mobile proof.** Operations and native Drupal
   forms/tables are rendered and captured at 390, 768, and 1280. The expanded
   traversal found and repaired a real narrow-screen table overflow by adding
   a theme-level scroll boundary for all Drupal render-array tables.
4. **Deliberate — portal navigation.** The implementation keeps the persistent
   desktop rail and compact mobile bottom navigation because they preserve
   orientation across thirteen real portal sections. Messages and Shay remain
   distinct destinations; all sections are opened by the browser test at all
   three widths. Human approval remains enforced by the lifecycle services,
   not simulated in the shell.
5. **Deliberate — Drupal toolbar.** The authenticated core toolbar remains an
   administrator-only utility layer so contributed modules retain dependable
   administration and accessibility. The FAMtastic rail is the primary product
   navigation beneath it; the public Drupal header, branding, and menus are not
   rendered by the command-center template.

### Protected-staging acceptance still required

- Marketing queue empty/loading/error/hold and an audited approve action.
- Social Content, Media, and Publish gates demonstrated independently.
- Safe/Wild/OMG selection, zero/one/multiple approval, and a truthful client
  share preparation state.
- Portal messages with the Shay draft visibly unable to send until a human
  confirms; support escalation/recovery at mobile and desktop.
- A non-admin staff fixture and customer fixture proving both least privilege
  and the absence of staff navigation/routes against the protected staging
  database. The isolated local gate already proves anonymous staff denial and
  an authenticated administrator.

## Exact release sequence

1. Commit the integrated theme, portal, lifecycle, runtime harness, and this
   resolution record together.
2. Rerun unit, contract, build, complete fresh-customer journey, and browser
   evidence on that exact implementation revision.
3. Deploy that immutable revision to the isolated protected staging target
   with a separate database/files/config and real email, payment, schedulers,
   and customer/provider transports disabled.
4. Run authenticated staff/customer role boundaries and the complete staging
   smoke matrix, then notify the owner only after those receipts pass.

## Cross-lane safety and functional review

The integration working-tree diff was inspected with `git diff --check` (no
whitespace errors). This review found no production, email, payment, DNS, or
deployment action in the diff. It did find the following release concerns:

| Priority | Finding | Why it matters | Required resolution |
| --- | --- | --- | --- |
| Resolved | Evidence/document mismatch described above. | Stale evidence could support a false release claim. | Captures and hashes are regenerated; final exact-revision run remains the promotion gate. |
| Resolved | The product pipeline declares Commerce, Webform, Node, and Views. | Undeclared direct API use caused fresh-install failures. | The visual theme stays independently reusable; the product-specific pipeline now truthfully declares the modules it directly uses. |
| Resolved | Theme negotiator could have returned a missing theme. | A module enable could break auth/admin rendering. | The negotiator now returns the theme only when `themeExists()` succeeds and has behavioral unit coverage for four auth routes, unrelated routes, and the missing-theme case. |
| Staging gate | Local permission proof is anonymous denial plus administrator; the portal is route-mocked. | It does not alone prove staging role isolation or real lifecycle data. | Create sanitized non-admin staff/customer fixtures in protected staging and run the role matrix before owner notification. |
| Resolved | Portal test previously inspected one action without following it. | A visible button could still be a dead end. | The test now clicks `Open Projects` and opens every registered portal section at 390/768/1280. |
| Resolved | Theme-negotiator test previously inspected source strings. | Text inspection could pass while runtime behavior was wrong. | Behavioral mocks now cover matching, rejection, and graceful missing-theme behavior; the fresh Drupal browser runtime proves actual registration. |

The recent committed staging/receipt work was not reimplemented or mutated in
this lane. Its reviewed UI-facing result is positive: the current portal copy
says staging review precedes checkout. The reviewer did not treat that copy as
proof of an actual payment/dispatch lifecycle; the existing isolated portal
test uses mocked empty workspace responses.

## Review limitations

The original reviewer did not drive a browser. The integration follow-up did
drive Chromium only against a disposable local SQLite runtime and mocked empty
portal APIs. No customer record, payment, mail, DNS, external provider,
deployment, or production system was touched. Protected staging remains the
boundary for real role and hosting-specific verification.
