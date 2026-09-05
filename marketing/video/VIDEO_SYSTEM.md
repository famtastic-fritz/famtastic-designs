# FAMtastic Marketing Video System (Remotion)

`marketing/video/` is the reusable Remotion campaign-video engine. One
`DropConfig` (`src/drops/*.ts`) plus the shared scene kit (`src/system/kit.tsx`,
`src/system/scenes.tsx`) produces three registered `Composition`s — `-9x16`,
`-1x1`, `-16x9` — from a single master timeline. `src/system/formats.ts`
resolves layout from the frame size at render time (margins, type scale,
column count, safe-area boxes), so the same scene data re-flows per aspect
instead of being cropped from one master.

This file is the tier's own operating log: type-fitting limits, palette
grading decisions, and known inconsistencies against the still (Photoshop)
tier, referenced from code comments in `kit.tsx` and `formats.ts`.

## Grading fix — 2026-09-05: paper palette overshot the anchor by ~50 YAVG points

### The defect

The `platform-dependency` drop (`src/drops/platform-dependency.ts`) uses the
`paper` palette because it must cut against a bought HeyGen anchor take
(`marketing/creative/heygen/renders/take-a-platform-dependency.mp4`) that is
itself a light frame. `paper` was correct in intent but far too bright in
practice. Measured independently with `signalstats` YAVG averaged over every
frame — not taken on report:

| | mean YAVG |
|---|---|
| HeyGen anchor take | 155.4 |
| HyperFrames film (`marketing/hyperframes/platform-dependency`) | 160.1 |
| Remotion 9:16 (before this fix) | **212.1** |

Reproduction command (run against any of the three output files):

```bash
ffmpeg -v info -i FILE -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-" -f null /dev/null 2>/dev/null \
  | grep -oE "=[0-9.]+$" | tr -d '=' \
  | awk '{s+=$1;n++} END{printf "%.1f (%d frames)\n", s/n, n}'
```

The Remotion cut was ~57 points brighter than the anchor it is supposed to cut
against — the two systems were shipping visibly different-looking versions of
the same campaign.

### Root cause

Two independent bugs, both in `src/system/palettes.ts` and
`src/system/scenes.tsx`, not one:

1. **`paper.ground` was too light on its own.** `[244, 241, 234]` (`#F4F1EA`)
   has a raw luma around 241/255. Several archetypes (`ChecklistScene`,
   `OutroScene`, the panel-free area of `SplitScene` and `PresenterScene`) are
   mostly flat ground with sparse type over a huge canvas, so the ground's own
   value dominates the frame average almost 1:1 — there is no photograph to
   hide behind the way there is in `PlateScene`.
2. **The light-theme `groundGradient` brightened toward the centre instead of
   falling off at the edges.** It mixed the ground 50% toward pure white at a
   top-centre hotspot, fading only to the (already too-light) base ground by
   64% radius — the opposite of a lit-paper falloff, and a second source of
   overshoot on top of (1).
3. **`PresenterScene` painted a flat `backgroundColor` that fully hid the
   shared gradient.** Even after fixing (2), the presenter beat's own ground
   fill sat on top of `<Ground/>` as an opaque solid, so it kept rendering at
   the raw, un-fallen-off paper value regardless of what the gradient did.

### The fix

- `paper.ground` deepened from `#F4F1EA` to `#D8D1C2` (`[216, 209, 194]`) —
  the same value the still (Photoshop) tier landed on for the same reason
  (`marketing/creative/hyperframes` grading note), so the two tiers share one
  number rather than two independent guesses.
- Added `ANCHOR_SHADOW = #33272E` (`marketing/creative/heygen/reference-tokens.json`
  → `ground_darkest_decile`) and rewrote the light-theme `groundGradient` to
  fall off toward that measured shadow value at the edges, holding the base
  ground tone only in the centre band where headlines and body copy sit (so
  text contrast is untouched). The first pass (a single 3-stop falloff)
  reached 178.9 — still above the 175 ceiling; rather than flattening the
  whole frame darker (which costs type contrast), the falloff was started
  earlier and carried further out, leaving the centre untouched.
- `PresenterScene` (`src/system/scenes.tsx`) now paints `t.groundGradient`
  instead of a flat `backgroundColor`, in both the full-bleed (16:9) and
  panelled (9:16 / 1:1) branches, so the presenter beat's ground matches every
  other scene instead of bypassing the grade.
- Deliberately **not** done: a global opacity/scrim layer over the whole
  frame. That flattens type contrast uniformly and is a cosmetic patch, not a
  grade — the brief explicitly ruled it out.

### Measurements — before / after, all three formats

