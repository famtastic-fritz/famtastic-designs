# Existing worker authentication: scoped principals v1

Source integration only. This reuses the installed worker registry, existing HMAC
format and Drupal nonce table. It does not add a credential scheme, enable workers,
register the managed reader/release, or establish independent QA quality.

## Identity comes from the authenticated request

`WorkerRequestAuthenticator::authenticate(Request, routedOperation)` requires the
existing default-off worker switch, TLS, installed operation capability, exact
canonical `/api/pipeline/worker/<operation>` HMAC, bounded body and current timestamp.
It calls the real coordinator's unique nonce insert before returning an empty
opaque principal held in its instance-private WeakMap. No worker/body identity,
UID, claimed approval or caller-made object can create one.

`facts(principal)` returns the verified worker/automation identity, operation,
installed claim-capability intersection, exact signed body and its digest. The
controller uses this immutable snapshot instead of reading mutable HTTP input
again. Returned arrays are by value; mutating one cannot change the binding.
**Never serialize/log these facts as an HTTP response:** operation input can
contain lease credentials or private business content. Signing/registry secrets
are not returned.

Every resolution checks the current enabled switch, registry secret/capability
fingerprint and original signature expiry. Observed revocation or expiry destroys
the principal; restoring configuration does not resurrect it. Clones, deserialized
objects and principals from another authenticator instance are rejected. A registry
capability grants only the corresponding operation, not a paid tool, tenant, import,
current receipt, asset right or final release.

`reviewer(principal, exactRequestId)` resolves only a signed `review` operation
with a matching strictly integer positive request_id. It returns the canonical
`automation:<worker>` identity. A legacy controller cast must not convert a signed
string into managed-review authority. The reader independently excludes every
actual producer in the import; the release checks current account/asset rights,
policy and retained evidence. Those checks are not duplicated or replaced here.

The reader and historical-release authenticator must use this same scoped service
instance. Durable jobs store receipts, not opaque request principals; a later
request authenticates again with a fresh nonce and current credentials.

Review credentials must not equal another configured worker's signing secret.
Distinct names are not independent authority if a producer can sign as the
reviewer. This check runs before nonce consumption and on subsequent resolution,
including newly added aliases. Existing claim behavior is unchanged; separate
credentials still do not prove an actual independent QA run.

## Nonce persistence and retry

`WorkerCoordinator::rememberNonce` now requires a committed primary connection,
not a caller transaction or replica. It rejects an existing nonce, requires exactly
one affected row from the unique INSERT's actual prepared statement, and verifies
the exact expiry remains stored after cleanup. A matching row alone cannot prove
which concurrent caller inserted it. The successful insert commits independently
before any operation; later failure cannot roll it back to permit replay.
Expired-row cleanup remains separate; an uncertain cleanup/response is not reason
to delete or reuse a possibly committed nonce. Ignored, altered or deleted writes
withhold a principal, even when the SQL call did not throw.

The identical signed request is rejected on replay, including after a successful
release. A newly signed request with a fresh nonce may reconcile the same immutable
release through its existing historical acknowledgment; it cannot create another
notice or restore visibility. Authenticated malformed JSON consumes its nonce and
now receives the generic authentication-rejected response; no input is echoed.

## Installation boundary

Service configuration connects this adapter to the existing controller/coordinator
and clock. The worker enable switch remains unchanged/default off; no registry
secret is added. Existing static claims, capability narrowing, lease/attempt checks,
pilot lock, independent reviewer attribution and generic error responses remain.
The controller forwards the opaque principal only to the existing portal release
operation. Managed reader/release/evidence dependencies remain unregistered.

Tests and actual execution evidence are recorded separately. Signed fixture traffic
and real disposable nonce persistence are not installed TLS/session, live workers,
SMTP acceptance, real creative generation or cloud/laptop-independent proof.
Exact runs, review finding, failure history and retained source hashes:
`../evidence/WORKER-REQUEST-PRINCIPAL-2026-09-22.md`.
