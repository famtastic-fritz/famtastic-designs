# Owner-attested offline invoice payment — controlled pilot

**Date:** October 3, 2026  
**Session classification:** `deployed`  
**Agency source/runtime:** `bb91a07b6c5e2c332b70881df2937f0400bdeb5c`

This shared record is deliberately redacted. Customer identity, invoice/order
identifiers, amount, receipt hash and private server path remain in the owning
customer and Commerce records outside Git.

## Result

FAMtastic can record an owner-confirmed payment received outside checkout without
inventing Stripe or bank-provider evidence. The exact operation uses a disabled
manual Commerce gateway, a completed manual payment, an immutable invoice
snapshot, one invoice event and one operational-ledger event. It distinguishes
the owner-recorded timestamp from bank settlement, requires exact tenant and
amount matching, and is replay safe.

Production proof included a rollback-only run, a single apply, an exact replay,
and read-only reconciliation. The paid invoice unlocked only the hosting handoff.
No customer notification, fulfillment record, DNS change or launch authorization
was created. The exact sanitized receipt and deployment rollback paths are held
in the customer's private activation package.

## Capability evidence matrix

| capability_id | Customer job and role | Entry/action/input | Saved result | Visibility and reversal | Evidence | Provider/third-party state | Owner acceptance | Consumption | Gap/next owner |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `invoice.owner-attested-offline-payment.v1` | Authorized FAMtastic staff records a customer payment received outside checkout without inventing provider proof. | Private exact-invoice CLI operation; there is no customer-facing manual-payment control. | Immutable invoice snapshot remains unchanged; one draft Commerce order, one completed manual payment, one invoice event and one operational event. | Account owner sees the paid invoice and unlocked handoff. Correction/refund requires a new audited staff reconciliation; history is not rewritten. | `hosted_verified`, 2026-10-03, runtime `bb91a07b`; rollback-only run, apply, replay and sanitized private receipt. | `unconfigured` for automated bank/Zelle verification; owner attestation only. | `not_requested`; payment reporting is not site or fulfillment acceptance. | `source_captured`; first controlled pilot only. | Finance owner retains supporting bank evidence. Engineering still needs a governed offline reversal/refund operation and a second unrelated lifecycle before generalizing this rail. |
| `owner-hosting.payment-gated-handoff.v1` | Customer owner supplies temporary least-privilege hosting and domain-path information after payment. | Authenticated portal handoff checklist; secrets stay in an approved private transfer route. | Handoff advances from payment required to awaiting owner access. | Customer owner and authorized staff see the status; owner may revoke access. | `hosted_verified`, 2026-10-03, runtime `bb91a07b`. | `unconfigured`; customer hosting remains a separate provider audit. | `pending`; customer-specific task retained in the owning record. | `source_captured`; first controlled pilot only. | Customer provides access; FAMtastic audits, installs, tests and trains before a separately approved launch. |

## Reusable owner-task pattern

| task_id / capability_id | Intended owner and route | Preconditions and visible steps | Expected saved/public result | Reversal | Release | Evidence and observation | Blocker |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `owner-hosting.provide-access` / `owner-hosting.payment-gated-handoff.v1` | Customer owner, authenticated portal | Open the handoff checklist, identify provider/control panel and domain path, then use the approved private invitation route. | Hosting audit can begin without secrets entering ordinary portal records. | Revoke the temporary invitation; FAMtastic records the stop. | Agency `bb91a07b` | `pending`; no uncoached customer observation recorded here. | Customer-specific access remains in the private owning record. |
| `owner-hosting.final-launch` / `owner-hosting.payment-gated-handoff.v1` | Customer owner with FAMtastic operator support | Review hosting audit, private installation, recovery, provider and owner-training evidence; approve the exact cutover. | Production launch is separately recorded and reversible. | Restore the prior DNS/runtime according to the private cutover receipt. | Future customer production release | `pending`; payment does not count as launch acceptance. | Audit, installation, recovery, training and explicit cutover approval remain. |

## Boundaries

An owner-confirmed offline payment is a bookkeeping and workflow transition. It
does not prove bank/provider settlement, portal terms acceptance, delivery,
customer receipt, intellectual-property transfer, hosting access, owner training,
DNS authority, production launch or the customer's own commerce capability.
