# Booked & Branded four-proof pilot evidence

Date: 2026-08-27

## Outcome

- Four fictional Florida beauty businesses.
- One email preview and one three-direction concept room per business.
- Twelve responsive proof sites: Signature Editorial, Signal Campaign, and
  Chairside OS.
- Twelve distinct reference-led Gemini images, one per proof direction.
- One complete package-proposal page and one reusable shape/type/message/image
  creative-system contract.
- One starter-first value ladder: proposed $199 launch, one included starter
  booking path, normal $9.99 monthly hosting beginning in month 13, existing
  optional $149 Appointment Scheduling setup, and later growth choices.
- One owner-QR payment boundary: the site can display the business's approved
  Cash App or existing payment QR, but FAMtastic does not process, receive,
  settle, or reconcile the payment. Payment-processing and optional messaging
  costs are paid directly by the business to its chosen providers.
- Public base live at
  `https://famtasticdesigns.com/showcase/booked-and-branded-pilot/`.

## Safety and truth boundary

All names, people, services, prices, requests, reviews, appointments, and
payment examples are fictional. No customer list, customer account, Drupal
Prospect, campaign, email recipient, payment processor, or production booking
record was used. No email was sent. The Booking Desk, payment QR, and review
controls are non-submitting visual demonstrations.

This public static showcase proves the product story and responsive visual
system. It does not replace the CRM-bound signed public-preview lane required
for real recipient proofs.

The booking choices are proposal paths rather than active integrations. The
showcase does not connect a Google, Cal.com, payment, messaging, or customer
account. It records the portable Site Studio contract—including the explicit
`payment.processed_by_famtastic: false` and direct-provider fee ownership—and
leaves provider ownership, commercial selection, setup, testing, and
authorization for a later real customer workflow.

## Image evidence

The final image series ran through the Gemini Developer Interactions API with
model `gemini-3.1-flash-lite-image`. The first 12-image pass was rejected
because several outputs invented poster text or interface overlays. A corrected
12-image pass produced 11 approved images; one targeted Palmera replacement
removed its remaining generated sign. The final set therefore contains 12
approved images from 25 provider generations.

The receipt records interaction IDs, usage objects, prompt/reference/output
SHA-256, timings, selection decisions, and an estimated cumulative cost of USD
0.8400. Provider-billed cost remains pending reconciliation. The hard
owner-authorized ceiling was USD 1.00, and no premium fallback was called.

The selected image bytes, exact prompts, provider evidence, creative-system
contract, and final artifact hashes are recorded under
`frontend/public/showcase/booked-and-branded-pilot/` and its canonical
`build-dna.json`. Raw receipts for the three attempts are retained in
`provider-receipts/`; rejected image bytes are excluded from the public build.

## Local acceptance

- Static routes tested: 22.
- Viewport checks: 44 (1440px and 390px).
- Copy-contract checks: 21, including the owner-QR/no-processing boundary.
- Retained screenshots: 14.
- Broken images: 0.
- Horizontal-overflow failures: 0.
- Browser console/page errors: 0.
- Fictional disclosure missing: 0.
- QA report: `qa-report.json`.
- Screenshot directory: `screenshots/`.
- Demand-engine checks: passed.
- Frontend production build: passed; the existing bundle-size warning remains.

The standard customer-journey proof also passed with its required safe
adapters: local isolated database, memory email, stub payment, fixture DNS, and
isolated deployment. The two local evidence hashes were:

- Journey evidence: `19342e39e026077d070c365b17150d4727ead0e202c8d77149b75f01abe29d38`.
- Lifecycle evidence: `5e4b499ee846e7d6f7cdd293216bd3ce1c6d5ae88b3e94e3cc400072ca4ebc30`.

## Review state

Primary visual review passed across the overview, package page, all four rooms,
all four operator-first mobile pages, the representative Shay email, the
complete three-direction desktop set, and all 12 source images. Independent
review is deliberately reserved for the owner.

## Production acceptance

The governed frontend deployment published commit
`c7aef995404bc5c368ebc5fa05f4927d16ff8ab6` and retained rollback archive
`/home/xrdj7j99xhzt/backups/famtastic-frontend-20260827T204623Z-c7aef995404bc5c368ebc5fa05f4927d16ff8ab6.tgz`.

Real-browser acceptance covered the existing React homepage and all 22
showcase routes on both `https://famtasticdesigns.com` and
`https://www.famtasticdesigns.com`. Apex desktop and `www` mobile checks found
HTTP 200, a rendered H1, the fictional disclosure, `noindex`, no horizontal
overflow, no console/page errors, and no broken images. All 12 final Gemini
assets returned `200 image/jpeg` with their exact committed byte sizes and
SHA-256 on both hosts. This is 44 route checks, two existing-homepage checks,
and 24 exact image checks. The former `$19.99` recommendation is absent; the
live package instead shows the proposed `$199` launch, normal `$9.99` hosting
from month 13, the optional `$149` scheduling setup, and later growth choices.
The live run also confirms that no payment-processing offer is present and that
the owner-QR/direct-provider-cost boundary appears. See `live-acceptance.json`.
