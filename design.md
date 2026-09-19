# FAMtastic Experience System v1

Creator credit is mandatory by default for every authored surface, regardless of
tier. Preserve original footer text and customer visual world; append the exact
owner PNG in a centered final row. The September 18 contract is
[CREATOR-CREDIT-2026-09-18.md](docs/design/CREATOR-CREDIT-2026-09-18.md).
Only explicit owner override creates an exception. Source implementation and
production proof are recorded separately; historical approvals remain historical.

## Preserve before enhancing — visual authority

[FAMTASTIC-DESIGN-SYSTEM.md](docs/design/FAMTASTIC-DESIGN-SYSTEM.md) is the canonical
visual specification for agency-owned surfaces. This root design.md remains the
experience/workflow contract; neither overrides customer-approved project worlds.
Apply identity to existing architecture, never infer permission to redesign it.
FAM 0–3 intensity decreases with operational density. Lime is action/state, not
the whole identity. Source artwork is immutable; crown derivatives share its
silhouette. Email is an existing consumer, not a system to rebuild.
Machine-readable visual DNA: `docs/design/famtastic-site-dna.v1.json`.
The September 17 visual-DNA baseline is released; see its deployment receipt.
The content-page system was subsequently owner-approved and released at `71620bf1`.
See [the content release receipt](docs/design/CONTENT-EXPERIENCE-RELEASE-2026-09-17.md)
for live evidence and the separate GitHub Actions billing limitation.

## Owned content pages — shared DNA, distinct recipes

Follow [FAMTASTIC-CONTENT-EXPERIENCE.md](docs/design/FAMTASTIC-CONTENT-EXPERIENCE.md).
Content pages may share primitives, but must not collapse into one template.
Packages are commercial, Solutions engineered, About editorial/personal, Contact
inviting, Work cinematic and Admin operational. Blogs retain a separate direction.
Use the named materials, native heading hierarchy, six-word cursive limit,
safe CTA variants and exact route enrollment; never apply a global page restyle.
Keep all offer facts and existing workflow/approval/payment boundaries intact.
First proof `/packages/199-quick-start` was owner-approved September 17, authorizing
the explicit Packages → Solutions/Services → About → Contact propagation. The
expanded preview is documented in `docs/design/CONTENT-EXPERIENCE-ROLLOUT-2026-09-17.md`;
the later explicit release approval and production proof are in the receipt above.
Do not extend Web Basics billing terms to other packages or interpret brand approval
as a deployment instruction. Contact success decoration requires `ok`, a positive
integer `request_id` and a saved status; a mail draft is not a saved request or send.

Owner rule, September14,2026: every new customer build must include a site-specific `design.md` before implementation. Reference this shared contract, the approved customer direction, responsive/type rules, component boundaries and acceptance evidence. A file added during maintenance must disclose that timing rather than imply it governed the original generation.

For expressive customer proofs, texture, surface geometry and lighting are separate
scoped layers, not permission to replace working architecture or spread one customer's
palette globally. Keep decoration noninteractive, preserve44px targets and semantic
controls, limit motion, and test text/focus contrast against the actual lit surface.
See the [StockandShip material-layer contract](docs/plans/CLIENT-DELIVERY-2026-09-18.md#reusable-material-layer-contract).

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

### Cursive headings — owner-approved September 17, 2026

Use cursive selectively for short public H1/H2 headings or one emphasized phrase
of at most six words inside a longer heading. Keep surrounding text in the display
sans; never apply a global cursive rule to all H1/H2 elements. Preserve the actual
wording, heading level, selectable text and screen-reader reading order. Use the
opt-in `SignatureHeading` component and self-hosted Kaushan Script (SIL OFL), with
`font-display: swap`, readable fallback and normal wrapping on narrow screens.
Do not imitate, regenerate or replace the approved logo's own lettering.

Client portal: reserve script for optional welcome/celebration accents, not project
status or instructions. Admin operational headings remain interface/display sans.
Never use script for prices, billing, forms, buttons, navigation, tables, legal
copy or critical system messages. Check 320px mobile, font-loaded and font-blocked
layouts. Customer project worlds and email typography retain their own contracts.

Primary agency logo: September 17 original PNG, governed by
`docs/brand/famtastic-designs-logo.md`. Use the shared React BrandLogo for migrated
website placements. RGB/gold logo colors do not replace existing action/surface tokens.

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

September 18 correction: customer proof actions always enter `/portal`, not
Drupal `/web/admin` or protected `/web/api` document URLs. Render named HTML
buttons; raw URLs belong only in the plain-text alternative. New personal proof
releases use the existing `customer_proof_ready/v4` adapter with one request-bound
portal URL, preserving personal copy and Shay's signature. Preserve the exact
destination through login and put Concepts in view. Never add bearer credentials
or bypass account ownership to make a link convenient. The generic standard
renderer also presents valid links as named buttons, not visible raw URLs.

New staging-review email direction and local-preview proof:
[`docs/design/email-brand-system.md`](docs/design/email-brand-system.md).
The September 18 owner request extends this approved shell to every active agency
notification via BrandedEmail. Preserve per-template content and trusted actions.

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
# Admin error-state continuity (2026-09-18)

The admin visual system also covers core 403/404 responses for original `/admin`
and `/admin/…` requests. Use the shared AdminErrorContext predicate for theme
selection and shell hooks. Preserve access checks and HTTP status; never apply
this exception to public/customer error pages or infer authority from a URL.
Legacy `/admin/user` bookmarks resolve to the permission-protected People route.

## Public footer extension — September 19, 2026

The existing footer's compact social family is governed by
[the scoped social-footer specification](docs/design/FAMTASTIC-SOCIAL-FOOTER.md).
Keep live service/package navigation, canonical logo and current closing CTA.
Local implementation/review evidence is separate from production release approval.

## Social footer production receipt — 2026-09-19

Owner-approved footer `49ce5033` is live and verified on apex/www. See
`docs/design/social-footer/RELEASE-2026-09-19.md` for exact source, backup and
live desktop/mobile evidence. Historical local review records stay immutable.
