# Site Studio selected-build integration

**Status:** Current integration doctrine. The former document named a Site
Studio proof-dispatch/callback path. That was a historical bridge and test
fixture, not the FAMtastic preview architecture.

## Boundary

FAMtastic Designs owns preview generation, preview artifact slots, screenshots,
Build DNA, owner review, signed proof links, portal access, transactional email,
and every prospect/request/project state.

Site Studio starts only after a customer has selected one or two directions and
FAMtastic has registered an immutable
`famtastic.site-studio.build-packet.v1`. Site Studio consumes that packet as
read-only context, runs its existing build engine, and returns a signed
`site-studio.build-success.v1` packet. FAMtastic validates the return and
decides whether it changes the project or queues a notification.

Site Studio does **not** generate a preview, store a preview, serve a proof
link, email a client, create a request/project, select a direction, accept a
revision, set a price, charge a card, buy a domain, or publish a customer site.

The full source of truth is
`docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md`.

## Safe handoff

1. FAMtastic completes and retains the proof package in its own controlled
   storage: selected HTML/art/screenshots, hashes, research, review evidence,
   and `famtastic.build-dna.v1`.
2. FAMtastic records the selection on the exact owned request/project and
   creates, signs, and archives one immutable build packet.
3. A private or offline Site Studio handoff receives the packet. Site Studio
   does not need a public listener, public hostname, preview route, or
   customer-facing credential.
4. Site Studio records only its actual build stages and returns the original
   correlation IDs, build output URIs/checksums, real provider facts when
   exposed, warnings, timestamps, and a Build DNA continuation reference in a
   signed success packet.
5. FAMtastic validates the signature/correlation and then, only through its
   own owner/outbox gates, updates the project or communicates with the client.

`docs/architecture/GANDALF_FAMTASTIC_SITE_STUDIO_BRIDGE.md` specifies the
packet schema, correlations, and success-return behavior.

## Preview release rule

Deploy the FAMtastic preview path independently. Do not expose Site Studio or
configure it as a preview provider. In particular, leave the legacy preview
transport settings `SITE_STUDIO_URL` and `SITE_STUDIO_DISPATCH_SECRET` unset
for preview work. Configure and prove only the FAMtastic-controlled preview
runner described in `docs/architecture/PROOF_RUNNER_CONTRACT_V1.md`.

The explicit no-Studio-exposure checklist is in
`docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md` and
`docs/LIVE_READINESS_CHECKLIST.md`.

## Historical fixtures and migrations

`scripts/e2e-preview-runner-callback.sh` is the current FAMtastic-owned preview
transport fixture. Historical records and fixtures such as
`scripts/e2e-local-proof-promotion.sh` remain available for audit and migration
work. Neither is a production preview runbook or evidence of a live provider.

Do not make the retired proof callback compatible by weakening FAMtastic's
verification or by exposing a local workstation. A migration is complete only
when a FAMtastic-controlled preview runner produces the complete 3/6/1 proof
evidence and Site Studio receives a selected build packet afterward.
