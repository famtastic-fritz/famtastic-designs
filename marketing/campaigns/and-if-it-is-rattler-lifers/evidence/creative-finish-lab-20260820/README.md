# Rattler Lifers — Creative Finish Lab

This lab preserves the selected Gemini Flash Lite reference recreation before each treatment.  Nothing here replaces the source.

| Stage | File | State |
| --- | --- | --- |
| Source | `01-flash-lite-reference-source.jpg` | preserved, SHA-256 `55a4cbd0099bb17c21179cdbb2d28b405c300eccf5f7205ea46ab31e2f39e34f` |
| Photoshop 2026 | `02-photoshop-finish.psd` and `02-photoshop-finish.jpg` | rendered and visually checked |
| Premiere Pro 2026 | `03-premiere-source-copy.jpg` and `03-premiere-motion-treatment.json` | source and exact treatment spec ready; no Premiere render claimed because its local CEP bridge timed out |
| After Effects 2026 | `04-after-effects-source-copy.jpg` and `04-after-effects-build.jsx` | source and actual AE build script ready; no AE render claimed |
| Remotion | `05-remotion-rattler-ambient.mp4` and `05-remotion-rattler-ambient-preview.jpg` | actual 1920x1080, 30fps, 4.05s render; visually checked |

`00-live-comparison.jpg` is ordered left-to-right: source, Photoshop finish, the 2-second Remotion preview frame.

## Photoshop finish recipe

- Curves: `(0,0) (48,39) (128,137) (208,222) (255,255)`
- Brightness `-2`; contrast `+7`
- `0.6%` Gaussian monochromatic grain
- Unsharp Mask `55%`, `1.0px`, threshold `4`

The intent is a finish pass, not a redesign: retain the image composition, wardrobe, faces, stadium, ticket album, and megaphone exactly as generated.

## Actual verification

- Photoshop output was reviewed in Photoshop after export.
- Remotion video was rendered directly with the local Remotion CLI. `ffprobe` confirms H.264, 1920x1080, 30fps, 4.053333 seconds. Its midpoint still was reviewed.
- The hash and current state of every artifact is recorded in `manifest.json`.
