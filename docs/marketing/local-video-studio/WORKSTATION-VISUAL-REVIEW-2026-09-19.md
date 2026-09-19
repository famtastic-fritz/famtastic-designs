# Four-format workstation visual review — 2026-09-19

## Result

Static visual review passed for the four existing native HyperFrames demo renders. I reviewed the four contact sheets and extracted five frames from each MP4 at frame indices 45, 135, 225, 315 and 359 (1.5s, 4.5s, 7.5s, 10.5s and 11.967s at 30 fps). The extractions are in ignored `artifacts/video-studio/workstation-proof/visual-review/`. No render was repeated.

The evidence listing is `artifacts/video-studio/workstation-proof/four-formats.stdout.json`. FFprobe reports each MP4 as H.264, 30 fps, 360 frames and 12 seconds:

| Format | Resolution | Render run | Video SHA-256 |
| --- | --- | --- | --- |
| 9:16 | 1080 × 1920 | `business-has-an-address-20260919T140605Z-f156f1c3` | `20906fcc9aa0fa54d4c322cc3b8f48942fb1c8585529276a05d7404c29b42ef6` |
| 4:5 | 1080 × 1350 | `business-has-an-address-20260919T140634Z-2d51372c` | `ffc4a4b21085f431c2a5254722279ae4815c74628469727563a59656049ec922` |
| 1:1 | 1080 × 1080 | `business-has-an-address-20260919T140657Z-9db468b9` | `740b3c65a0164f61f9a10a12d4ae4f6466979beaabe1b2d96892f398f101111a` |
| 16:9 | 1920 × 1080 | `business-has-an-address-20260919T140721Z-aedbce7e` | `31bff954934086df19c699bb84abdcd09560531d296116c42b91e1ca38d85aa1` |

## Observations

- All four contact sheets show the same four scenes in order: “Business is happening,” “Give it an address,” “Built to be found,” and “Stand apart on purpose.” The four compositions are present in every ratio; I saw no missing scene.
- Headline, eyebrow and body copy remain inside the frame in the selected frames for all formats. The mock website card's title and bullet copy wrap onto short readable lines in 4:5 and square; they stay mostly on one line in portrait and 16:9. No visible text truncation, collision or edge crop appeared.
- Contrast is strong across the gold, orange, pink and lime scene palettes. The mock website card in the address scene stays contained. The logo at the top and small centered logo in the footer retain their colors and proportions in each reviewed frame; the lower caption/footer combination does not overlap.
- Both caption windows are legible in all four formats. “Give people one place to find your business.” wraps to two lines in portrait, 4:5 and square, and to one line in 16:9. “Stand apart on purpose.” repeats the final scene headline while it is active; this reads as intentional subtitle copy but is mildly redundant with the main title.
- At the last encoded frame (11.967s), the closing scene remains fully composed: headline, supporting line, `famtasticdesigns.com` CTA and footer mark are still visible. The second caption has ended by that frame, leaving a clean end card.

## Limits

This is a still-frame review of contact sheets and selected decoded frames. It supports observations about layout, text fit, color, scene presence and the end frame only. It does not establish animation continuity, transition quality, playback behavior, audio/caption synchronization, or sound quality. The parent lane owns browser playback and audio review; no human-playback or audio-review claim is made here. These are motion-graphics demos, not recreated live-action source films or publication-approved campaign assets.
