# October 6 command-center capability and owner handoff

## Release identity

- Classification: `candidate` until deployment receipt and running-release checks are attached.
- Repository: FAMtastic Designs; worktree `/private/tmp/famtastic-command-center-release-20261006`.
- Source: reconciliation of current main `044c1010` and PR #49 candidate `afc8df9`; final release revision pending.
- Intended role: Fritz Medine, authenticated staff with `administer famtastic pipeline`.
- Owner guide: [October 6 task guide](OWNER_GUIDE_2026-10-06.md).
- Historical evidence: [September 30 mobile flow](MOBILE_FLOW_VERIFICATION_2026-09-30.md) and [integration evidence](INTEGRATION_EVIDENCE_2026-09-30.md). Those local tests do not establish the October 6 merged runtime or hosted behavior.
- No uncoached owner observation yet. No claim of real AI response, customer inbox delivery, Postiz recovery or Studio consumption.

## Capability evidence matrix

All rows reviewed October 6, 2026 against reconciled source; exact release revision is supplied by the release identity above. Evidence levels remain separate from deployment status.

| Stable capability ID | Customer job / role | Entry, action, input | Saved result | Visibility and reversal | Evidence | Provider state | Owner acceptance | Consumption | Gap / next owner |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `famtastic.campaign-draft-planning` | Staff plans and maintains campaigns beyond fixed 17 days | `/web/admin/famtastic/campaign/add`; name/key/brief/dates/channels/content, Save draft plan; edit/duplicate/archive/restore | Database-owned campaign plan and revision | Staff draft; edit or archive/restore; archive does not cancel provider schedules | `source_only` October 6; historical `local_tested` September 30 | `not_applicable` to planning; publishing connection independently unverified | `pending`, Fritz, task CC-CAMPAIGN-01 | `not_applicable` | Root: prove current hosted save/reload; Fritz: independent task |
| `famtastic.communication-drafts` | Staff prepares consistent customer replies | `/web/admin/famtastic/messages/{thread}`; purpose/body, Save draft, Preview and review, exact-recipient review | Database-owned staff draft/history; explicit send enters existing message/outbox lifecycle | Staff draft until send; edit/discard suggestion; a sent email is not recallable; record grouping reversible | `source_only` October 6; historical `local_tested` September 30 synthetic draft/preview/queue | `connected_unverified` for existing mail path; current release delivery unverified | `pending`, Fritz, task CC-MESSAGE-01 | `not_applicable` | Root: prove hosted draft/preview; no customer send needed for acceptance practice |
| `famtastic.staff-ai-tasks` | Staff requests reply, conversation, campaign and workload assistance | `/web/admin/famtastic/ai-assistance`; configured model, explicit enabled task and hourly limit; task buttons | AI receipt and editable suggestion; save/send remain separate | Staff suggestion; discard/edit; flags can disable later calls | `source_only` October 6; historical `simulated` provider tests | `unconfigured` in September 30 audit; October 6 state `unverified` pending inspection, no real call established | `pending`, Fritz, task CC-AI-01 | `not_applicable` | Root: inspect readiness; separate authorized model test required to claim connected use |
| `famtastic.mobile-command-home` | Staff finds work needing attention from a phone | `/web/admin/famtastic`; Home/Messages/Campaigns/More and queue actions | Reading home changes no underlying work; linked action owns its persisted result | Staff-only view; close navigation or return Home | `source_only` October 6; historical `local_tested` at 390×844 and 1440×1000 | `not_applicable` to queues; AI summary state above | `pending`, Fritz, task CC-HOME-01 | `not_applicable` | Root: current hosted phone/desktop readiness; Fritz: uncoached navigation |

## Release-matched owner-task acceptance

All tasks: intended owner Fritz Medine; staff permission above; actual owner device and observation date not yet recorded. Exact source/runtime release will be appended before closeout. Agency tests cannot mark owner acceptance as passed.

| Task / capability | Preconditions / route / device | Visible steps | Expected saved/public result and reversal | Developer/hosted evidence | Owner result / blocker |
| --- | --- | --- | --- | --- | --- |
| CC-HOME-01 / `famtastic.mobile-command-home` | Signed-in staff, own phone; `/web/admin/famtastic` | Open Home; open Messages; return; expand More and find AI assistance | Correct pages readable; no record mutation; return Home | October 6 source-only pending hosted check | `pending`; uncoached session not observed |
| CC-CAMPAIGN-01 / `famtastic.campaign-draft-planning` | Signed-in staff, own phone; new campaign route; practice name/key | Save dated draft; reload; edit one idea; duplicate; archive; restore | Separate database draft persists; no public post; archive practice plan afterward | Historical September 30 local lifecycle; current release pending | `pending`; no owner observation |
| CC-MESSAGE-01 / `famtastic.communication-drafts` | Explicit training conversation, own phone; conversation route | Choose purpose; Use purpose template; edit; Save draft; reload; Preview and review | Draft persists with correct recipient and branded preview; no send; edit draft to correct mistakes | Historical local save/reload/preview; current release pending | `pending`; training thread and owner walkthrough not yet designated |
| CC-AI-01 / `famtastic.staff-ai-tasks` | Staff, own phone; AI assistance route | Read provider/readiness; explain what default and enabled task mean; return to manual draft | Understanding check; no provider/config mutation required. Optional real generation needs configured provider and bounded authorized test | Historical simulated adapter tests only; current release pending | `pending`; no owner observation or live provider proof |

## Hosted verification and screenshots

Pending root release receipt and current browser observations. No screenshot evidence is attached yet. For each future capture record route, staff role, capture date, source/runtime revision, actual viewport and decoded image dimensions, and inspect for customer data before linking. A successful deployment or HTTP response alone does not prove the authenticated primary task.

## Bounded learning

`site_local`: Separate campaign drafting from social publishing, and draft preview from delivery. Cause: earlier fixed campaign presentation and delivery logs obscured actual owner actions. Repeat: show persisted draft state and provider readiness independently. Proof still needed: current hosted owner task and Fritz’s independent result. No shared skill promotion or Studio import is claimed.
