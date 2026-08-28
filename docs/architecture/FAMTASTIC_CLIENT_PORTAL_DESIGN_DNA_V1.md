# FAMtastic Client Portal Design DNA v1

## Purpose

The **Client Portal** is the authenticated control plane and prospect workspace for FAMtastic customers. It empowers clients to manage what they own, review and approve creative proofs, track onboarding and fulfillment, exchange contextual messages, manage subscriptions and billing, and discover their next high-leverage growth capability.

This document establishes the canonical **Client Portal Design DNA v1** standard. Every client-facing workspace surface (authenticated `/portal`, token-scoped `/portal/:token`, and prospect proof room `/p/:token`) must strictly adhere to this contract.

---

## 1. Core Operating Principles

1. **Derived from Durable Records**: Every UI view, milestone, number, and status must derive deterministically from four backend records:
   - *Commerce Order*: What was purchased, amount, currency, and payment status.
   - *Entitlement*: What capabilities the client owns and their operational status/validity.
   - *Project/Service Instance*: Delivery status, active proofs, and the explicit next human action.
   - *Intake & Artifacts*: Customer requirements, uploaded references, consent records, and delivered files.
2. **No Invented Numbers or Synthetic Hallucinations**:
   - Figures, counts, and dates come from real backend queries or explicit unknown states.
   - Test data (e.g., synthetic mailboxes, e2e test strings) is strictly prohibited from customer-visible rendering.
3. **Zero Dead Links & No External Leakage**:
   - Authenticated users must not be dumped onto public lead capture forms (e.g. `/contact`) when exploring recommended services or growth offers. Every product action routes directly into the portal purchase flow (`/buy?sku=...`) or a contextual modal.
4. **Governed AI Assistant Boundary (Shay)**:
   - Shay and AI capabilities in the portal explain, summarize, draft, and route.
   - AI may never autonomously change billing, mutate entitlements, send public messages, or deploy code without explicit customer/owner confirmation.

---

## 2. Visual Design System & Brand Tokens

| Token | Specification | Purpose |
|---|---|---|
| **Background** | `#070907` near-black charcoal | Deep, modern dark-mode canvas |
| **Panel Surface** | `#101310` to `#141814` (rgba(16,19,16,.92)) | Premium glassmorphic cards with 18–20px border radius |
| **Borders** | 1px solid `#252b25` | Subtle structure and depth separation |
| **Signature Accent** | `#7cfc00` (FAMtastic Lime) | Single accent color representing "alive / action / attention" |
| **Typography** | Inter, system-ui, sans-serif | Clean, highly legible, modern typography with uppercase letterspaced eyebrows |
| **The "One Glow" Rule** | `box-shadow: 0 0 24px rgba(124,252,0,.35)` | Maximum of ONE glowing pulse element per screen to focus user gaze |
| **Touch Targets** | Minimum 44px height/width | Strict mobile usability across all phones and handhelds |
| **Containment** | `overflow-x: clip` and fluid auto-fit grids | Zero horizontal scrolling or geometric sibling card overlap |

---

## 3. Module Specifications (`/portal`)

### 3.1 Home (`section === 'home'`)
- **Job**: Instantly answer "What needs my attention next?"
- **Elements**:
  - Top Hero: Single high-priority action card glowing with the next best action.
  - Active Fulfillment Line: Clear step progression for active orders (Provisioning → Hosting → Proof Concepts → Approval & Launch).
  - Studio Overview: Summary of active entitlements and recent verified activity.
  - Quick Service Launcher: Rapid access to owned services and contextual next steps.

### 3.2 Projects (`section === 'projects'`)
- **Job**: Convert discovery into saved briefs, review visual concepts, and track builds.
- **Elements**:
  - Request Switcher: Fluid horizontal chips for switching between multiple website requests.
  - Progressive Brief: 2-step progressive disclosure:
    - *Step 1*: Rapid 3-field draft creation.
    - *Step 2*: Comprehensive 6-fieldset deep interview (Goals, Business, Content, Brand/Style, Domains/Access, AI/Store) with sticky save bar.
  - Secure File Vault: Drag-and-drop asset uploader with mandatory copyright ownership and optional AI-assisted generation consent checkboxes.
  - Proof Review Suite: Interactive 3-up / 6-up live sandbox iframes, direction switcher, revision request modal, and unlisted share link generator.

### 3.3 Services (`section === 'services'`)
- **Job**: Operate owned capabilities and discover relevant studio modules.
- **Elements**:
  - Owned Capabilities: Active services with validity dates, status badges, and direct management shortcuts.
  - Recommended Modules: Catalog add-ons with clear pricing and direct `/buy?sku=...` in-portal checkout routes.

### 3.4 Messages & Support (`section === 'messages'`, `section === 'support'`)
- **Job**: High-touch, contextual communication with the FAMtastic team.
- **Elements**:
  - Two-Pane Conversation Hub: Thread list on left, message history and reply form on right.
  - Author Badges: Distinct styling for Customer messages vs. FAMtastic team responses.
  - Support Category Selector: Categorized triage (Website Issue, Change Request, FAQ, Urgent Impact).

### 3.5 FAQ & Knowledge (`section === 'faq'`)
- **Job**: Immediate self-service answers to operational questions.
- **Elements**: Real-time searchable FAQ accordions grouped by category.

### 3.6 Growth & Referrals (`section === 'grow'`, `section === 'referrals'`)
- **Job**: Expand digital footprint and refer fellow businesses safely.
- **Elements**:
  - Contextual Growth Cards: Specific upgrade recommendations linked to real catalog SKUs.
  - Consent-Tracked Referrals: Transparent introduction logging with privacy safeguards.

### 3.7 Billing & Orders (`section === 'billing'`)
- **Job**: Full transparency on charges, renewals, and invoices.
- **Elements**: Order breakdown cards with package names, amounts, payment status pills, and Stripe security guarantees.

### 3.8 Account & Settings (`section === 'account'`, `section === 'settings'`)
- **Job**: Profile management and granular notification control.
- **Elements**: Contact profile editor, workspace team member list, and plain-language email preference toggles.

---

## 4. Token-Scoped Prospect Portal (`/portal/:token`)

- **Job**: Unauthenticated yet token-scoped workspace for prospects reviewing their active project quote, concepts, or launch systems.
- **Security & Integrity**:
  - Preserves token context across all navigation links.
  - Displays real business metadata, project pulse, launch systems (Domain, DNS, SSL, Hosting), and direct action gates.
  - Single glow on the next best action (Purchase → Brief → Proof Review → Live Launch).

---

## 5. Automated Validation & Verification

All portal changes must pass:
1. `node scripts/validate-client-portal-design-dna.mjs` (Schema & structural compliance)
2. `npm --prefix frontend run build` (Frontend compilation)
3. `./scripts/e2e-portal-links.sh` (Headless Playwright crawl with geometric overlap and overflow checks)
