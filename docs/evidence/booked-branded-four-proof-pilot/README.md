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
- Public base live at
  `https://famtasticdesigns.com/showcase/booked-and-branded-pilot/`.

## Safety and truth boundary

All names, people, services, prices, requests, reviews, appointments, and
payment examples are fictional. No customer list, customer account, Drupal
Prospect, campaign, email recipient, payment processor, or production booking
record was used. No email was sent. The Booking Desk, deposit link, QR card,
and review controls are non-submitting visual demonstrations.

This public static showcase proves the product story and responsive visual
system. It does not replace the CRM-bound signed public-preview lane required
for real recipient proofs.

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

- Journey evidence: `009262ea080bcbf4a0dae7da37ee9505803709010e3cdde38b36e5b7fa817be8`.
- Lifecycle evidence: `fdfd89029b176362f313f7c9e6edbbf2ad783b83cab149b84f4a495b001d59c0`.

## Review state

Primary visual review passed across the overview, package page, all four rooms,
all four operator-first mobile pages, the representative Shay email, the
complete three-direction desktop set, and all 12 source images. Independent
review is deliberately reserved for the owner.

## Production acceptance

The governed frontend deployment published commit
`b91038f2db841b9375d3fdd912ba636c04088361` and retained rollback archive
`/home/xrdj7j99xhzt/backups/famtastic-frontend-20260827T200346Z-b91038f2db841b9375d3fdd912ba636c04088361.tgz`.

Real-browser acceptance covered the existing React homepage and all 22
showcase routes on both `https://famtasticdesigns.com` and
`https://www.famtasticdesigns.com`. Apex desktop and `www` mobile checks found
HTTP 200, a rendered H1, the fictional disclosure, `noindex`, no horizontal
overflow, no console/page errors, and no broken images. All 12 final Gemini
assets returned `200 image/jpeg` with their exact committed byte sizes and
SHA-256 on both hosts. See `live-acceptance.json`.
