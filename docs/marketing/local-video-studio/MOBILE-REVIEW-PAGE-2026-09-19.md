# Mobile video review page — September 19, 2026

The owner explicitly requested website publication for phone viewing and a fresh email with the link. `/video-review/` brings together the three already published revision films: the portrait walking continuation, the faster full-script ad and the explicitly experimental synthetic voice comparison. It adds no new generated media or personal voice claims.

The page uses the shared FAM content primitives and exact creator credit, native inline video controls, optional WebVTT tracks, direct MP4 links and one-player-at-a-time audio. `preload="none"` avoids downloading three movies before a viewer taps play. It uses no autoplay. The review page declares `noindex, nofollow` in both its generated shell and hydrated metadata and is omitted from the sitemap; this is indexing guidance, not access control.

Local validation: production build passed (220 HTML outputs); 9 existing film/creator-credit contracts passed; canonical brand equality and new notice PHP syntax passed. CUA checked the built page at 390 and 320 pixels with no horizontal overflow, verified walking/ad playback and observed the first player pausing when the second starts. Media bytes are the previously proven immutable outputs. Receipt root: `artifacts/video-studio/mobile-review-20260919/`; canonical ledger: `run/build-dna.json`.

## Live release and verified delivery

Watch on a phone: https://famtasticdesigns.com/video-review/

Implementation `8e50a5c26511fe45633c048f77dfe3550e28c261` merged in [PR 30](https://github.com/famtastic-fritz/famtastic-designs/pull/30). The canonical deployer published exact main **`736ab1ff2f357e83839fdce955753aabe6ac84ca`** at **2026-09-19T21:10:48Z**, using Node 22.23.2. The server build inventoried 220 HTML outputs and verified 218 route shells. Backup: `/home/xrdj7j99xhzt/backups/famtastic-frontend-20260919T210747Z-736ab1ff2f357e83839fdce955753aabe6ac84ca.tgz`. The deployed marker and private notice-source hash were read back independently.

Both apex and www return the same route shell, SHA-256 `7adffc7a9992f6fb96afb718a15152d150481c677bd6b6fb7cad6eeead823f24`, with the intended title, noindex and canonical apex URL. CUA verified the public page at 390px, including complete 35.583333-second walking-film playback with unmuted audio and no media error. The www page rendered at 1280px with all three players, canonical credit and no console errors. Both layouts have no horizontal overflow. This is browser viewport testing, not a physical iPhone/Safari certification.

All three live MP4s still match their original SHA-256 hashes and byte lengths and return 206 range responses. All three posters and caption files return the expected HTTP status/MIME. Local CUA additionally played all three films and verified exclusive audio playback; the voice comparison reached its natural end. The independent source review found no release blocker. It noted that “side by side” describes the sequential voice comparison figuratively.

The newly authorized notice used the existing standard/v2 renderer, signed Shay, with key `video-review-page-20260919-phone-v1`. Outbox **805** records one sent, zero failed/retried and provider Message-ID `<lMCNXwBWhKAUkc4PgtV57RosdMqu6WMjiJyGXo@default>`. Gmail inbox message **`1a0bb84c57aeb599`** verifies recipient `fritz.medine@gmail.com`, subject “Your videos are ready to watch on your phone,” the exact page link, template header and SPF/DKIM/DMARC pass. A read-only repeat returned `already_sent` / `resent=false`; historical notices were preserved.

GitHub Actions run `35469336970` could not start its three jobs because of the account billing lock. Local build/browser checks and actual deployment/email receipts are reported separately; no protection, billing or approval setting was changed. No new media generation, paid creative provider, broad email worker or social publishing was enabled.

Final canonical Build DNA `video-20260919T205918-95259a0597` passes **14 stage records and 35 artifact checksums**, and is registered as Drupal row **59**. All raw release, browser, hash and mail receipts remain under the evidence root above.
