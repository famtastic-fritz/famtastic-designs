# Social Automation Handoff

Date: 2026-08-12

## Installed foundation

- **Postiz v2.22.1** is pinned as the self-hosted scheduling and provider layer.
- **Docker + Colima + Docker Compose** provide a local-only runtime on this Mac.
- **Remotion 4.0.332** provides reusable, branded motion templates alongside the existing FFmpeg pipeline.
- The canonical 17-day manifest remains the source for 68 content moments, stable IDs, UTMs, approval state, and evidence.
- Public publishing remains disabled. Provider credentials are stored only in an untracked local environment file.
- Drupal Campaign Operations now provides the FAMtastic owner command center:
  campaign pulse, approval/action queue, all 17 campaign days, Postiz launch,
  verified delivery failures, attributed visits, leads, conversion, and sales.
  Website Analytics remains a separate workspace.

The Postiz scheduler is bound to `127.0.0.1:4007`; it is not exposed to the public internet. The upstream deployment repository is installed at `/Users/famtastic-fritz/Development/FAMtastic/tools/postiz-docker-compose`, and the source snapshot used for inspection is installed at `/Users/famtastic-fritz/Development/FAMtastic/tools/postiz-app` at tag `v2.22.1`.

The local owner is `fritz.medine@gmail.com`. Its generated password is stored in
macOS Keychain under service `FAMtastic Postiz Local`; it is not written to the
repository. Registration has been disabled after owner creation.

## Verified channel ownership and account map

The Meta inventory was inspected through Fritz's authenticated account on
2026-08-12. Do not create a second Facebook Page: the existing Page is the
canonical Facebook business presence.

