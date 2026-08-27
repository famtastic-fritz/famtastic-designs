# FAMtastic page and component doctrine v1

## Decision

Every FAMtastic website is a system that can grow, not a finished screenshot or
a permanent one-page file. Model it as:

`site -> page recipe -> component instance -> versioned component -> parts and bindings`

A one-page build is the first useful recipe for a small site. It is not the
final architecture. The same selected visual direction must be able to become a
larger one-page experience or a multi-page site without a quality drop,
customer re-entry, or a ground-up rebuild.

## Canonical model

- A **site** owns the visual system, business bindings, routes, and one or more
  page recipes.
- A **page recipe** is an ordered list of component instances assigned to page
  regions. It owns order and allowed visibility, not the component's content.
- A **component instance** is one use of a versioned component. Its stable ID,
  field values, media, repeaters, actions, variant, and Build DNA persist when
  it moves between recipes.
- A **versioned component** is a reusable mini-template with a typed contract.
  It may expose fields, media or nested-content slots, repeaters, actions,
  visibility rules, optional motion, and named parts.
- A **part** is the smallest named compositional unit that needs independent
  styling or binding, such as a copy lockup, media frame, service card, status
  chip, or action pair. A part is not automatically a standalone component.

Stable identity comes from structured source. Never infer page, instance,
component, field, slot, or part identity from DOM position, CSS selectors,
visible copy, or an asset filename.

## Composition and variation rules

1. A field change edits content; it does not create a new component.
2. A media change updates a declared slot binding; it does not create a new
   template or art direction.
3. A component variant may change composition while keeping a compatible typed
   field/action contract.
4. A page variant changes recipe order, visibility, or component variants only
   through an allowlisted rule.
5. A new template must contain a materially different recipe or compositional
   system. Renaming copy or replacing one image is not a new template.
6. Hide, reorder, and page-split behavior must preserve required navigation,
   accessibility, business actions, and route integrity.
7. Premium image positions follow the repository's three-candidate, selection,
   and finishing contract. Ordinary owner-supplied or reusable slot swaps do
   not become premium work merely because the slot is replaceable.

## Starter foundation

A low-cost one-page recipe may be deliberately lean, but it should retain the
ordinary website foundation relevant to the business: brand and navigation,
positioning, services or offer, about/story, work/gallery when available,
contact, location/map/hours when applicable, policies/FAQ, and the primary
conversion action. Booking, payment QR, reviews, portals, or other operational
components are additive capabilities; they must not silently replace the
foundation.

Planned components must be labeled planned. A design or registry entry is not
proof that the component, backend, provider, or production route exists.

## Upgrade continuity

When a customer upgrades, preserve the selected theme and move the existing
component instances into new page recipes. Retain stable IDs, content, media,
actions, visual tokens, accessibility decisions, and Build DNA lineage. Add or
upgrade only the components and routes required by the new scope.

For example, a one-page Home recipe can become Home + Services + Contact by
moving the existing services and contact instances to their own recipes while
keeping their bindings and visual direction. The upgrade must not look like a
cheaper or unrelated site simply because its architecture expanded.

## Build DNA

Componentized work extends the existing `famtastic.build-dna.v1` ledger; it
never creates a parallel design-history system. Record or reference:

- `site_id`, page recipe ID/version, and component-system contract/version;
- each stable `page_id`, component instance ID, and component definition ID;
- selected component variant, relevant field/slot/action binding hashes, and
  permitted visibility/order change;
- input, output, media, QA, review, and artifact hashes at the stage that
  produced them; and
- the exact selected recipe and binding snapshot handed to Site Studio.

An image-only experiment must name the one permitted slot change and prove the
other normalized page/component output remained frozen. A component-variant or
multi-page experiment needs its own explicit acceptance contract.

## FAMtastic and Site Studio boundary

FAMtastic owns customer truth, approved proof direction, business data,
communication, offer/payment authority, public delivery, and the immutable
handoff packet. Site Studio is the workhorse that consumes the selected page
recipes and bindings, performs the build, journals its real stages, and returns
artifact hashes and a Build DNA continuation.

Site Studio must not infer a different recipe from rendered HTML, silently
replace the selected visual system, rerun research because a receipt is
present, activate a provider, send a message, charge, or publish. Component and
page-recipe data in a packet is immutable build context unless a separately
authorized change request says otherwise.

## Current evidence and limits

`docs/architecture/BOOKED_BRANDED_COMPONENT_SYSTEM_V1.md` is the first local
proof of this doctrine. It demonstrates one page recipe, nine stable component
instances, and four pages whose only permitted change is one hero-media slot.
The machine contract proves the normalized output and component signatures are
otherwise identical.

That evidence is local. It does not yet prove arbitrary component swapping,
hide/reorder controls, one-page-to-multi-page conversion, Site Studio import,
customer delivery, or production execution. Each is a separate evidence gate.
