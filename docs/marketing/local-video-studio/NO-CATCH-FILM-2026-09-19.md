# “What’s the catch?” — original film and owned-page release

The owner supplied the complete $199 Special / “You grow. We grow.” script and explicitly requested a new original film, proof, publication somewhere on famtasticdesigns.com, and one email with the link. This scope authorizes the exact film/page release and the owner notification; it does not authorize social scheduling, paid-provider generation, new prices, or unrelated production changes.

## Source and creative continuity

- Source branch: `codex/famtastic-no-catch-film`, isolated from existing dirty work, based on `2bb9a176c096297a7a2efc4b3b83f18ab39438bb`.
- Authored source: `marketing/brands/famtastic/video-studio/whats-the-catch/`.
- Full script SHA-256: `12a242dae7818a836495ebc92bb3ff0274ccddfc230df531c9016a8261c0676f`.
- Eleven original HTML/SVG/GSAP scenes; 1920×1080 at 30 fps. Original concept objects illustrate a business foundation, direction and growth without invented customer metrics.
- Metropolis and Kaushan Script from the existing local brand library, exact canonical logo SHA-256 `ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`, shared creator credit.
- New Kokoro local narration, voice `am_michael`, speed 0.78. No reused presenter, voice clone, stock performance, paid inference or prior movie soundtrack.
- Page: `/why-famtastic/`, linked from the existing $199 offer page, with player, poster, optional WebVTT track, complete source transcript, canonical scope/renewal terms and research-first CTA.

The original movie from the earlier task is not an input to this film. The existing local renderer and its explicit lossless AAC mastering path are reused. Generated media and raw evidence remain outside Git under `artifacts/video-studio/no-catch-20260919/` and the renderer’s immutable run directory.

## Validation record

- Video Studio unit suite: **93 passed** in 12.000 seconds.
- Canonical brand asset check: passed; creator-credit contracts: **5 passed**.
- New page source contracts: **4 passed**. Delegated automated responsive checks passed at 320, 390, 768 and 1440px. Their one-off harness is not shipped; final acceptance uses CUA in accordance with the repository browser policy.
- Existing content-family checks: **68 passed**. Node 22 frontend production build passed at the page checkpoint.
- First native film audit: runtime/layout/motion/contrast passed, **300 motion samples**, **47 contrast checks**. The decorative SVG crop, late opening headline and cramped question layout were repaired in the next authored version. The remaining duplicate-image lint advisory refers to intentional canonical logo placements in the header and creator credit; initial evidence is retained.
- First full ASR audit caught “FAM” being pronounced as initials. A controlled local title-case/hyphen A/B confirmed the correct spoken form. Four utterances were regenerated in a separate recorded run; the other seventeen original WAVs remain hash-bound. The initial quiet master and acronym reading were not silently accepted.

Two existing broad checks are not green: `validate-demand-content.py` reports the unchanged `own-your-online-presence` series has fewer than eight posts and no pillar; the broad package experience harness receives the isolated Vite HTML fallback at `/jsonapi` instead of Drupal JSON. Neither failure is caused by the new film/page. The focused route tests and real production browser checks are the relevant release proof. GitHub Actions had an existing account billing lock before this change; each release records its actual CI status separately.

## Delivery and release evidence

Final v3 is a **103.933333-second, 1920×1080, 30 fps** H.264/yuv420p film with 3,118 frames and one AAC narration stream. The audio lasts 101.930 seconds, followed by a 2.003-second closing hold. The 9,326,264-byte MP4 SHA-256 is `7e9b3f72e553fbe69a6b06e78713fde2573cfa84ba21a3952f4f0081cc9ad0d1`.

- Final immutable render: `artifacts/video-studio/project-whats-the-catch-20260919T172357Z-93ce024e/`. Native check and render stage: **92.333 seconds**; native renderer reports **91.897 seconds**. Identical final-project cache lookup: **0.916 seconds**, verified same MP4 hash.
- Final runtime, layout, motion and contrast audit passed, with **300 motion samples and 41 contrast checks**. One duplicate-image lint advisory remains for intentional header/footer logo reuse. Independent snapshots cover all eleven scenes; the final offer card was corrected to say “basic managed hosting.”
- Exported AAC packets and decoded PCM both match the narration master exactly. A complete FFmpeg decode reported no errors. Master loudness: −16.5 LUFS, −1.8 dBTP. Final local ASR checked all 21 utterances, including the corrected brand pronunciation.
- CUA observed the final local page’s video reach its natural end at 103.933333 seconds, unmuted and readyState 4, at 1920×1080. The transcript has 21 paragraphs; the optional VTT track is initially disabled because captions are already burned in. Responsive inspection covers 320, 390, 768 and 1440px.
- Render Build DNA validation passed: **6 stages, 31 artifact checksums**. Raw native audio, failed attempts, pronunciation trials, source snapshots, final audio-master comparison, cache receipt and CUA evidence are retained locally.
- Final publication set is `artifacts/video-studio/no-catch-20260919/media-v3/`: MP4 above; JPEG SHA-256 `99bfb0baaf6083c68c3a9e502ec6dc98c7c5f4842792f038eae0b750417bdfce`; WebVTT SHA-256 `101db30c2a9149bad5e2a252ad2da4a9141a710025adb60bd4ad418f7c30c59d`. Publisher tests: **11 passed**.

The local delivery copy is `~/Downloads/FAMtastic-Whats-The-Catch-20260919.mp4`. This source checkpoint is ready for the explicitly authorized release. Public upload hashes, deployed commit, live browser evidence and notification receipts are appended after those actions occur; this checkpoint does not claim publication or email delivery.

## Release mechanics

The normal server-side frontend build consumes clean Git source. It cannot consume ignored local MP4 output. `scripts/publish-no-catch-film.py` stages only the three versioned film assets privately, checks SHA-256, installs without clobbering existing files, and records verified receipts; it does not recursively synchronize or delete the mixed document root. The canonical `scripts/deploy-frontend-godaddy.sh` remains the only frontend code deployment lane.

`backend/scripts/send-owner-no-catch-film.php` defaults to a read-only preview. Its fixed owner recipient, content binding and unique key use the existing standard/v2 branded outbox. A unique insert prevents a racing merge from resetting an attempted notice; dispatch is limited to the exact one key with one maximum attempt. SMTP acceptance and verified mailbox receipt are recorded separately. Source deployment alone sends nothing.

## Limits

Narration is local synthetic speech, not Fritz’s voice. Caption chunks are distributed inside measured utterance bounds rather than forced word alignment. Signal checks, transcription and observed player state do not substitute for a human listening/creative preference review. No original generated live-action footage, external music, paid provider, social posting, customer charge or autonomous campaign scheduling is included.
