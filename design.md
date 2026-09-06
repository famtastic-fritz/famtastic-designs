# FAMtastic Experience System v1

This is the required, reusable design contract for every customer-facing
FAMtastic surface: `famtasticdesigns.com`, Client Portal, proof rooms, Site
Studio handoffs, transactional email, and industry recipes. It does not replace
the implementation-specific contracts below; it gives them one shared source
of truth.

## The customer rule

Every screen and message answers, in this order:

1. **Where am I?** Name the business/project in ordinary language.
2. **What is happening?** Use a human stage, never an internal status value.
3. **What do I need to do now?** Show one primary action, or plainly say that
   FAMtastic is working and nothing is needed.
4. **Why does this matter?** Let the customer open a short explanation only
   when they want it.

Do not put a tutorial, an operator diagnostic, technical infrastructure, a
revision allowance, a research report, or several competing actions ahead of
the answer to #3. Tooltips support clear copy; they never replace it.

## Shared visual and interaction tokens

- Canvas: `#070907`; panel: `#101310`–`#141814`; border: `#252b25`; action
  lime: `#7cfc00`; primary text: near-white.
- Inter is the interface and body face. A recipe may add a compatible display
  face only when its Build DNA records that choice.
- 44px minimum interactive targets, no horizontal page overflow, and reduced
  motion support are required.
- One glow maximum per screen, reserved for the current primary action.
- Images and motion must carry a business-specific idea, not decorate a generic
  hero. Each proof records the research decision, prompt/provider receipt, and
  component slot in Build DNA.

## Experience architecture

- **Projects landing:** a simple list of projects. Every row says whose turn it
  is and has one button: `Open project`.
- **Project detail:** four progressive destinations: `Today`, `Concepts`,
  `Research & growth plan`, and `Setup`. Files, Build DNA, sharing, and archive
  live under `More`, never in the critical path.
- **Research:** show a customer-readable "What we learned", "How it shaped the
  directions", and a labeled 30/60/90-day growth plan. Research informs a
  decision; it is not a promise of results.
- **Domain:** intake supplies a proposed website address. The customer confirms
  or edits it; no availability, registration, DNS, hosting, or payment action
  is implied until it is actually approved.
- **Clarification:** a blocking unknown becomes one plain question, a focused
  answer surface, and a versioned branded email. A nonblocking unknown is shown
  as an assumption in the research—not silently invented.

## Transactional email rule

Account-owned messages use the FAMtastic Concierge frame and a versioned
template. Each message has one job, one human headline, one graphical CTA, and
one short fallback destination. Never expose an opaque portal/proof URL as
visible body copy. Store the exact plain-text receipt and template version in
the outbox.

## Reuse and proof

Every new industry build starts with `design.md`, then records its own
`Design DNA`/component recipe before generation. Reuse the component contract,
not a painted-over site. A different industry or customer direction needs a
materially different recipe, research decision ledger, and evidence-backed
media plan.

## Required companion contracts

- Portal: `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md`
- Components: `docs/architecture/FAMTASTIC_PAGE_COMPONENT_DOCTRINE_V1.md`
- Proof generation: `docs/WEBSITE_PROOF_PRODUCTION_STANDARD_V1.md`
- Transactional email: `docs/templates/TRANSACTIONAL_EMAIL_TEMPLATE_REGISTRY_V1.md`

When these documents conflict, preserve safety and durable-record rules first,
then update this contract and the specialized contract together before a
customer-facing release.
