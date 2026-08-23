# FAMtastic preview-delivery operating routine v1

**Status:** shared operating rule for Codex, Claude Code, Shay, scripts, and
future agents. It records the ownership and current compatibility route so a
new session does not mistake Site Studio for the preview host or email sender.
It does not claim that every configurable package profile is already deployed.

## One-line rule

**FAMtastic Designs owns every preview and its delivery. Site Studio receives
only a selected immutable build packet and returns a later build-success
packet.**

FAMtastic owns the run, provider route, Build DNA, proof campaign and variants,
artifact slots, screenshots, share/portal access, owner review, transactional
outbox/email, and prospect/request/project state. A Site Studio URL, local
file, offline workstation, or legacy transport name is never a preview room or
customer-delivery authority.

Read this with:

- `AGENTS.md`
- `docs/AGENT_OPERATING_CONTRACT.md`
- `docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md`
- `docs/architecture/PROOF_RUNNER_CONTRACT_V1.md`
- `docs/architecture/PUBLIC_PREVIEW_DELIVERY_V1.md` for a public lead

## Proof packages are configured, then frozen

Package count is a product and delivery decision, not a global constant or a
side effect of whether someone happened to sign in. Before dispatch, FAMtastic
must select a named, versioned proof-package profile that declares its allowed
audience, exact direction contract, mix, labels, access surface, owner-review
rule, email behavior, and build/provider route. The exact contract becomes
immutable for that run and is recorded in Build DNA.

| Default package | Audience and entry point | Default direction contract | Delivery boundary |
| --- | --- | --- | --- |
| `public_initial` | Unregistered public lead | Exactly 3: Safe, Medium FAMtastic, Ultra FAMtastic | Owner-approved, unlisted/view-only room and one transactional invite |
| `authenticated_refined` | Verified same-email customer with detailed portal intake | Up to 6: default mix is 1 Normal, 1 Medium FAMtastic, 4 Ultra FAMtastic | Fresh account-owned campaign, owner review, portal access |
| `selected_revision` | Customer has selected a direction and submits a durable change request | Exactly 1 selected direction version | Owner-reviewed replacement; preserve the baseline |

The defaults are intentionally useful starting points, not a permission to
hard-code `3`, `6`, or a particular mix in UI copy, email copy, or a fallback
worker. A future catalog may offer a different approved count or mix; it must
select a supported profile before any job is created. If the requested package
is not supported by the installed runner and verifier, fail visibly or route to
a supported profile—never silently pad, truncate, append, or reuse a different
customer's proof set.

**Current implementation boundary:** the deployed legacy-compatible promotion
adapter accepts its declared `a/b/c` three-direction contract only. Existing
canonical runner profiles encode the current defaults. A general package
catalog is not production-certified merely because this operating rule exists;
agents must not promise an arbitrary count until its configuration, validation,
and acceptance coverage ship together.

## Canonical preview path

```text
intake + consent + named proof-package profile
  → FAMtastic provider preflight and Build DNA creation
  → FAMtastic controlled preview generation
  → exact HTML/art/screenshots/hashes/QA/independent review in FAMtastic slots
  → FAMtastic owner review
  → FAMtastic signed concept room or authenticated portal preview
  → FAMtastic owner-approved transactional outbox/email
  → selection/revision (still FAMtastic-owned)
  → selected immutable FAMtastic build packet
  → private Site Studio build
  → signed build-success packet returned to FAMtastic
```

`website_proof.generate.v1` remains the only supported creative-preview routine.
It is not a direct-SMTP action, a payment action, or a Site Studio call. The
authoritative release gates are Build DNA, browser QA, independent review,
artifact ownership, named owner approval, and the FAMtastic outbox receipt.

## Existing production-compatible promotion route

The currently proven compatibility route for a legacy three-direction promotion
is FAMtastic-owned even though older script text calls the private bundle an
"offline Site Studio handoff":

```text
FAMtastic production prospect/campaign
  → scripts/fetch-local-proof-job-godaddy.sh PROSPECT_ID OUTPUT_DIR --apply
  → private offline SSH bundle with the FAMtastic brief and exact campaign IDs
  → controlled local proof build that satisfies the declared a/b/c contract
  → scripts/promote-local-proof-godaddy.sh BUNDLE --apply
  → FAMtastic Drupal proof-local import and FAMtastic-controlled artifact slots
  → FAMtastic owner review / transactional outbox / recorded email attempt
```

This route is a compatibility adapter, not a generic autonomous runner and not
a Site Studio preview dependency. It must preserve the exact campaign/job/event
IDs, validate static artifacts before import, and remain behind explicit
`--apply` and owner-send gates. It cannot be used to reinterpret a six-proof
package as three, to turn a fixture into a customer delivery, or to bypass the
FAMtastic outbox.

## Session-start and recovery rule

At the start of any relevant session, run:

```bash
bash scripts/agent-startup-context.sh
```

Then read the linked contract for the lane being changed. The script is a
non-destructive orientation check: it reports the current branch and existing
working-tree entries, verifies the canonical documents exist, and prints no
customer data or secrets. It does not fetch, build, install, invoke a provider,
email, deploy, or approve anything.

If a current task seems blocked, first identify the actual FAMtastic artifact,
campaign, outbox, and delivery route. Do not infer a blocker from a retired
Site Studio callback, an old script phrase, a generic HTTP 200, or memory from
another chat.

## Evidence and learning record

For every material run, preserve the exact profile/version, direction contract,
provider/model receipts, prompts or prompt artifacts, inputs/outputs and
hashes, timing/costs/fallbacks, screenshots, QA, reviewer decision, owner
decision, artifact locations, and outbox/delivery receipt in Build DNA and the
FAMtastic records. Add durable lessons to `docs/SITE_LEARNINGS.md`, a concise
product entry to `docs/CHANGELOG.md`, and the FAMtastic Drive decision log.

This is the operating source for cross-session orientation. It does not replace
the source-level contract or manufacture production evidence.
