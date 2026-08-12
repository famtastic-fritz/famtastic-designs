# Social Automation Handoff

Date: 2026-08-12

## Installed foundation

- **Postiz v2.22.1** is pinned as the self-hosted scheduling and provider layer.
- **Docker + Colima + Docker Compose** provide a local-only runtime on this Mac.
- **Remotion 4.0.332** provides reusable, branded motion templates alongside the existing FFmpeg pipeline.
- The canonical 17-day manifest remains the source for 68 content moments, stable IDs, UTMs, approval state, and evidence.
- Public publishing remains disabled. Provider credentials are stored only in an untracked local environment file.

The Postiz scheduler is bound to `127.0.0.1:4007`; it is not exposed to the public internet. The upstream deployment repository is installed at `/Users/famtastic-fritz/Development/FAMtastic/tools/postiz-docker-compose`, and the source snapshot used for inspection is installed at `/Users/famtastic-fritz/Development/FAMtastic/tools/postiz-app` at tag `v2.22.1`.

The local owner is `fritz.medine@gmail.com`. Its generated password is stored in
macOS Keychain under service `FAMtastic Postiz Local`; it is not written to the
repository. Registration has been disabled after owner creation.

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

## Connection and launch procedure

1. Open the local scheduler at `http://127.0.0.1:4007` and create the one local owner account.
2. Change `POSTIZ_DISABLE_REGISTRATION=true` in `~/.config/famtastic-marketing/postiz.env`, then restart Postiz.
3. Add only the provider app values for the first channel and restart.
4. Complete official OAuth in Postiz; passwords remain with the social provider.
5. Schedule one private/draft test using a stable campaign content ID.
6. Verify crop, caption, link, UTM, provider ID, and removal/rollback evidence.
7. Approve days 1–3 before any public scheduling.
8. Enable bounded public publishing only after Fritz explicitly approves the batch.

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
