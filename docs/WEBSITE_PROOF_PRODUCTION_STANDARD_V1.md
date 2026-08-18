# FAMtastic website proof production standard v1

Status: approved operating standard  
Owner: Fritz Medine  
Effective: August 17, 2026  
Routine: `website_proof.generate.v1(source_id)`

## Outcome

Every qualified website request must become an auditable, customer-owned path
from intake to three genuinely different working concepts. The system prepares
the work automatically, stops at explicit human approval gates, keeps prices
deterministic, and preserves enough evidence to reproduce what happened.

The customer promise is simple:

1. Register or use the public request path.
2. Complete the website brief.
3. Review three working concepts: Safe, Wild, and OMG.
4. Select a direction and request refinements.
5. Confirm scope and payment.
6. FAMtastic deploys the approved release.

## Canonical journeys

### Registered customer

```text
Verified account
  -> reusable website request
  -> normalize and validate intake
  -> research and creative planning
  -> exactly three working proofs
  -> automated quality checks
  -> FAMtastic owner review
  -> proof-ready account notification
  -> customer selection or revision request
  -> deterministic scope and price
  -> payment or scoped grant redemption
  -> refinement and approval
  -> isolated deployment
```

### Public lead

```text
Public lead
  -> lawful-contact and consent checks
  -> normalize and validate intake
  -> research and three working previews
  -> automated quality checks
  -> FAMtastic owner review
  -> private proof email
  -> registration
  -> account-to-lead association
  -> detailed portal intake
  -> selection and refinement
  -> payment
  -> isolated deployment
```

The public-lead adapter may begin from a lead id, but every continued run must
resolve to a canonical website request. Remaining idempotency is keyed by the
website request id and brief version.

## Callable routine

`website_proof.generate.v1(source_id)` owns the production sequence. The source
id is a website request id or a public lead id that can be deterministically
resolved to one.

The routine retrieves:

- business and verified contact information;
- current website, domain, and logo status;
- industry, location, products, services, and audience;
- required pages, features, integrations, ecommerce, and booking needs;
- preferred creative intensity, colors, feelings, and styles to avoid;
- reference websites, competitors, and customer-supplied visual assets;
- AI-enrichment and research consent boundaries;
- package recommendation, private offer, or grant eligibility;
- account, organization, request, project, and order continuity.

One unattended run may prepare proofs and notifications. It may not publish a
customer proof notification, change an approved price, charge a customer,
purchase a domain, or deploy a customer release without the relevant explicit
gate.

## Intake contract: `website_discovery_v3`

The request form must collect the operational facts already covered by v2 plus
the following structured creative inputs.

### FAMtastic intensity

Question: **How FAMtastic should your website feel?**

This is a creative-intensity preference, never a quality score:

| Level | Meaning |
| --- | --- |
| 0 | Safest, most familiar, maximum restraint |
| 1-2 | Classic and subtle |
| 3-4 | Polished with visible personality |
| 5 | Balanced, professional, and distinct |
| 6-7 | Bold, expressive, and editorial |
| 8-9 | Showpiece design with strong visual ideas |
| 10 | Maximum FAMtastic: cinematic, immersive, and unforgettable |

The customer also chooses whether one of the three directions may intentionally
push beyond the selected level.

### Brand and references

Collect preferred colors, colors to avoid, desired emotional response, brand
status, style notes, reference sites, competitor sites, and reference files.
Supported reference files begin with PNG, JPEG, WebP, and PDF. Each upload must
record ownership confirmation, AI-use consent, original filename, MIME type,
size, checksum, retention state, and request ownership. A flyer or image made
with another tool is treated as reference material, not automatically as
reusable licensed artwork.

### Optional AI enrichment

Customers may opt into one of three modes:

- none: deterministic normalization only;
- FAMtastic-managed: approved FAMtastic model routing enriches the brief;
- customer-managed: the customer connects an approved provider later through a
  secrets-safe integration; API keys are never accepted in intake free text.

The raw intake remains authoritative. Enrichment is a versioned derivative with
provider, model, prompt hash, input hash, output hash, duration, cost, fallback,
and reviewer status. Life-path guidance is a separate opt-in and may affect
voice or creative suggestions only. It must never affect price, eligibility,
contracts, accessibility, risk, or approval.

## Strict three-direction contract

Every proof run returns exactly three genuinely different directions:

