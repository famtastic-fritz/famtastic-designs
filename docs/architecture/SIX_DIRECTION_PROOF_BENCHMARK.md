# Six-direction proof benchmark

Status: locally proven on 2026-08-18. This is an internal creative benchmark, not proof of unattended production delivery.

## Contract

Every benchmark project produces exactly six responsive websites: one restrained direction at FAMtastic level 0-3, one medium direction at level 4-7, and four ultra directions at level 8-10. Each must have a distinct information architecture, original hero artwork, customer-specific copy, desktop/mobile screenshots, browser QA, an independent visual review, hashes, prompts, research, a model ledger, and a review room.

The acceptance floor is no visual dimension below 7/10, overall at least 8/10, no critical defect, and passing browser checks for overflow, images, alt text, links, console errors, page errors, and failed requests.

The quality gate also rejects generic bold/color/italic-only typography, flat
subject-agnostic surfaces, recolored templates, poster-only pages, copied
official symbols, unclear conversion, and inaccessible mobile behavior. Reuse
of proven components is allowed when the direction receives new research,
thesis, copy, imagery, business-native texture/symbolism, and QA.

Preview speed is controlled with one initial independent review, one
consolidated repair, and at most one post-repair review when screenshot hashes
changed. The fresh provider runner now rejects a larger repair budget; the
resume runner skips already-passing work and caps the total visual-review count
at two. Noncritical polish belongs to the selected direction.

This contract is intentionally different from the one-direction social-presence
baseline in `LEAN_SOCIAL_PRESENCE_BASELINE_2026-08-19.md`. Six directions test
customer choice and range across complete websites. The lean social baseline
tests whether one researched identity can reach the visual-quality floor in a
bounded production window. Neither evidence type should be relabeled as the
other.

## Proven benchmark

`scripts/run-st-lucie-three-project-benchmark.sh` rebuilt and verified three projects for the same customer identity using three unique request IDs:

- Bossy Nails by Pri
- The Good Ole Candy Lady Shop
- The FAMU Corner

The batch contains 18 unique HTML builds, 18 unique artwork hashes, 36 direction screenshots, three project review rooms, one combined review room, and zero failed batch assertions. It makes no email, Drupal, payment, booking, or production mutation.

This proves the local generation, packaging, browser QA, visual gate, multi-project fixture continuity, and preservation routine. It does not prove Drupal persistence, customer email, portal import, live feeds, checkout, or deployment for these three projects.

## Preservation and template policy

`website-delivery-swarm/library/archive-template-ideas.mjs` scans structured proof manifests, hashes retained files, and creates a de-identified private template-candidate catalog. Unselected work is internal-only. Selected client work is client-only. Public portfolio use always requires owner review and, for real clients, consent.

Reusable material is limited to structure and design rationale. Customer identity, intake, uploads, copy, and customer-specific artwork are not reusable. Every reused direction must receive new copy, new assets, rights review, and a new business-specific QA pass.

## Honest model boundary

The benchmark used three separate specialist agent sessions, a separate parent visual-review session, OpenAI managed image generation, and deterministic Playwright/browser verification. The ledger records those roles. It does not claim six different model providers or a fully unattended multi-provider debate. That remains a certification target.

## Production path

The portal data model already permits multiple website requests for one customer and returns them as a collection. Production automation still needs the account-owned proof job and callback described in the website-delivery swarm architecture: paid project dispatch, full intake adapter, proof persistence on the owned project, transactional proof-ready notification, authenticated selection/revision/approval, and deployment handoff.