| Channel | Verified or declared identity | Operating decision |
|---|---|---|
| Facebook personal | Meta developer identity uses `nineoo@yahoo.com` | Warm-audience/friends activity remains personal and assisted; never automate a personal profile through the business publisher. |
| Facebook Page | `FAMTastic Designs`, Page asset ID `179965042038743` | Existing canonical business Page; 0 followers and no recent posts at inspection. Connect this Page to the business publisher. |
| Instagram personal | `@famtstic` (Fritz's personal identity) | Keep personal and separate. It was mistakenly converted to Business during discovery and Fritz is switching it back to Personal. Never connect or auto-publish this identity. |
| Instagram business | `@famtasticdesigns`, associated with `fritz.medine@gmail.com` | Canonical FAMtastic Designs Instagram. This is the only Instagram identity authorized for Meta Business Suite and Postiz connection. |
| TikTok | Existing FAMtastic account declared under `fritz.medine@gmail.com` | Connect after Meta proof; confirm handle and business/creator state during OAuth. |
| YouTube | Existing channel currently owned through `nineoo@yahoo.com` | Keep the channel; use YouTube Brand Account/channel permissions to add `fritz.medine@gmail.com` as manager/owner instead of sharing credentials. |

The login email does not determine the public brand. All business-facing
profiles, media, captions, links, and sender identity must present FAMtastic
Designs consistently.

## Remaining social-account information

For each channel FAMtastic wants enabled, collect the account identity and complete the official OAuth/developer-app authorization. Never paste account passwords into the repository.

| Channel | Account information Fritz supplies | Provider setup still required |
|---|---|---|
| Facebook | FAMtastic Page URL/name and confirmation Fritz is an admin | Meta developer app ID/secret; authorize the Page with least privilege |
| Instagram | `@handle`, professional-account status, and Page linkage | Use the same Meta app; authorize Instagram content publishing |
| Threads | `@handle` | Threads app ID/secret and official OAuth |
| TikTok | `@handle` and business/creator status | TikTok developer client ID/secret, Direct Post scope, audit if public posting is required |
| YouTube | channel URL/ID | Google Cloud OAuth client ID/secret and YouTube upload authorization; unverified projects may be private-only |
| LinkedIn | profile URL and company Page URL; confirm Page admin | LinkedIn app client ID/secret and organization posting permissions |
| Pinterest | profile/business URL | Pinterest app client ID/secret and board authorization |
| X | `@handle` | X developer API key/secret and a plan with write access |

Also decide the first-wave channel order. Recommended: Facebook Page + Instagram, then TikTok and YouTube Shorts, followed by LinkedIn. This reduces simultaneous OAuth and review variables while preserving the same campaign IDs and assets.

## Owner review model

Postiz is the publishing workspace: composer, channel previews, media library,
calendar, scheduling, provider delivery, inbox, and platform analytics. Drupal
is the management workspace: approval readiness, exceptions, stable content
IDs, GA4/lead/order attribution, revenue, and the decision trail. A publishing
attempt never counts as delivered until a provider-verification event exists.

The command center is mobile-first and keeps the dense historical campaign
table in an independently scrollable region. The 390px browser proof recorded
zero document-level horizontal overflow, nine visible pulse cards, all 17
campaign days, and 76px-high primary actions.

## Meta connection status

Completed and verified on 2026-08-12:

- Meta developer registration is complete under `nineoo@yahoo.com`.
- The unpublished app **FAMtastic Social Publishing** was created with app ID
  `1761267725205283`.
- Its selected use cases are **Manage everything on your Page** and **Manage
  messaging & content on Instagram**. Advertising-management access was not
  requested.
- Meta Business Suite already contains the canonical `FAMTastic Designs` Page
  with asset ID `179965042038743`; no duplicate Page was created.
- Account identity was corrected after the first linking attempt:
  `@famtstic` is Fritz's personal account, while `@famtasticdesigns` is the
  canonical business account. The personal account was mistakenly converted to
  Business during discovery; Fritz is reversing that change. Personal contact
  information was never exposed and no content was posted.

Current provider blocker: Meta Business Suite still shows **Connect
Instagram**, its onboarding counter remains `0 / 1`, and Settings > Profiles
lists only Facebook Pages. Therefore no Instagram relationship is verified.
The next attempt must explicitly authenticate `@famtasticdesigns`; do not use
the saved personal `@famtstic` profile. If Meta reuses the personal session,
choose **Switch accounts** and authenticate the business account. No social
post, ad, invitation, or public campaign action was created.

After that checkpoint, verify the linked Instagram handle in Meta Business
Suite, inspect Page/business access, connect the Meta provider values to Postiz,
perform an unpublished/draft proof, and retain provider evidence. Publishing
remains disabled until Fritz separately approves a bounded public batch.

## Connection and launch procedure

1. Confirm `@famtstic` is restored to Personal, then authenticate the distinct
   `@famtasticdesigns` account and confirm it is Professional/Business.
2. Verify that Meta Business Suite shows both the canonical Facebook Page and
   the correct Instagram handle, with Fritz retaining full control.
3. Inspect/create the FAMtastic Meta Business Portfolio and assign the Page,
   Instagram account, and developer app without duplicating assets.
4. Add only the Meta app ID/secret to the untracked Postiz environment and
   restart the local service. Never store the secret in Git or documentation.
5. Complete official OAuth in Postiz; passwords remain with Meta.
6. Create one unpublished/draft connection proof using a stable campaign
   content ID. Do not publish to the personal Facebook profile.
7. Verify account identity, crop, caption, link, UTM, provider ID, Inbox access,
   and removal/rollback evidence.
8. Repeat least-privilege OAuth for TikTok and YouTube only after the Meta proof
   passes; keep platform audit/private-only restrictions explicit.
9. Approve days 1–3 before any public scheduling.
10. Enable bounded public publishing only after Fritz explicitly approves the batch.

## Safety and operating commands

```bash
./scripts/postiz-local.sh config
./scripts/postiz-local.sh start
./scripts/postiz-local.sh status
./scripts/postiz-local.sh logs
./scripts/postiz-local.sh stop

npm --prefix marketing/video run studio
npm --prefix marketing/video run render:proof
```

`FAMTASTIC_MARKETING_PUBLISH=false` remains the repository default. Postiz being online does not authorize posting.

## Reuse and extraction

Provider-neutral approval, scheduling, and evidence contracts stay in `marketing/engine`. FAMtastic branding and campaign truth remain in `marketing/brands/famtastic` and `marketing/campaigns`. Once the workflow proves multi-channel OAuth, draft scheduling, delivery verification, retry/alert behavior, and analytics attribution, the engine can move to a dedicated repository without extracting Drupal/customer secrets.
