# Client + operator portal architecture v1

**Purpose:** make the customer portal a calm, phone-first workspace for running one business, while giving the FAMtastic owner the proof, controls, and exceptions needed to deliver it safely.

This is a product architecture, not permission to activate a domain, contact route, calendar, payment, email campaign, or customer account. Each live mutation remains separately owner-approved and auditable.

## The two jobs

| Person | Portal job | The portal must make easy | The portal must never pretend |
| --- | --- | --- | --- |
| Shay / client owner | Run and grow one business | approve business facts, inspect research, choose a direction, publish/close an availability invitation, respond to a request, see the next growth decision | that a request is confirmed, a calendar is connected, a domain is registered, or a metric exists when it does not |
| Fritz / FAMtastic owner | Deliver a useful, accountable business system | see every client’s stage and blockers, verify identities/facts, approve proof and research artifacts, prepare sendable email, approve launch integrations, intervene in exceptions | that customer ownership, provider connection, delivery, or email was proven by a mock or static page |

## Shay’s workspace

Keep the first level flat and phone-friendly. Each screen has one question and one next action.

```text
My Business (/portal)
├── Today (/portal?tab=home)
│   ├── Next best action
│   ├── Project pulse and clear status
│   └── Important exception only
├── My Website (/portal?tab=projects)
│   ├── Research + opportunity snapshot
│   ├── Proof directions / selected direction
│   ├── Revision and edit requests
│   └── Launch decisions
├── Owner Desk (/portal?tab=desk)                 [after exact owner binding]
│   ├── Availability invitations
│   ├── Request inbox and response state
│   ├── Services, hours, contact, location
│   └── Approved temporary notice / portfolio controls
├── Growth Plan (/portal?tab=growth)
│   ├── 90-day priorities
│   ├── One active experiment
│   ├── Real action results after analytics activation
│   └── Recommended next FAMtastic capability
├── Files + Proof (/portal?tab=files)
│   ├── Approved images and business assets
│   ├── Research report / sources / date
│   └── Launch handoff documents
├── Messages (/portal?tab=messages)
│   └── Context-attached support and decisions
└── Account (/portal?tab=account)
    ├── Exact account/contact identity
    ├── Notification preferences
    └── Security / access
```

### Shay’s default “Today” view

Show only the business she needs to run today:

1. **Next best action** — e.g., “Confirm your public phone and hours,” never a vague progress bar.
2. **What clients can do** — website status, direct-request status, availability status, and what is still deliberately off.
3. **Pending decisions** — domain choice, exact business facts, proof selection, revision count, and launch consent.
4. **Owner Desk shortcuts** — open availability, requests, business details. No synthetic client rows or bookings.
5. **Growth move** — one recommendation connected to observed data or clearly labelled as a hypothesis.

### Research must be a first-class deliverable

The My Website screen needs an immutable, request-owned **Research + Opportunity Snapshot** beside the proof—not buried in a note or email.

- observed facts and sources/date;
- business goals and stated constraints from intake;
- 3–5 labelled opportunities/hypotheses;
- why the chosen design and customer flow follow from that research;
- 90-day growth plan with one recommended first experiment;
- owner approval/version/date, with no raw interview data or cross-account access.

This fills the current gap: the existing customer portal can show projects, proofs, files, messages, growth, analytics, and account views, but does not yet have a durable request-owned research snapshot or the Owner Desk’s exact account authorization.

## Fritz’s operator console

The owner console should be a single-operator command center, not team/billing SaaS chrome.

```text
FAMtastic Owner Console (/owner)
├── Work Queue
│   ├── New / aging client decisions
│   ├── Stalled intake, proof, revision, launch, and renewal states
│   └── One next operator action per client
├── Client 360 (/owner/clients/:request)
│   ├── Exact identity / account binding / authority check
│   ├── Research snapshot + Build DNA + proof artifacts
│   ├── Customer-visible status and email receipt history
│   └── Domain, provider, launch, and payment approvals
├── Proof Review
│   ├── Direction quality / mobile QA / accessibility / asset provenance
│   ├── Customer selection and edit counters
│   └── Explicit approve, return, or rebuild decision
├── Delivery Control
│   ├── Domain, hosting, SSL, email, analytics, request route, calendar state
│   ├── Provider evidence and rollback notes
│   └── No action enabled without its named authority
├── Client Health
│   ├── Real requests / response time / conversion signals after activation
│   ├── Growth experiment progress
│   └── Recommended next capability and owner-reviewed outreach draft
└── Exceptions
    ├── identity mismatch / access denial
    ├── failed worker, email, build, or provider action
    ├── stale proof or expiring temporary review
    └── explicit recovery action + audit history
```

## The important wiring

```text
Completed intake
  → immutable Research + Opportunity Snapshot
  → Build DNA-bound proof set
  → Fritz approves customer-visible package
  → Shay’s account-owned My Website review
  → selection / revision / launch decisions
  → exact owner binding
  → phone Owner Desk (availability + requests)
  → separately approved calendar / email / analytics integrations
  → measured growth recommendation
```

Existing portal capability is a useful shell: `/portal` already has home, projects, services, files, analytics, messages, growth, billing, account, and settings; its project path already guards customer-account proof access. The missing pieces are not another dashboard: immutable research delivery, a smaller Owner Desk, exact client-owner authorization for availability/request records, and an operator Client 360 that combines delivery evidence with the customer-visible state.

## Delivery sequence

1. Add the immutable research snapshot and its exact customer/owner read paths.
2. Add a `desk` portal section only after the account-email discrepancy is resolved and the availability/request APIs enforce exact client ownership—not merely staff permission.
3. Replace generic portal status tiles with the Today / decision / exception model.
4. Add Client 360 to the owner console, beginning with identity, research, proof, email receipt, and launch dependencies.
5. Enable real measurements only after the public site, analytics, and the particular action being measured are approved and working.
