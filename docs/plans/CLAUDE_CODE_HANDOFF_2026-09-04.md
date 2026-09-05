# Claude Code handoff — recovery completed through production frontend release

## Current position

- The interrupted Claude session is recorded in `docs/plans/CLAUDE_SESSION_RECOVERY_2026-09-04.md`.
- Site repository: `/Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs`, `main`, pushed as `a05c7c602aa94c4ec142fe9e14a22f0cc8cac0a2`.
- Frontend production release: same commit, deployed at `2026-09-05T01:12:41Z`; rollback backup `/home/xrdj7j99xhzt/backups/famtastic-frontend-20260905T011048Z-a05c7c602aa94c4ec142fe9e14a22f0cc8cac0a2.tgz`.
- Postiz source: `/Users/famtastic-fritz/Development/FAMtastic/tools/postiz-app-src`, branch `codex/youtube-retry-fix`, commit `ea5db55` (`Retry transient YouTube uploads`). This is a local vendor fork branch, not pushed to the upstream `gitroomhq/postiz-app` remote.
- Running Postiz container: `postiz`, image `localhost/postiz-famtastic:youtube-retry-fix`, health `healthy`. Pre-change database backup: `/private/tmp/postiz-db-backup.d5nMOL/pre-youtube-retry-fix.dump`, SHA-256 `9272e7283ed72c1141ba39692eddfca68090a794b3db7ec692e137df6c33a091`.

## What was completed

1. The fallback generated blog-art hero is now a compact masthead; ownership art wraps text on desktop. Author raster visuals were not changed. Production browser review of `/blog/why-running-business-on-gmail-and-linktree-costs-revenue/` found a 220px art hero, 250px desktop wrap figure, no horizontal overflow, and no console errors.
2. YouTube upload retry now classifies transient network errors as retryable and re-fetches the media stream for every attempt. Gaxios internal retry is disabled for this stream path so it cannot reuse a consumed stream. Tests in the built image proved `ECONNRESET` retries and HTTP 400 does not.
3. The four inherited booking-app files are draft-only `draft.md` files. Their `brief.md` and `seo-check.json` files do not exist, so `scripts/publish-blog-draft.py --draft <slug> --dry-run` correctly fails loud. Do not publish or characterize them as ready until those artifacts are added and dry-run passes.

## Do not change without fresh owner direction

- `drop-06` remains armed for `2026-09-05T09:00:00-04:00` / `13:00Z`. It was not rescheduled or recreated during recovery.
- Do not claim a YouTube publish. After the attempt, inspect the authoritative Postiz/provider record. If it errors, preserve the exact error payload and retry count before changing code or requeuing anything.
- The Postiz fork still has pre-existing uncommitted TikTok changes in exactly three files: `apps/frontend/src/components/new-launch/providers/tiktok/tiktok.provider.tsx`, `libraries/nestjs-libraries/src/dtos/posts/providers-settings/tiktok.dto.ts`, and `libraries/nestjs-libraries/src/integrations/social/tiktok.provider.ts`. Preserve them; they were deliberately excluded from `ea5db55`.

## Verification already performed

- `npm --prefix frontend run build` passed.
- `php -l backend/scripts/publish-single-blog-post.php` and `python3 -m py_compile scripts/publish-blog-draft.py` passed.
- `git diff --check` passed before commit/push.
- GoDaddy deployment preflight and apply passed for `a05c7c60`.
- Browser acceptance passed for apex and `www`: nonempty `#root`, expected main heading, current JS (`index-B6HFQ7Vb.js`) and CSS (`index-CovqJJSf.css`), no console errors. Both asset types returned 200 with JavaScript/CSS MIME types.

