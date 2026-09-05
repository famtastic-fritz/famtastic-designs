---
workflow: general-video
flow: automation
storyboard: no
message: "The platform helps people find you, but the address they find isn't yours — own the one they type."
destination: instagram-reels
aspect: 1080x1920
language: en
length: 29s
angle: argument
---

## Intent

A campaign film for FAMtastic's "Platform Dependency" drop, aimed at owner-operator
service businesses — barbers, cleaners, mobile detailers, nail techs — who take
bookings and messages inside someone else's app or a link-in-bio page. Calm and
declarative, not alarmist. It educates; it never names or attacks a platform.

The film cuts against an existing HeyGen presenter take, so it is graded to that
take's measured appearance rather than to the brand spec: a light frame, near-white
walls, mauve-grey shadows, and exactly one small olive-green accent.

## Assets

- marketing/creative/heygen/renders/take-a-platform-dependency.mp4 — the approved
  presenter take. Supplies BOTH the narration bed (whole film) and one muted
  picture inset at the turn. Not regenerated; staged verbatim.
- marketing/creative/heygen/scripts/take-a-platform-dependency.json — the take's
  locked narration script. The on-screen type is a condensed restatement of it.
- marketing/creative/plates/platform-dependency/pd-{a1,a2,b2,p}-vertical-9x16.jpg —
  the four light plates from the 21-plate campaign set; one per photographic beat.
- marketing/creative/anchors/pd-anchor-counter-16x9.png — the flagship anchor;
  the framed card in the offer beat.
- marketing/creative/heygen/reference-tokens.json — the measured grading target.

## Customizations

- The narration's own sentence boundaries drive the cut. Boundaries were measured
  off the take's RMS envelope (scripts/vo-timing.md), not estimated.
- Plates are pre-graded to the anchor's measured luminance band with local ffmpeg
  (scripts/stage-assets.sh). No CSS filter stands in for the grade.
- The paper band that carries the type grows scene by scene, and the photograph
  recedes behind it. That progression is the argument, not decoration.

## Notes

- Product claims are limited to what backend/config/famtastic-products.json says
  about FAM-FOOT-199. Business email (FAM-BUSINESS-EMAIL, $99) and maintenance
  (FAM-MAINTENANCE, $49.99/mo) are separate and are named as separate on screen.
- No invented statistics, no percentages, no competitor named.
- Every URL burned into a frame was curled first. `/web/` is the backend admin
  prefix and must never appear in a public URL.
