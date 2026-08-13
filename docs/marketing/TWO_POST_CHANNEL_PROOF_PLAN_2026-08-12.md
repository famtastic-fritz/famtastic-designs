# Two-Post Social Channel Proof Plan

Date: 2026-08-12

## Goal

Prove a repeatable FAMtastic social publishing service by delivering and
verifying two campaign posts on each target network: Facebook, Instagram,
TikTok, YouTube, and, where API access is practical, X. Start with controlled
personal/warm-audience channels and available business channels; expand into
clean business identities without rebuilding campaign assets.

This is not satisfied by generating files or showing a scheduler preview. A
channel passes only when two posts have provider-visible URLs/IDs and the
evidence ledger records account identity, content ID, time, copy/media variant,
UTM, visibility, and rollback/removal result.

## Two publishing lanes

| Lane | Purpose | Voice | Automation rule |
|---|---|---|---|
| Founder/personal | Test with Fritz's friends, contacts, and existing audience | Personal, candid, founder-led | Use official automation only where the platform permits personal-account publishing; otherwise use an assisted manual handoff with evidence. |
| FAMtastic/business | Build the durable company audience and conversion path | Branded, educational, proof-led, direct CTA | Connect professional/Page/channel identities through least-privilege OAuth and Postiz. |

The two lanes may share one idea and media system, but captions must be adapted.
Do not blast identical text to every account.

## Acceptance matrix

| Network | First proof identity | Method | Post 1 | Post 2 | Current gate |
|---|---|---|---|---|---|
| Facebook | `FAMTastic Designs` Page | Postiz/Meta Page API | Pending | Pending | Meta app secret and Postiz OAuth configuration. Personal-profile posts remain assisted manual because Page APIs do not publish to normal profiles. |
| Instagram | Confirmed business account or a newly created clean FAMtastic account | Postiz Instagram provider | Pending | Pending | Exact business identity and professional account connection. Personal Instagram remains assisted manual if restored to Personal. |
| TikTok | Fritz's available TikTok account, then separate business identity | Postiz TikTok provider | Pending | Pending | Handle confirmation, TikTok developer client, scopes/audit; an unaudited client may be private-only. |
| YouTube | Fritz's current personal channel under `nineoo@yahoo.com` | Postiz YouTube provider | Pending | Pending | Google OAuth client and channel authorization. First API proofs may be Private until Google audit permits public uploads. |
| X | Fritz's available account, or new FAMtastic business identity | Postiz X provider | Pending | Pending | Account identity plus X developer write access; do not buy a paid tier automatically. If unavailable, record as provider-gated rather than fake automation. |

## First two content proofs

Use two distinct, already planned campaign moments:

1. **Teach:** what a small-business website must actually do. CTA points to the
   relevant FAMtastic educational page with stable campaign UTM content ID.
2. **Challenge/Invite:** "Cost is not one of them. Period." and the 55-cents-a-
   day Web Basics offer. CTA points to the canonical offer/intake path.

Each network receives its native format: 9:16 video for Shorts/Reels/TikTok,
an accessible branded image or short video for Facebook/X, burned-in captions,
safe-zone QA, platform-length copy, and descriptive alt text where supported.

## Execution stages

1. Inventory the exact personal and business identity for every provider.
2. Configure provider applications and store secrets only in the local,
   untracked Postiz environment or OS credential store.
3. Authorize one identity at a time and verify the account name returned by the
   provider before any schedule is created.
4. Run private/draft/unlisted proofs where supported. Confirm media crop,
   caption, link, UTM, visibility, and deletion.
5. Present the two-post batch for Fritz's explicit public-publishing approval.
6. Publish post 1 per channel, verify its public provider URL, and monitor for
   delivery errors before post 2.
7. Publish post 2, verify it independently, and capture early reach, clicks,
   leads, and qualitative responses.
8. Convert the evidence into a FAMtastic case study, capability-registry entry,
   operating runbook, reusable onboarding checklist, and future service offer.

## Safety gates

- `FAMTASTIC_MARKETING_PUBLISH=false` remains the default until Fritz approves
  the exact two-post public batch.
- No ad spend, paid API tier, account deletion, username change, or public post
  is inferred from this plan.
- A personal identity is never silently substituted for a business identity.
- Provider restrictions are recorded honestly: assisted manual posting is not
  described as API automation, and private provider tests are not described as
  public delivery.
- Publishing retries are bounded and idempotent; a timeout never triggers an
  unverified duplicate post.

## Definition of done

- Ten verified deliveries: two each on Facebook, Instagram, TikTok, YouTube,
  and X, unless X is explicitly classified provider-gated because write access
  requires an unapproved paid plan.
- Each delivery has a provider URL/ID, canonical content ID, account/lane,
  timestamp, visibility, media checksum, caption record, UTM, and screenshot.
- Each channel has a tested correction/removal procedure.
- Drupal Campaign Operations reports the delivery and attribution evidence.
- The repository contains the reusable onboarding, credential, QA, failure,
  and reporting doctrine needed to sell this workflow responsibly.

## Facebook Page proof — 2026-08-12

- Connected identity: `FAMTastic Designs` (`179965042038743`)
- Postiz channel connection: passed
- Post 1 provider ID: `179965042038743_1718361166119882`
- Post 2 provider ID: `179965042038743_1718361229453209`
- Meta Graph verification: both records returned `is_published: true`
- Public URLs:
  - https://www.facebook.com/1718361399453192/posts/1718361166119882
  - https://www.facebook.com/1718361399453192/posts/1718361229453209
- Operational note: Postiz's composer defaulted the proof drafts to its next
  posting slots. For this explicitly authorized immediate proof, delivery was
  completed through the same connected Page token and the returned provider
  IDs/URLs were written back to the local Postiz records. The reusable runner
  must expose an explicit `now` mode instead of rewriting scheduler state.

## Corrected Facebook creative proof — 2026-08-12

The initial Page transport proof used text-only posts. That proved API delivery
but did not satisfy campaign creative acceptance. The corrected proof used the
approved branded campaign graphics and separate business/founder copy:

- Business Page branded photo 1:
  https://www.facebook.com/1718361399453192/posts/1718375802785085
- Business Page branded photo 2:
  https://www.facebook.com/1718361399453192/posts/1718375929451739
- Meta Graph verification returned `is_published: true` and `media_type: photo`
  for both Page posts.
- Both Page posts were shared to Fitzgerald Médiné's personal timeline with
  founder-adapted copy; Facebook confirmed each share to the `FRIENDS`
  audience. This is an assisted personal-profile lane, not Page API automation.

Lesson: a provider connectivity proof is not a campaign acceptance proof. A
campaign pass requires the approved media, account-specific copy, working CTA,
public/provider evidence, and the intended personal/business distribution
lanes.
