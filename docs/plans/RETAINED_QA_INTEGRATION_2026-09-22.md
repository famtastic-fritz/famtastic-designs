# Retained independent review: next implementation slice

Status: source-inspected implementation plan, not an installed worker, evidence
store or completed review. This follows the signed-principal checkpoint and does
not introduce another Fritz approval gate. Existing policy requires real review
before automatic customer release; a saved import is not a reviewed proof set.

## Current source and verified limits

- `ManagedProofReader` supplies authenticated current receipt/package facts and
  exact private HTML/assets; producer self-review is rejected. Worker-facing
  review context/artifact transport and full service wiring remain missing.
- `ManagedProofRelease` already consumes a trusted retained-evidence verifier for
  initial release, historical acknowledgment and customer grants. Its current
  local tests use synthetic evidence verification; never install those closures.
- `ManagedProofPackageFiles` provides bounded sealed-file reads, exclusive writes
  and exact inventory checks. Storage integrity alone is not review authority.
- Studio `selected-review-qa.js` currently hashes, then discards, screenshot
  buffers. It checks static browser behavior/structure/parity at390/768/1280, not
  all nine independent managed-release judgments. Metadata-only DNA and research
  retrieval checks cannot establish those judgments.
- `WorkerRequestAuthenticator` supplies current signed identity, not a durable
  review certificate. Its90-second HTTP principal must not be persisted or used
  as a long-running lease. Existing Node static runner cannot yet sign `review`.

## Implement one coherent evidence-to-release slice

1. Retain actual desktop/mobile screenshots for A/B/C, browser reports, review
   notes and supporting rights/claim records in a separately configured private
   root. Reuse the private-file primitive, not worker paths or arbitrary URLs.
   Bound decoded bytes, image dimensions, file counts and each read; preserve
   exact source hashes and retry history. Never make screenshot uploads inline
   exceptions to the100KB signed-request boundary.
2. Bind the canonical manifest to request/customer/campaign, exact import receipt,
   package and all three HTML digests, normalized research digest, reviewer,
   every producer, policy, review attempt, tool/verifier/source revision and
   retained file inventory. Preserve all nine existing AutomatedProofPolicy
   checks; do not replace visual, functional, claims or distinctness judgments
   with three different hashes or a valid PNG header.
3. Commit acceptance through the actual authenticated review writer, then resolve
   references only from that authoritative record. A worker-supplied boolean,
   freshly calculated digest or loose evidence-reference string grants nothing.
   Retained acceptance outlives its HTTP signature, but new reads/submissions
   authenticate afresh and recheck current account/rights.
4. Wire that concrete verifier into the existing ManagedProofRelease consumer.
   Test actual retained bytes -> authenticated acceptance -> release -> customer
   read, plus unchanged input retry and lost acknowledgment. A standalone file
   validator with another permanently synthetic resolver is not completion.

## Shared review ownership, not a second queue

Use a separate review job in existing `famtastic_job`/`famtastic_worker_claim`,
keyed to exact import receipt and reviewed policy/recipe revision. Keep the
creative job completed and its claim `proof_imported`: the import receipt verifier
requires those immutable facts. The review must never regenerate its inputs.

Add an explicit review capability/profile and reviewed cost policy; `proof.review`
currently authenticates submission but does not claim work. Do not classify QA
as static dispatch or invent zero-cost provider work. Reuse the shared budget,
lease generations, bounded retries and unknown-cost holds. Persist exact payload
comparison at duplicate admission; generic enqueue's key-only dedup is not enough.

Prepare files outside transactions. On acceptance, lock request/account/user/
membership/assets, then mutex, review job/claim and import graph. Revalidate
current lease/attempt, independent reviewer and exact receipt; read back persisted
acceptance before commit. Lost acknowledgments reconcile immutable records, not
new generation, another notice or restored permissions. Renew job ownership during
long reviews and obtain fresh signed principals for each transport operation.

## Required evidence before activation

Actual retained-byte tampering/missing/extra/link tests; forged references; wrong
receipt/direction/research; producer review; current rights changes; stale attempts;
interrupted staging; changed duplicate submissions; lost write acknowledgments;
failed/unperformed checks; existing release/customer-read composition; two shared
workers and one accepted review. Retain failed evidence without calling it green.

The real independent agent/browser workflow must perform visual and scope review.
Existing static Playwright regressions do not replace that judgment; ad-hoc UI
review remains CUA-based. Interactive/commercial proofs need their actual declared
functional checks and safe preview isolation, not a static-only success claim.
Keep owner checkouts, live transports and activation flags untouched until the
controlled managed customer journey is proven and release gates are satisfied.