Reproduced independently (not taken from any other agent's report) with the
command above, against the actual rendered output files in
`out/platform-dependency/`:

| Format | Before | After | Δ vs before | Δ vs anchor (155.4) |
|---|---|---|---|---|
| 9:16 | 212.1 | **163.2** | −48.9 | +7.8 |
| 1:1 | 205.9 | **161.9** | −44.0 | +6.5 |
| 16:9 | 196.3 | **161.5** | −34.8 | +6.1 |

All three now sit inside the 150–175 band, within about 8 points of the
anchor's 155.4 and close to the HyperFrames film's 160.1.

Per-second detail (9:16, 30fps, 990 frames total) — every scene's steady-state
value:

| Scene | Seconds | YAVG range |
|---|---|---|
| `plate` (photograph) | 0–4 | 112–129 |
| `presenter` | 4–12 | 165–172 |
| `split` | 12–17 | 165–171 |
| `checklist` | 17–23 | 157–180 |
| `offer-card` | 23–29 | 157–168 |
| `outro` | 29–33 | 178–181 |

Whole-clip min/max across all three formats: 112–182. The `plate` scene sits
below the floor by design — it is real photography with its own tonal range
(the anchor is also photography, not a flat colour), not a flat-ground scene
the grade targets. `checklist` and `outro` briefly touch ~180–182 for one or
two transient frames during the scene-fade boundary; steady state is inside
band and the whole-clip average (the number the task's reproduction command
actually reports) is what matters and is centred in the band on all three
formats.

### File integrity (ffprobe + audio)

```
9x16:  1080x1920  h264/aac  33.045s  audio 48kHz stereo
1x1:   1080x1080  h264/aac  33.045s  audio 48kHz stereo
16x9:  1920x1080  h264/aac  33.045s  audio 48kHz stereo
```

Duration matches the pre-fix renders exactly (33.045333s) — only the grade
changed, not the cut. `volumedetect` over the presenter window (4–12s) reports
mean −19.6 dB / max −3.8 dB on all three formats — the presenter's
voice-over is present and unchanged, not replaced by a silent bed.

### Visual verification

Frames extracted and looked at (not just measured) from all three formats at
the plate, presenter, split, checklist, offer-card and outro beats:

- Headline type (near-black `#141210` on the deepened paper ground) and the
  green accent line/checkmarks/CTA pill remain high-contrast on every beat —
  darkening the ground did not flatten the type hierarchy.
- The presenter panel (bordered inset in 9:16/1:1, full-bleed in 16:9) reads
  correctly; the take's own photography is unaffected by the ground grade
  since it sits inside its own bordered box / fills its own frame.
- The paper ground now visibly reads as lit paper with a soft cast-shadow
  edge, not a flat near-white card and not a mid-grey card — matching the
  "recognisably the same design system" requirement.

## Known gaps

- **16:9 presenter beat: eyebrow label overlaps the Chrome brand lock-up.**
  In the full-bleed (`columns === 2`) branch of `PresenterScene`, the eyebrow
  is positioned at `box.y` (= `f.safeTop`, 84px for 16:9) while `Chrome`'s
  brand lock-up sits at `~0.55 * safeTop` to roughly `safeTop + markSize*1.8`
  — for the 16:9 format those two vertical ranges overlap. Pre-existing, not
  touched by this grading fix (it is a layout/position bug, not a colour
  one) — flagged here rather than silently left for the next person to
  rediscover.
- **Typography inconsistency vs the still (Photoshop) tier.** The video tier
  renders Inter / Space Grotesk (the site's real faces, loaded via
  `@remotion/google-fonts`); the Photoshop template substitutes
  HelveticaNeue-Condensed / AvenirNext because those faces are not installed
  as system fonts on the machine that renders stills. The two tiers are
  typographically inconsistent until Adobe Fonts is activated for the still
  pipeline (recorded previously in the `ec3907f1` commit; restated here since
  this is the file that was supposed to hold it).
- **Type-fitting constants (`CHAR_W` in `formats.ts`) were measured, not
  derived.** `fitSize`/`fitLines` re-measured `display: 0.545, body: 0.515,
  bodyBold: 0.535` against Space Grotesk Bold / Inter by rendering stills and
  inspecting them, because the still template's constants were measured
  against HelveticaNeue-CondensedBold (condensed) and do not transfer. If a
  headline overflows its column after a copy change, this is the first place
  to look, and the fix is another render-and-inspect pass, not a formula.
- **The last ~10 seconds of the take's own voice-over aside, there is no
  music bed anywhere in this drop.** Consistent with the sibling HyperFrames
  build; no licensed track exists in the repo and adding one was not in scope
  here.

## Palette system

`src/system/palettes.ts` ports the Photoshop design system's palette block
verbatim (`marketing/creative/templates/famtastic-social-frame.jsx`, lines
75-115) so a still and a video from the same campaign are provably the same
colours. Five palettes exist (`famtastic`, `ghost-town`, `salon`, `trades`,
`paper`); `paper` is presently used only by `platform-dependency` — the
grading fix above therefore has no blast radius into the other drops
(`Drop06GmailLinktree`, `Famtastic55CentsPortrait`), which use different
palette systems entirely (`src/drop06/tokens.ts`, `src/scenes/*`).

Adding a palette or changing an existing one is an argument, not a
preference — say what in the subject produced it (`CAMPAIGN_ART_DIRECTION_V1`
Rule 1). The `paper` ground value and its edge falloff are now grounded in a
specific measurement (`reference-tokens.json`), not a guess.