- **Safe:** polished, familiar, credible, and low-risk;
- **Wild:** expressive, energetic, and clearly differentiated;
- **OMG:** the strongest campaign-level visual idea.

Each direction includes:

- a working responsive page;
- real generated artwork or properly licensed imagery;
- mobile and desktop layouts;
- navigation and functional calls to action;
- customer-specific copy and real business information;
- a concise visual rationale;
- desktop and mobile screenshot evidence;
- a stable selectable proof identifier.

Changing only colors, fonts, or hero copy on one template fails this contract.
The deterministic no-image renderer is not a customer-deliverable proof engine.
When a qualified creative provider is unavailable, the run must wait in a
visible exception state rather than generate low-quality substitutes.

## Engine and agent boundaries

The deterministic workflow owns facts and safety:

- intake validation and versioning;
- ownership and access control;
- required fields and consent;
- pricing, add-on, grant, and renewal rules;
- job idempotency, retries, and exception routing;
- file structure and retention;
- proof publication and notifications;
- account, request, project, and order association;
- approval, payment, deployment, and audit boundaries.

Creative agents may own:

- research synthesis and citations;
- positioning and customer-specific copy ideas;
- art direction and image prompts;
- layout concepts and creative critique.

A model may recommend a product or add-on, but it cannot invent a price, apply a
grant, change renewal terms, or silently add an item to checkout.

## Structured specialist output

Every specialist result validates against a versioned schema. A representative
creative-director result is:

```json
{
  "agent": "creative_director",
  "routine_version": "1.0.0",
  "website_request_id": "request-uuid",
  "direction": "omg",
  "concept_name": "Crown in Motion",
  "visual_strategy": "customer-specific strategy",
  "image_prompts": [],
  "required_sections": [],
  "claims_requiring_review": [],
  "confidence": 0.87
}
```

Invalid output retries through the declared provider policy or enters a visible
exception state. It never silently becomes a customer artifact.

## Model routing and ledger

Capabilities are routed by job, not hard-wired to one provider. Local models
may handle classification, drafts, and inexpensive critique. Approved premium
models may handle benchmark or escalation work. Dedicated visual providers
create artwork. Browser automation proves functional behavior. Humans approve
customer delivery, prices, contracts, and publication.

Every run records:

- agent, capability, provider, and model;
- prompt, input, output, and artifact hashes;
- start, duration, retry, and result;
- fallback and exception details;
- available cost data;
- reviewer decision and timestamps.

## Owner review and customer-send gate

Completed proofs first enter `owner_review`. They are visible to authorized
FAMtastic administrators and hidden from the customer workspace. The admin view
shows the exact request, intake version, three variants, thumbnails, working
previews, generation ledger, automated QA result, and notification preview.

Only an explicit, CSRF-protected **Approve and queue customer email** action may
transition a proof set to `customer_ready`. That action records the acting user
and timestamp, reveals the proof set in the owned customer account, and queues
one idempotent transactional notification. Marketing campaign approval is not
used for account-owned project notifications.

Customer selection and revision actions are account-owned and authorization-
checked. Public prospect tokens remain restricted to the public-lead journey.

## Grant-code classes and zero-dollar orders

Grant codes are private, hashed, scoped records—not public coupons. Supported
classes are:

| Class | Intended use |
| --- | --- |
| `OWNER_COMP` | Owner-only internal/customer work, restricted to an approved owner account |
| `CUSTOMER_GRANT` | One named customer, request, and SKU, including a fully sponsored initial purchase |
| `PERCENT_PROMO` | Scoped percentage reduction with explicit limits |
| `FIXED_PROMO` | Scoped fixed reduction with explicit limits |
| `SERVICE_CREDIT` | Earned credit applied to approved services |
| `PARTNER_GRANT` | Contracted partner benefit with account and SKU scope |
| `TEST_ONLY` | Non-production acceptance only; rejected in production by default |

Every code stores a one-way hash, non-secret prefix, class, customer,
organization, request, SKU, discount rule, maximum uses, expiration, status,
initial-term treatment, creator, redemption order, and timestamps.

A zero-dollar initial purchase creates a real Drupal Commerce order and $0
receipt, records the grant redemption, marks the order completed without
opening Stripe, and invokes the same idempotent fulfillment as a paid order.
Grant codes never waive hosting renewal. Ongoing hosting sponsorship requires a
separately approved service contract with its own term and cancellation rules;
the grant covers only the initial website package and included first year.

## Notification reliability

