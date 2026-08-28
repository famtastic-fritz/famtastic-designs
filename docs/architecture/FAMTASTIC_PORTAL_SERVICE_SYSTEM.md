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

The portal derives its modules from four durable records:

1. Commerce order: what was bought, at what terms and price.
2. Entitlement: what the customer currently owns and may access.
3. Project or service instance: the operational status and next action.
4. Intake and artifacts: the customer inputs, proofs, files, approvals, Build DNA records, and delivered outputs associated with that instance.

Marketing recommendations may use those records, but may never change them.

## Rebuilt Modular Frontend Architecture

The customer portal frontend is organized into 14 dedicated components under `frontend/src/components/portal/`, orchestrated by `CustomerPortalDashboard.jsx`:

| Module | Navigation Key | Customer Job | System Source & Logic |
|---|---|---|---|
| **Home / Command Center** | `home` | Pulse overview, Next Best Action, active order fulfillment timeline (Payment → Hosting/DNS → 3/6 Proof Concepts → Approval & Live Launch). | Orders, projects, entitlements, threads, activity feed. |
| **Projects & Requests** | `projects` | Guided 6-step brief interview (Goals, Business, Content, Brand 0-10 Creative Scale, Domains, AI Enrichment, Store), 3/6 Proof Review Room with live sandboxed iframes, direction selection, revision submission, unlisted sharing, and Build DNA provenance inspection. | `famtastic_project_request`, `famtastic_project`, proof variants, `famtastic.build-dna.v1`. |
| **My Services & Marketplace** | `services` | Owned capabilities with renewal dates, plus full studio SKU catalog (Web bundles, Hosting, AI Chatbots, Automation, SEO, Maintenance) and Specialized Intake Hub link. | Entitlements, SKU catalog, `/intake` routes. |
| **Files & Assets** | `files` | Managed organization files, uploads with ownership/AI-consent validation, downloadable deliverables, brand kits, and verified Build DNA packages. | `famtastic_customer_resource`, managed files. |
| **Growth & Analytics** | `results` | Actionable search visibility, conversion signals, Google Analytics 4 telemetry, and monthly growth recommendations (focusing on real outcomes, not vanity charts). | `customer_analytics` entitlement, performance digest. |
| **Messages** | `messages` | Contextual threads (Website issue, Project/approval, Billing/renewal), message history, reply composer, and urgent escalation hotline. | `famtastic_portal_thread`, `famtastic_portal_message`. |
| **Shay AI Advisor** | `shay` | Governed AI Solutions Advisor explaining package scope, proof reviews, change request drafting, and AI website agents. | Governed Drupal AI provider / client advisor. |
| **Support** | `support` | Structured 4-choice triage: Website/service issue, Request a change, Find an answer (FAQs), and Urgent business impact email hotline. | Support routing, thread creation. |
| **Knowledge & FAQs** | `faq` | Searchable interactive FAQ accordions with category tags. | Structured FAQ catalog. |
| **Growth Ideas** | `grow` | Contextual recommendations based on active project stage, plus "Have FAMtastic handle it" custom outcome intake. | Contextual offers engine. |
| **Referrals** | `referrals` | Client referral submissions with permission validation, reward status tracking, and 1-click SMS/email sharing. | `famtastic_referral`. |
| **Billing & Orders** | `billing` | Commerce order history, receipts, formatted currency amounts, payment status badges, Month-13 hosting renewal disclosures, and Stripe payment security notes. | `commerce_order`, payment gateways. |
| **Profile & Team** | `account` | Contact information (Name, Phone) and organization team members with role badges (Owner, Admin, Member, Billing). | `famtastic_customer`, `famtastic_membership`. |
| **Settings & Alerts** | `settings` | Essential account notifications (Project updates, Support replies, Billing notices), education/promotions opt-ins, and topic subscriptions. | Customer preferences. |

## Governed AI Workforce Boundary

Drupal AI Core, AI Dashboard, AI Agents, AI Automators, AI Logging, Key, and local/cloud providers form the internal AI platform.

### What AI May Do in the Client Portal:
- Propose, summarize, classify, draft, and explain.
- Turn raw client intakes into structured brief summaries.
- Draft suggested support replies for human review.
- Generate interactive concept variants in private preview sandboxes.
- Surface actionable SEO, citation, and conversion opportunities.

### What AI Must Never Do Autonomously:
- Autonomously send customer messages, emails, or SMS.
- Alter an account, change permissions, or grant discounts/credits.
- Mutate DNS, register domains, charge credit cards, or complete checkouts.
- Approve a creative proof or trigger production deployment.

Every commercial, financial, and deployment milestone requires human confirmation at action time.

## Build DNA Standard (`famtastic.build-dna.v1`) Integration

Every creative proof, direction refinement, and delivery run produces one immutable `famtastic.build-dna.v1` record containing:
1. Exact prompt artifacts and normalized inputs.
2. Provider, model ID, and timing/cost telemetry.
3. SHA-256 hashes of all generated assets and screenshots.
4. Independent reviewer QA evaluation and human approval gates.

Clients can inspect their project's Build DNA directly from the Project Command Center to verify model provenance and cryptographic asset integrity without exposing server secrets or API keys.
