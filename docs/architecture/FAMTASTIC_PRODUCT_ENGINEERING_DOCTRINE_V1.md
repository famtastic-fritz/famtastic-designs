# FAMtastic Product Engineering Doctrine v1

**Version**: `famtastic.product-doctrine.v1`  
**Status**: Canonical Standard  
**Effective Date**: September 2, 2026  
**Audience**: Product Architects, Engineers, and Autonomous Agents  

---

## 1. The Adjective As The Architectural Standard

In the FAMtastic ecosystem, **FAMtastic is not a branding badge—it is the governing adjective and niche factor from which all product logic, engineering architecture, and commercial offers are built.**

> **FAMtastic (adj.)**:  
> *"Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose, applying mastery of craft to the point that the results are the proof, and manifesting the extraordinary from the ordinary."*

Every product, add-on, checkout flow, and client portal interaction must pass the **3-Letter Engineering Test**:

```
 ┌─────────────────────────────────────────────────────────────────────────────────────────┐
 │                               THE F-A-M PRODUCT FILTER                                  │
 ├────────────────────────────────┬────────────────────────────────────────────────────────┤
 │ Pillar                         │ Architectural & Product Test                           │
 ├────────────────────────────────┼────────────────────────────────────────────────────────┤
 │ **F — Fearless**               │ Does this feature boldly break from predatory industry │
 │ *Boldly different, on purpose* │ norms? (e.g. eliminating recurring vendor lock-in,     │
 │                                │ transparent pricing, $199 all-inclusive bundles).      │
 ├────────────────────────────────┼────────────────────────────────────────────────────────┤
 │ **A — Applying Mastery**       │ Is the value demonstrated immediately through working  │
 │ *Demonstration, not declaration│ software and proof rather than sales talk? (e.g. 48-hr  │
 │                                │ 3-direction interactive proofs: Safe, Wild, OMG).      │
 ├────────────────────────────────┼────────────────────────────────────────────────────────┤
 │ **M — Manifesting**            │ Does this transform an ordinary small business into an │
 │ *Turning common into remarkable│ extraordinary, high-converting digital asset?          │
 └────────────────────────────────┴────────────────────────────────────────────────────────┘
```

---

## 2. Product-by-Product Alignment Matrix

| Product SKU & Title | How it embodies **Fearless (F)** | How it embodies **Applying Mastery (A)** | How it embodies **Manifesting (M)** |
|---|---|---|---|
| **`FAM-FOOT-199`**<br>Web Basics Launch | **Fearlessly breaks price barriers**: $199 for full 1st year (~55¢/day), zero hidden fees, hosting and domain connection included. | **48-Hour Proof**: Delivers 3 live, clickable interactive design directions (Safe, Wild, OMG). | **Transforms side-hustles**: Turns `@gmail` & DM chaos into an authoritative, owned digital hub. |
| **`FAM-BUSINESS-499`**<br>Business Growth Bundle | **Anti-agency retainer model**: 5 conversion-ready pages with lead capture and SEO without a $5,000 retainer. | **Connected Pipeline**: Real-time Drupal lead routing, GA4 measurement, and automated client notifications. | **Multiplies enterprise value**: Gives local service contractors corporate-grade credibility. |
| **`FAM-HOST-999`**<br>Basic Managed Hosting | **Fair, honest renewal**: $9.99/mo after year one. Never auto-charges without recorded authorization. | **Zero-downtime Cloud Infrastructure**: Automated SSL, backups, DNS routing, and SLA protection. | **Reliable digital bedrock**: High-speed cloud hosting that keeps local businesses live 24/7/365. |
| **`FAM-LEAD-AUTOMATION`**<br>Lead Routing Engine | **Eliminates third-party app cuts**: 0% commission fees; owner keeps 100% of lead and transaction data. | **Instantaneous Intake**: Automated SMS/Email lead dispatch and CRM routing within seconds of customer submission. | **Captures lost revenue**: Ensures no high-intent customer falls through the cracks at 11 PM. |
| **`FAM-AI-AGENT`**<br>AI Website Assistant | **Bespoke, grounded intelligence**: Grounded exclusively in approved business knowledge, zero hallucinations. | **Autonomous Customer Triage**: Resolves routine hours, pricing, and booking questions automatically. | **24/7 Front-Desk Mastery**: Turns a solo operator's website into an always-on, high-touch concierge. |

---

## 3. Engineering Invariants for All Products

1. **No Synthetic / Fake Data**: Customer views and client portals must never render invented numbers, test mockups, or placeholder text (`docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md`).
2. **The Single Glow Rule**: Visual accents enforce the single glow halo token (`box-shadow: 0 0 24px rgba(124, 252, 0, 0.35)`).
3. **Data Sovereignty**: The customer list, lead records, and domain ownership remain 100% the property of the business owner.
4. **44px Touch Targets**: Every interactive button and form control must maintain accessible touch boundaries.
