# TikTok Content Posting API — Production Review Submission (drafted, not yet submitted)

Status: draft only. Nothing has been submitted to TikTok. This file exists so
the drafted text isn't conversation-only — see
`~/Development/FAMtastic/plans/social-posting-capabilities/plan.md` for the
full checklist this belongs to.

## Pre-submission checklist (Fritz-only steps)

1. Confirm both `@famtasticdesigns8` and `@famtasticdesigns` are still listed
   as Sandbox Target Users in the TikTok developer portal — not verifiable
   from this side.
2. Re-run before recording: `docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -c "SELECT \"refreshNeeded\",\"tokenExpiration\" FROM \"Integration\" WHERE \"providerIdentifier\"='tiktok';"` — if `refreshNeeded` is true or `tokenExpiration` has passed, re-authenticate in Postiz (the one OAuth click) before recording, not during.
3. Open famtasticdesigns.com (Privacy Policy / Terms of Service pages) in the
   browser you'll record — start the demo there.
4. Get the current ngrok URL fresh, same day: `curl -s http://127.0.0.1:4040/api/tunnels`.
5. Navigate from famtasticdesigns.com into that ngrok URL, into Postiz's composer.
6. In the composer, show on camera: the "Posting to TikTok account: [name]
   (@handle)" header, the privacy-level dropdown (real options only, nothing
   pre-selected as public), and comment/duet/stitch toggles sitting off.
7. Compose and post (or upload-only) one real video with your own conscious
   choice of privacy level and toggles, on camera.
8. Stop — do not click Submit on TikTok's app review yourself; that's a
   separate, deliberate final step.

## Composer fix — verified live 2026-09-04

Confirmed live inside the running `localhost/postiz-famtastic:tiktok-ux-fix`
container (`apps/frontend/src/components/new-launch/providers/tiktok/tiktok.provider.tsx`):

- No hardcoded privacy default — starts unset, filtered to the account's real `privacyLevelOptions`.
- Creator identity header renders: "Posting to TikTok account: {nickname} (@{username})".
- `duet`, `stitch`, `comment` all default to `false` (unchecked).

Scopes in use (`libraries/nestjs-libraries/src/integrations/social/tiktok.provider.ts`,
lines 34-40): `video.list, user.info.basic, video.publish, video.upload, user.info.profile, user.info.stats`.

## Draft submission text — "Explain how each product and scope works"

> **user.info.basic / user.info.profile / user.info.stats** — Postiz reads
> the connected creator's basic profile (open ID, nickname, avatar) and
> public stats so the composer can display "Posting to TikTok account:
> [name] (@[username])" before every post, and so it can populate the
> account's actual privacy-level and interaction-permission options (a
> private account, for example, has no "Public to everyone" option). No
> profile or stats data is stored beyond what's needed to render this
> per-post confirmation UI.
>
> **video.list** — Used only to confirm a video was successfully received
> after upload/publish, by querying the account's video list for the new
> post ID. Not used to browse or display the creator's historical content
> library.
>
> **video.publish** — Used when the user selects "Post content directly to
> TikTok" in Postiz's composer. Publishes the scheduled video with the
> privacy level, comment/duet/stitch settings, and disclosure/branded-content
> flags the user explicitly chose in that post's settings — every field
> defaults to the most restrictive/private-safe option and requires the user
> to opt in.
>
> **video.upload** — Used when the user selects "Upload content to TikTok
> without posting it," which sends the video to the creator's TikTok inbox
> for them to review, edit, and manually publish inside the TikTok app.
> Postiz never auto-publishes in this mode.

Grounded only in what Postiz actually does — verified against the real
provider source, not written from a template.
