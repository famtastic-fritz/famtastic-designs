# RUNBOOK: Instagram Standalone Onboarding (Postiz)

Proven end-to-end 2026-08-24 on our own account. Use this exact sequence for every future client. Source of the paid-service onboarding flow (see PRODUCT_PIPELINE).

## Gates — check in order, stop at first failure

| # | Gate | How to verify |
|---|------|---------------|
| 1 | IG account is Professional/Business or Creator | Instagram → Settings → Account type |
| 2 | Client owns/controls the account and is present | They perform the Allow click themselves |
| 3 | Postiz has `INSTAGRAM_APP_ID` + `INSTAGRAM_APP_SECRET` set | `docker exec postiz printenv \| grep INSTAGRAM_APP_ID=1` style check (non-empty) |
| 4 | Redirect URI whitelisted under Meta app → Instagram product → **API setup with Instagram Login** → business login OAuth settings | Read it back from portal; must be exactly `<public-url>/integrations/social/instagram-standalone` |
| 5 | Client's IG account added as **Instagram Tester** AND invite ACCEPTED | Portal Roles tab + client's instagram.com → Settings → Website permissions → Apps and websites → **Tester Invites** (not email, not Active apps) |
| 6 | Browser session is signed into the CORRECT handle | Check profile chip before OAuth — saved-account shortcuts silently pick wrong accounts (@famtastic ≠ @famtasticdesigns) |
| 7 | Explicit client approval immediately before Allow | Verbal/written OK logged |

## Connection procedure

1. Prefer API-minted handshake over UI button:
   `GET /api/integrations/social/<public-host>/…` → login via
   `POST /api/auth/login {"email","password","provider":"LOCAL"}` (cookie Domain=.ngrok-free.dev), then
   `GET https://<public-url>/api/integrations/social/instagram-standalone` → open returned `url`.
2. Client completes Allow through scope list (business_basic, content_publish, manage_comments, manage_insights).
3. If ngrok free interstitial appears mid-callback, click Visit Site once — or expect failure; production deployments must use a domain without interstitials.

## Proof of done (both required, mask tokens in reports)

```
docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -c \
 "SELECT name,\"providerIdentifier\",\"tokenExpiration\"::date FROM \"Integration\" WHERE \"providerIdentifier\"='instagram-standalone' AND \"deletedAt\" IS NULL;"
curl -s "https://graph.instagram.com/v21.0/me?fields=username,account_type&access_token=<token>"   # HTTP 200 + correct username/account_type
```

## App icon recipe (TikTok/X/YouTube portals — 1024×1024 PNG)

Source of truth: `~/Downloads/FAMtastic-Vector-Working.ai` (master vector).
Generated icon (git-tracked): `marketing/brands/famtastic-social-app-icon-1024.png`.

Regenerate after logo changes:
```
qlmanage -t -s 2048 -o /tmp <master.ai>          # QuickLook renders AI → PNG
sips --resampleWidth 1024 /tmp/<name>.ai.png --out /tmp/icon-1024w.png
sips --padToHeightWidth 1024 1024 --padColor FFFFFF /tmp/icon-1024w.png \
  --out marketing/brands/famtastic-social-app-icon-1024.png
```
(No ImageMagick/Inkscape on this machine; QuickLook+sips is the proven path.)

## Portal field cheat-sheet (TikTok example, reuse pattern per platform)

| Field | Value |
|---|---|
| App name | `FAMtastic Social Publishing-<TT/X/YT>` |
| Category | Business |
| Description | `FAMtastic Designs publishes and manages its own business content on TikTok from one dashboard.` |
| ToS / Privacy URLs | `https://famtasticdesigns.com/terms` · `/privacy` (⚠️ pages still to be built — business todo) |
| Platforms | Web only |
| Redirect URI | `<public-url>/integrations/social/<provider>` |
| Submit for review | **NO** for own-account use — sandbox/development mode covers it; production review only when onboarding paying clients |

## Domain verification (proven methods, best first)

TikTok (and similar portals) require proving ownership of the site domain before accepting ToS/Privacy/Website URLs.

**Method 1 — DNS TXT (PREFERRED, proven 2026-08-24).** Portal → URL properties → choose DNS/Domain method → it issues a TXT token → owner adds TXT record at registrar (GoDaddy UI, ~2 min, both apex and www if portal checks both). No deploy, no dirty-tree dependency, survives redesigns. Evidence: `dig TXT famtasticdesigns.com` shows `tiktok-developers-site-verification=<token>`.

**Method 2 — verification FILE.** Download `tiktok<token>.txt` → place in `frontend/public/` (Vite copies to deploy root) → commit + deploy (owner gate; if tree is dirty, a surgical scp of the committed file to `~/public_html/` mirrors git without drift) → file must resolve at `https://<domain>/<filename>.txt`. ⚠️ TikTok's checker fetches WITH a trailing slash — the htaccess rule `RewriteRule ^(tiktok[A-Za-z0-9]+\.txt)/$ /$1 [L]` (in `frontend/public/.htaccess`) handles it; without it you get 404 → "signature not found". Tokens rotate on failed attempts — always re-download and redeploy the newest file.

**Method 3 — meta tag.** Add portal-issued `<meta>` to `frontend/index.html` <head>, same deploy path as Method 2.

## Never
- Touch an existing Facebook integration while adding Instagram standalone.
- Proceed past gate 5 without acceptance — error will be "Insufficient Developer Role".
- Store or transmit secrets through chat/email; use env file + Keychain.

## Refresh discipline
Tokens expire ~60 days (refresh token auto-renews while channel stays healthy). Record expiry date in campaign ledger; reconnect reminder at T-7 days.

## Production-app note (paid clients)
Development-mode tester access covers OUR accounts only. Real client onboarding requires a published Meta app with completed App Review for `instagram_business_*` scopes — plan as its own NEW_PRODUCT step before selling.
