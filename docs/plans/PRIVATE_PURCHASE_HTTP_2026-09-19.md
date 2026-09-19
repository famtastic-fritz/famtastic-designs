# Private purchase HTTP and browser checkpoint — September 19

## Boundary

Local disposable Drupal/SQLite only. Production retains deployed378c3d86 and
private checkout OFF. No production completion code, order, payment, email,
selection or deployment was changed. Ordinary satisfaction-before-payment and
the exact16/17 exceptions are unchanged. This is not Stripe/provider approval.

## What the real browser found

The customer theme inherited Olivero's white page/region layout while applying
near-white text. The new private form was unreadable despite passing service and
HTTP assertions. Its inherited logo was also absent. Fix: one exact-route page
suggestion, a restrained dark shell and the byte-identical canonical PNG, with
native content/error/footer regions retained. Other checkout/account pages are
not restyled or reported visually approved. Shared CreatorCredit is reused.

Independent source review confirmed route scope and found a CSS specificity
collision: the general input rule beat the lime submit rule. Excluding submit
inputs fixes that; actual browser computed values show lime rgb(124,252,0),
dark text rgb(7,16,0), and a54px-high submit control.

CUA observations on the actual authenticated native form:

- Real local customer sign-ins; no production credentials/session impersonation.
- Reunion scope expanded/collapsed, honest disabled state and active form viewed.
  Local-only flag was enabled for form inspection, with all transports disabled.
  No browser purchase/consent submission or provider checkout was performed.
- 320px: document305px, no horizontal overflow, intact loaded logos,54px submit,
  72px checkbox label. 390px was also visually checked. Keyboard project-link
  focus has a2px lime outline. The native select's padding was narrowed so its
  selected option remains readable on320px.
- 1280px: document1280px,50px disclosure,54px select and submit.
- Prepaid390px: same synthetic paid receipt, no submit/payment button, no overflow,
  scope acknowledgement explicitly separate from acceptance/launch.
- Correct request-bound `/portal?section=projects&request=…` return href verified.
  The standalone PHP fixture does not serve React, so this is not a completed
  portal-return navigation test.

The earlier full-page screenshot export in
`.artifacts/selected-staging-drupal/20260919T054220Z-81181/` produced a malformed
`private-prepaid-mobile.png`; retain it as a failed export, not visual approval.
Live CUA observations above remain separate from exported-image quality.
Fresh normal-viewport captures in
`.artifacts/selected-staging-drupal/20260919T061151Z-86270/` are the reviewed files:
`private-prepaid-mobile-viewport.png` (390x844 viewport) and
`private-prepaid-desktop-viewport.png` (1280x900). The saved mobile file was reopened
and visually verified. Both show disposable records, not live customer receipts.
The prepaid page has no submit/payment control and both exact PNGs load. Browser
viewport was reset and the disposable runtime was cleaned up afterward.
Final browser CSS SHA256:
`8d0a8af1cc5195be8f79dde647cd37e2f8dbdc58ba847981a91202f3b4bcc3ef`.
The earlier054220 HTTP receipt predates final CSS refinements; the final HTTP
reruns below bind the final CSS. Never silently overwrite historical receipts.

## Reusable isolated HTTP runner

Run with a matching existing backend runtime, Node22 and serial execution:

```sh
FAMTASTIC_BACKEND_VENDOR=/path/to/matching/backend/vendor \
  bash scripts/test-selected-staging-drupal.sh --private-purchase-http
```

Optional `FAMTASTIC_PRIVATE_HTTP_BROWSER_HOLD=1` holds the loopback server for
20minutes after passing; Enter cleans up earlier. Only the fresh exact mktemp
runtime/database is removed; evidence remains. Never run this fixture against
production. Native fixture identity hooks are retained; test usernames match the
portal's email-as-username contract. Fixture credentials/completion code live
0600 outside the served tree, not in receipts. PHP network/mail transports and
Drupal outgoing HTTP are blocked, child environment allowlisted, all mail captured.

The HTTP checks exercise real cookie login, tenant and staff denial, native CSRF,
raw signed-scope omission/tampering, stale displayed scope, consent/domain
validation, one unpaid custom-price order/replay, saved-gateway denial, same-paid-
purchase completion/replay, and feature-OFF denial with the saved gateway reset.
Record projections remain unchanged for jobs, outbox, fulfillment and entitlements.

Retained failed attempts were harness defects, not permission to weaken gates:
Drupal bootstrap needed the correct working directory; native customer usernames
must be their emails; first successful redirect adds `check_logged_in=1` while
replay omits it. Exact origin/order path and only that query parameter are allowed.
The local router's denied root directory caused a logout landing404; it now routes
only `/web/` through Drupal while continuing to refuse settings/database files.
Drupal then returns its existing302 to the public agency homepage. The HTTP test
verifies that exact destination without following it; this is not a successful
browser logout-to-portal test and never navigates a fixture into production.

## Final local receipts

- 38/38 authenticated HTTP assertions:
  `.artifacts/selected-staging-drupal/20260919T055122Z-82576/private-purchase-http.json`;
  repeated38/38 in061151Z-86270 before final screenshot capture. Tracked sanitized
  copy: `docs/evidence/private-purchase-http-20260919.json`.
- 36/36 native Commerce assertions:
  `.artifacts/selected-staging-drupal/20260919T055150Z-83508/private-purchase.json`.
- Complete canonical journey and frontend build:
  `.artifacts/selected-staging-drupal/20260919T055211Z-83708/canonical.json`, exit0.
  Journey and lifecycle evidence parsed: every Boolean check true, three proofs;
  all notification/payment/DNS/deployment effects synthetic/captured. Existing
  unresolved portal-PNG and large-chunk build warnings remain recorded.
- Portal DNA34 and email86 passed; syntax, asset identity and diff checks passed.
- Prior339 PHP/2537 assertions and85 selected-staging tests remain separate earlier
  receipts, not claimed reruns here. The three financial source hashes are unchanged.

These runs used parent HEAD0f509157 plus the uncommitted HTTP/presentation changes;
the receipts bind exact tested file hashes. The resulting source/docs commit is
recorded in Git. A branch push is not deployment. Local tests do not establish
successful Stripe/native checkout or real customer acceptance.

Final independent read-only review cleared the bounded source-only commit/push:
both38-check HTTP receipts match current application/harness/presentation hashes;
route scope, native messages, gateway-reset separation and fixture boundaries were
reviewed. It did not independently execute providers or claim visual approval.
Parent CUA evidence covers the actual local rendered pages. Checkout staysOFF.

## Remaining activation gates

Successful native gateway-enabled checkout, test-provider receipt/webhook/3DS,
uncertain/failure/refund handling, complete portal-to-native navigation, error
state presentation, deployed Apache/cPanel middleware and concurrent MySQL remain
separate. The account-page theme and staff-labelled native test login are not
covered by this exact-route presentation fix. Do not extrapolate it globally.

The previous339 PHP/2537 assertions cover unchanged financial service/form/guard
source. This checkpoint adds HTTP/browser evidence, not a new payment provider.
Client8/16/17 remained unselected on the06:10Z production read; outboxes772/773/775
remain sent once. Actual06:10:03Z CLI cron was observe_only with zero mutations,
enrollment and reservations. No idle generation or duplicate notification.
