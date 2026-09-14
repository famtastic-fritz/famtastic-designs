# Kakes By Kesline proof delivery recovery

2026-09-14, production data and SMTP acceptance verified. This is the proof rescue within the client messaging task; it required no backend code deployment.

## Correct customer and request

- Verified customer/uid **12**, Prospect **296**, recipient **kesline@kakesbykesline.com**.
- Canonical submitted request **14**, public ID `e37d8e5d-8377-4074-92dd-c55fa8ad4b9d`, has the client's five-page rebuild brief, dessert business, warm/elegant pink-black-white direction and HoneyBook mention.
- [Customer Studio Review](https://famtasticdesigns.com/portal/?section=projects&request=e37d8e5d-8377-4074-92dd-c55fa8ad4b9d).
- [Staff proof review](https://famtasticdesigns.com/web/admin/famtastic/website-request/14/proof-review).
- Campaign **53**, `pc-kakes-by-kesline-72a526d3acc005c9`; exact existing worker correlation `local-1f0df2115686176920236565676ec821`.
- Request **13**, shown in the incident screenshot, was an automatically created thin registration request. After successful delivery, the existing reversible archive API hid it from the active customer list. Request 13 and campaign 52 remain retained; no cascade or deletion occurred.

## Sent result

The three directions are **Blush & Buttercream**, **Sweet Personality**, and **The Celebration Atelier**, variants **106–108**. They are navigable responsive homepage studies for the selected five-page website direction, with clear illustrative-image labeling and a genuine email-draft CTA. No booking, payment, availability, price, launch or customer selection is simulated.

Exactly one proof-ready notice was accepted by configured production SMTP at **2026-09-14 20:27:27 UTC / 4:27:27 PM EDT**:

- Outbox **633**, key `website-request:14:proofs:53:3`.
- `sent`, attempts `1`; dispatched `1`, sent `1`, failed/retried `0`.
- Subject: `Your FAMtastic Studio Review is ready`.
- Template `customer_proof_ready/v3`.
- Message-ID: `<LdJEJmLtuyUfyyYJ23JujkiTwMGdELpGYdvZKJVnA@default>`.
- Authoritative request status is now **notified**. The exact plain-text body and provider receipt are retained in the private artifact directory below.

SMTP acceptance is proven. Recipient inbox placement and read status are unavailable. No general notification queue was drained. The production exact-dispatch pilot lock was already off; no global safety gate or other customer's authorization was changed. Fritz's explicit current instruction supplied delivery authorization. A separate Codex agent reviewed the visuals; this is not recorded as human visual approval.

## Provenance and evidence

Private local evidence: `.artifacts/kesline-proof-rescue-20260914/` in the `client-messaging-proof-rescue` worktree. Do not serve this directory or copy raw receipts/briefs into a public document root.

- `build_proofs.py`, `brief.json`, `research-snapshot.json`: exact native page source, scope and research rationale.
- `prompt-a.json` through `prompt-c.json`, `image-*/generation-receipt.json`: original **gpt-image-2** via the existing authenticated, image-only Keychain Swift worker. All three were `1536x1024`, high quality. GPT Image 2.5 was not claimed. Estimated total USD **0.54**; provider invoice cost was not returned. Each run was capped at USD 0.60.
- `build-dna.json`: immutable creative snapshot, Drupal build run **37**. `build-dna-delivery.json`, registered as Drupal build run **38**, is its lineage-linked immutable delivery continuation with top-level exact campaign/Prospect correlation and actual release receipts.
- `bundle/`: exact three-direction callback artifacts. Callback SHA-256 **`69526eefb41033fa4dce9fd55f6e9a819444951c569ef27a819b47c495dad544`**, 3,447,919 bytes. Imported through `scripts/promote-local-proof-godaddy.sh --apply`; callback was newly processed with exactly three variants.
- `browser-qa.json`, `browser-qa-final.json`, `screenshots/`, `independent-review.json`: local Chromium at 1280px and 390px, all images loaded, anchors and FAQ functional, no page errors, no clipped text or horizontal overflow, every visible interactive target at least 44px. Independent review caught and corrected the mobile long headline and rechecked the navigation CTA before release.
- `production-ready-receipt.json`: exact customer uid12 workspace had three proofs plus research; customer-scoped production controllers returned all three HTML pages and all nine requested protected images with HTTP-200-equivalent controller responses and byte hashes. This is real production service evidence, not a signed-in browser screenshot.
- `production-send-receipt.json`, `production-final-rows.txt`, `duplicate-cleanup-receipt.json`: exact send, final **notified** state and reversible duplicate archive. A real anonymous HTTP request to the protected proof URL returned **404**.

The three signed JPEG asset hashes, repeated consistently across all directions, are:

| Asset | SHA-256 |
| --- | --- |
| hero-a.jpg | `a7d00c786268b680c514fc322a25e666536dba89824d2aea57f342236c115dde` |
| hero-b.jpg | `0c5368a974b9d70edd8b532bf6f19295ef238f305ca4908760bd68d96e195918` |
| hero-c.jpg | `06501cae076c16e6c232e7cf4f1839da258e376bc3bbe3aa6886dd8eed80c345` |

Private server callback and immutable records remain under `~/.config/famtastic/proof-inbox/`, including the exact event/checksum payload and `kesline-request14-recovery-20260914/` records. No creative artifact was published as the customer's live site.

## Incident cause and follow-up

Both account proof jobs had been marked **completed** while campaigns 52/53 only recorded **waiting_callback** with `local-*` handoffs and zero variants. A local handoff created a waiting record; it had not executed a creative provider. The owner send gate was not the original blocker. The screenshot added confusion by showing duplicate request 13.

`AutomationWorker::run()` unconditionally completes any successful return, including `generateProofs()` returning `status=waiting_callback`. The portal can already derive a waiting-for-provider explanation from a local campaign, but the staff review form uses the raw `proof_review_status=not_started` heading and does not display that handoff explanation. Follow-up should surface the durable handoff state immediately and reserve proof-job completion for a validated artifact callback. A proper async job transition requires callback correlation and retry/race tests; changing the status label alone does not start an unattended creative worker.

Read-only production route inspection at **20:33:13 UTC** confirmed `SiteStudioProofClient::isRemote() === false`. A functioning local image API does not automatically supply an unattended production proof worker.

The accompanying source repair now exposes the existing read-only handoff status on the protected staff review page. An unexecuted local handoff is a visible **Proof generation needs FAMtastic attention** warning, rather than an unexplained **not started** heading. An already completed workflow job without a complete campaign or valid handoff is an attention state rather than a misleading queued state. The lookup supports legacy exact and current brief-versioned job keys, escapes literal underscores, restricts the job type, and cannot confuse request 14 with request 140. It does not change request ownership, authorization, job dispatch, callback execution, sending, or any production record by itself.

Focused behavior checks use the actual Drupal query builder against isolated in-memory SQLite rows, including overlapping request IDs, another job type/routine, legacy/current versions, completed local handoffs, missing artifacts, failed jobs, missing requests, and staff warning rendering. The proof rescue above is production-proven within its stated limits; this status repair is source/local until the parent's combined release is deployed. The remaining unattended worker and asynchronous completion gap is explicit and unresolved by this narrow repair.