Registration, website-request submission, proof-ready review, purchases,
support, and worker failures must be observable in one Drupal operations area.
Notifications use an idempotent outbox with retries and dead letters.

The production scheduler must invoke lifecycle processing independently of
mailbox ingestion. One failed mailbox command may not prevent Drupal cron,
notification dispatch, proof jobs, or worker health updates. Worker heartbeats
become stale after two missed intervals; the operations UI must label stale
workers as unhealthy and show queue age, attempts, and errors.

The first-response owner alert is operational and immediate. Customer proof
emails are transactional and require the owner-review gate. No customer proof
notification is considered sent until the outbox records provider acceptance.

## Golden benchmark and quality gate

The sanitized Shay locs request is the first golden benchmark fixture. A clean
run must repeatedly produce:

- one request and one deterministic package recommendation;
- exactly three distinct working demos;
- six screenshots: desktop and mobile for Safe, Wild, and OMG;
- no broken links or horizontal mobile overflow;
- required calls to action and research citations;
- request, account, project, proof, and order continuity;
- a prepared customer notification;
- a complete agent ledger;
- a safe stop before customer email and payment.

Automated checks prove loading, responsiveness, navigation, CTAs,
accessibility basics, and artifact integrity. A human reviewer scores each
direction on first impression, business relevance, visual distinction, copy
specificity, trust, mobile usability, accessibility, conversion clarity,
emotional response, and confidence in the next action.

Release rule:

- no subjective dimension below 7/10;
- overall score at least 8/10;
- all three directions sufficiently distinct;
- no unresolved critical defect;
- explicit human approval for customer delivery.

## Evidence package

Every run preserves:

```text
proof-runs/{website-request-id}/{run-id}/
├── intake-redacted.json
├── asset-manifest.json
├── research.json
├── approved-brief.json
├── agent-ledger.json
├── direction-safe/
├── direction-wild/
├── direction-omg/
├── screenshots/
├── quality-report.json
├── approval-record.json
├── notification-preview.html
└── run-report.md
```

Raw customer uploads follow the configured retention and deletion policy and
are not duplicated into a permanent evidence package unless required and
authorized. The manifest and hashes remain for audit.

## Evidence levels

- **Simulated:** fixture data or mocked APIs.
- **Locally proven:** real code and browser journey on the supported local stack.
- **Test-provider proven:** an external provider test mode accepted the exact operation.
- **Production smoke-tested:** an authorized, non-destructive production check passed.
- **Production proven:** real owned records, hosted proofs, controlled email,
  account continuation, monitoring, and an observed safe payment boundary.

The level is attached to each capability; a locally proven component cannot be
described as a production-proven journey.

## Release acceptance

The production journey is releasable only when all of the following are true:

1. A submitted registered request queues exactly one owner alert and one proof job.
2. Registration queues one owner alert without leaking passwords or tokens.
3. The dispatcher and proof worker run independently of mailbox ingestion.
4. Stale worker state and notification backlog are visible to administrators.
5. The intake persists creative intensity, colors, avoidance, references,
   uploads, and AI consent with the declared schema version.
6. Exactly three Safe/Wild/OMG proofs enter owner review and remain customer-hidden.
7. Owner approval reveals proofs and queues one account notification.
8. Cross-account reads, proof views, selections, uploads, and grant redemption fail.
9. A scoped free grant completes one $0 Commerce order without Stripe and
   fulfills exactly once.
10. A normal paid order still uses Commerce/Stripe and the same fulfillment boundary.
11. The canonical customer-journey runner exits zero, emits all required PASS
    markers, and records only true assertions.
12. The exact clean Git SHA is deployed and production evidence is recorded.

## Current incident and corrective action

On August 17, 2026, Shalique's verified portal account submitted website request
`216719ac-51fe-4c9d-9e4e-70508a47eb24`. The customer receipt and Fritz owner
alert were written to the durable outbox, but the production dispatcher had not
run after submission. The shared-host cron command coupled mailbox ingestion and
Drupal cron with `&&`, allowing the first command to suppress all downstream
work. The request also had no pre-purchase proof-job trigger or owner-review
state.

The corrective release separates scheduler commands, adds direct lifecycle
operations and health reporting, creates the pre-purchase proof job, blocks
low-quality fallback proofs, introduces owner review and explicit customer-send
approval, expands the creative intake contract, and adds auditable scoped
grant-code fulfillment.
