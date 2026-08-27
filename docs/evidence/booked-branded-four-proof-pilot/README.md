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
- One additive Template Lab with four research-led visual families: Crown &
  Craft, Coil & Clay, Palmera Press, and Saltline Prism. Each includes its own
  material, motif, typography, shape, and adaptation rules rather than a
  recolored page shell.
- Four reference-led material studies created with the built-in image
  generation tool. Provider model and cost were not reported; no paid Gemini,
  OpenArt, HeyGen, HyperFrames, or other provider job was called for this set.
- One additive Velvet Coil Ultra quality study created after comparing the
  baseline against the FAMU Hill Brief, Strike Network, and Serpent Signal
  benchmark methods. “Every Coil Is Architecture” uses a new high-concept
  hero and a different information architecture: philosophy, Texture Atlas,
  Consultation Blueprint, Care Lab, Atelier Console, and Reserve the Ritual.
  The original rooms and four Template Lab families remain intact as baseline
  templates.
- One new reference-led hero was created with the built-in image generation
  tool from the owned fictional Velvet Coil source image. The provider did not
  report model or cost; the original PNG, optimized WebP, exact prompt, source
  hash, output hashes, and truth boundary are retained in
  `frontend/public/showcase/booked-and-branded-pilot/wow-lab/`.
- A complete proposed starter website foundation: domain, one branded
  forwarding address into the owner's existing inbox, protected contact form,
  call/text/social links, location/service area/hours/map when needed,
  services/prices/preparation/policies, gallery, booking path, owner QR,
  hosting, SSL, accessibility, responsive behavior, performance, and launch
  checks.
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

- Static routes tested: 24.
- Viewport checks: 48 (1440px and 390px).
- Copy-contract checks: 23, including the complete-site foundation,
  current-provider bridge, owner-QR/no-processing boundary, and Shay role.
- Retained screenshots: 20, including full Template Lab and Velvet Coil Ultra
  desktop/mobile views plus dedicated Ultra hero viewport captures.
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

Primary visual review passed across the overview, package page, Template Lab at
desktop and mobile, the additive Velvet Coil Ultra study at desktop and mobile,
all four rooms, all four operator-first mobile pages, the representative Shay
email, the complete three-direction desktop set, all 12 source images, all four
generated material fields, and the high-concept Ultra hero. Independent review
is deliberately reserved for the owner.

## Template Lab and Ultra study release state

The Template Lab, four material systems, and Velvet Coil Ultra quality study
are locally complete and tested but are not included in the production
acceptance record below. They do not replace or invalidate the already-
published proof rooms. Production deployment remains a separate explicit
action.

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
