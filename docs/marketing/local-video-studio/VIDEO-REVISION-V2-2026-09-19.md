# Video revision two — 2026-09-19

## Owner feedback and result

The owner rejected the first film’s slow narration and the pronunciation of FAMtastic. This supersedes the earlier ASR-based pronunciation claim. The original 103.933333-second film, audio, scripts and evidence remain unchanged.

The new film is **72.8 seconds** (2,184 frames, 1920×1080, 30 fps), with all 21 source lines retained. The new local Kokoro narrator uses speed **1.06**, versus 0.78 before, and 0.18-second inter-line gaps. Its 70.778667-second AAC master measures **−16.6 LUFS / −1.7 dBTP**. The engine receives explicit brand phonemes `fæmtˈæstɪk` (fam-TAS-tik); source/caption spelling is untouched. The installed phonemizer’s previous title-case output was `fæmtˈeɪstɪk`, explaining the owner’s fam-TAY-stik complaint.

This is the stock synthetic narrator `am_michael`, not a clone of Fritz. Listening preference and creative acceptance remain the owner’s review.

## Proof

- Local TTS inference: **17.241 seconds** for all 21 utterances.
- Native HyperFrames render: **31.952 seconds**; combined native check/render ledger stage: **82.778 seconds**.
- Identical project cache lookup returned the same final SHA in a **0.780-second command**.
- Full decode passed; final AAC packet hash and decoded PCM hash exactly equal the new master.
- Native motion validation: 300 samples, zero findings. Contrast: 42/42 passed.
- Local ASR contains all source content. It is a content check, not listening acceptance.
- CUA played the full 72.8 seconds with audio unmuted, ended=true and no media error. The optional caption track loaded with readyState=2.
- Video Studio at part-one delivery: **97 tests passed**; the expanded local voice-conversion suite subsequently passed **105 tests** in 12.077 seconds. Existing no-clobber publisher: **11 tests passed**. Brand asset check passed.

Final MP4 SHA-256: `41fa7b56d73db8a520a014d4084669ae2ce1d4fca5b82427f3c92d60f5438b79` (8,271,743 bytes).

## Source and evidence

Campaign: `marketing/brands/famtastic/video-studio/whats-the-catch-v2/`. Shared new pronunciation module: `marketing/engine/video_studio/fam_video/brand_voice.py`. Local voice runner: `scripts/famtastic-local-narration.py`; freezes script, runner and phonetic contract before inference, requires pinned installed model/package hashes, and refuses an existing output directory.

Local evidence root: `artifacts/video-studio/no-catch-v2-20260919/`. Render: `artifacts/video-studio/project-whats-the-catch-v2-20260919T182234Z-ac03a770/`. Narration, render and QA each have canonical Build DNA. The source-supplied copied campaign graphics are unchanged; scenes/captions are timed from the new PCM lengths.

## Delivery status

PR [27](https://github.com/famtastic-fritz/famtastic-designs/pull/27) merged at `31a92fe4b9335c0f94557b8dec294f6301eb36f9` (implementation `4cc5b27f58e19ab81e15052a545af31365c09e9f`). The fixed versioned MP4/JPG/VTT set is published; the existing `/why-famtastic/` page and its first-film assets remain intact during owner review. No frontend code release occurred; its previous runtime remains `63114513`.

Watch: https://famtasticdesigns.com/media/films/whats-the-catch-v2-20260919.mp4

All three public hashes and MIME types match local delivery bytes. MP4 byte ranges return 206. CUA played the public MP4 through 72.8 seconds with ended=true, unmuted audio and no media error. Narration/render/QA Build DNA projections are Drupal rows **49, 50 and 51**.

The one fresh `video-v2-20260919-motion-complete` standard/v2 owner notice has SMTP acceptance: outbox **802**, sent 2026-09-19, provider Message-ID `<h2w1Wcg2fK5FldRV9VSM3tF4c0Xa4u8tqf5dz07o@default>`. Gmail inbox message `1a0baf83f0139a37` verifies the exact link, standard/v2 header and SPF/DKIM/DMARC pass. A read-only repeat returned already_sent / resent=false and preserved outbox 802. The notice script ran from the exact Git main source checked out privately outside the document root.

Additional checks: two fixed-edition/collision publisher tests, five creator-credit tests and 86 shared email presentation assertions passed. GitHub Actions did not start because the account is locked by a billing issue; remote CI is not green. No billing or repository-protection setting was changed. Local source/native checks are recorded separately.

## Remaining work in this revision

The presenter-led continuation and the local synthetic voice-conversion experiment now have separate native proof in [WALKING-AND-VOICE-PROOF-2026-09-19.md](WALKING-AND-VOICE-PROOF-2026-09-19.md). Personal voice matching and a new generated walking performance remain unproved. No paid provider, social scheduler or automatic publication has been enabled.
