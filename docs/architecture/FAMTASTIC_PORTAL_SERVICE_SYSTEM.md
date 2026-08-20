# FAMtastic Portal Service System

## Operating rule

The public website explains and recommends FAMtastic products. The customer
portal is the authenticated control plane for buying, onboarding, operating,
measuring, supporting, and expanding those same products. A product is not
portal-ready until its SKU contract in `backend/config/famtastic-products.json`
has a working implementation for every declared portal surface, milestone,
communication, and acceptance test.

## Shared lifecycle

Every product follows one durable lifecycle, with irrelevant stages skipped:

`discover -> recommend -> purchase -> entitlement -> intake -> work -> customer decision -> delivery -> measurement -> renewal/expansion`

The portal must derive its modules from four durable records:

1. Commerce order: what was bought, at what terms and price.
2. Entitlement: what the customer currently owns and may access.
3. Project or service instance: the operational status and next action.
4. Intake and artifacts: the customer inputs, proofs, files, approvals, and
   delivered outputs associated with that instance.

Marketing recommendations may use those records, but may never change them.

## Portal modules

| Module | Customer job | System source | Required actions |
| --- | --- | --- | --- |
| Home | Understand what needs attention | Projects, service instances, messages, billing | Continue the highest-priority task |
| My services | See and operate owned capabilities | Active entitlements joined to SKU definitions | Open intake, status, docs, support, usage, billing |
| Projects | Complete bounded delivery work | Projects, milestones, proofs, artifacts | Submit intake, review, revise, approve, download |
| Messages | Keep human help in context | Organization/project/service threads | Start and reply to contextual conversations |
| Billing | Understand charges and renewals | Commerce orders, payments, entitlements | Receipt, payment method, renewal, cancellation |
| Files | Exchange and retain project artifacts | Organization-owned managed files | Upload, download, version, approve |
| Results | See outcomes instead of vanity metrics | Analytics and service-specific telemetry | Review trends and recommended action |
| Marketplace | Discover a relevant next capability | Published SKU registry minus owned entitlements | Learn, request recommendation, purchase when eligible |
| Shay | Explain and route; never silently mutate | Drupal AI provider plus approved customer context | Answer, summarize, draft, then request confirmation |

## Product behavior

| Product family | Intake and delivery | Portal management | Expansion signal |
| --- | --- | --- | --- |
| Website bundles | Website discovery, three proofs, revisions, approval, launch | Project timeline, concepts, files, domain, hosting | Copy, brand, extra pages, SEO, analytics, agent |
| Hosting and maintenance | Domain/site binding and activation | Availability, renewal date, support, cancellation | Maintenance, analytics |
| Lead automation | Source, routing, message, escalation, test | Workflow status, test result, lead totals, support | Analytics, AI agent |
| AI website agent | Knowledge, boundaries, escalation, QA | Knowledge status, conversations, unresolved questions, usage | Analytics, maintenance |
| Analytics and SEO | Property/location connection and verification | Trends, conversions, monthly observations, work completed | Automation, content, maintenance |
| Brand and copy | Brief, concepts/draft, revision, approval | Versions, comments, approval, downloadable files | Website, pages |
| Scheduling/email/integrations | Provider access, mapping, configuration, test | Connection health, documentation, support | Automation |
| Ecommerce discovery | Catalog, checkout, fulfillment, account and integration requirements | Workshop status, requirements file, recommendation | Private implementation offer |

## Drupal AI boundary

Drupal AI Core, AI Dashboard, AI Agents, AI Automators, AI Logging, Key, and an
AI provider form the internal AI platform. They support staff assistance,
structured extraction, summaries, recommendations, and Shay. They do not
replace Commerce ownership, portal authorization, lifecycle state, or customer
confirmation.

Provider secrets must use Key with a server-side environment provider. Prompt
logging is disabled by default. Customer data may only be sent to a configured
provider for an explicit approved operation, with organization ownership and
minimum necessary context enforced before dispatch.

AI Search/RAG is deferred until a supported vector-store design, source access
policy, deletion policy, and cross-organization isolation tests are approved.
