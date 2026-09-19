---
workflow: general-video
flow: automation
storyboard: yes
message: "Your vision. Your hustle. Your grind. The business you’re trying to build."
aspect: 1080x1920
language: en
length: 35.583333s
---

# Walking continuation

## Intent

Make a presenter-led continuation from the owner's supplied 30-second film. Keep
three passages paired to their original performance and original AAC dialogue:
the opening (source 0.00–5.92s), the website-gesture passage (7.28–12.40s), and
the close (26.24–29.70s). A separate, locally synthesized narrator carries the
owner's exact 62-word bridge between those passages. The bridge is voiceover; it
does not imitate the on-screen performer's voice or imply new lip synchronization.

The new film uses the supplied transparent performance matte over deterministic
city, website, plan, and home geometry. Keep the character visible through most
of the bridge, but use wide framing and wide source inserts where original mouth
movement does not match the new voiceover. The existing source offers brief
weight shifts and steps, not a sustained walk. Do not loop or relabel that as a
new walking performance.

## Source and limits

- Owner-supplied input: `FAMtastic-Designs-30sec-FINAL-COPY-FIXED.mp4`,
  SHA-256 `95ae3ca64a6eaee00c772ddd70063c210b55dff03632b9690ec645ffa2bae49f`.
- Source picture is H.264, 480×854, 24 fps, 30.041667s; the output canvas is
  portrait 1080×1920. Scaling the source does not create detail absent from it.
- The reused Vision matte is 480×854 VP9 alpha and contains an Opus stream in
  its source container. `build_project.py` strips all audio from the matte before
  staging; the authored AAC master is the only final audio track.
- Bridge text is copied from the supplied `What's-the-catch` user script. Local
  Kokoro `am_michael` audio is a separate narrator, not an owner-voice clone.
- No pricing, booking, customer outcome, or other new product claim is added.

## Acceptance boundary

This folder stages a review draft for the existing local HyperFrames renderer.
Rendering, visual acceptance, merge, publication, email, and deployment are
separate actions. The source film's resolution and the matte's extraction limits
remain visible in the evidence rather than being described as restored footage.

Final visual repair: bridge inserts use unoccluded source ranges 4.0–12.856, 4.0–8.984, and 8.0–13.104 seconds. These are reused movements under an explicitly labeled separate voiceover. No rectangular head crop or price-card-occluded torso is used. The opening is framed lower to clear the headline.
