---
workflow: general-video
flow: automation
storyboard: no
message: "Outside one booking app you are a ghost — and being findable is just a surface with your name on it."
destination: instagram-reels
aspect: 1080x1920
language: en
length: 44s (master) + 15s (short cut)
angle: argument
---

## Intent

Two campaign films for `marketing/campaigns/ghost-town-ep1/` — "Ghost Town,
Episode 1: The Hair Salon Ghosts" — aimed at independent hair stylists, braiders,
barbers, nail techs and estheticians who are discoverable only inside a booking
marketplace or an Instagram DM.

The campaign's own manifest is explicit that this is **not** a cost argument. It
is the sequel to `cost-is-not-the-reason`, and it deliberately makes the opposite
case: even a stylist who is perfectly happy with the app's fees is still
invisible to everyone who is not already looking for her by name. The films must
never drift back into a fees argument, and they never name or attack a
marketplace — the campaign's production notes forbid the fabricated
"X% of businesses" statistic that the framing invites.

Tone, from `video-scripts/EP1_FULL_SCRIPT.md`: "Direct, a little wry, not doom-y.
This is a fixable problem, not a scary one."

## Deliverables

1. **The Sign That Isn't There** — 44s 9:16 master cut, all six beats of the
   script (hook, problem, reframe, solution, price, CTA).
2. **The DM Trap** — 15s 9:16 short cut, Short Cut 2 in the script.

## Palette

This film uses the campaign's own `ghost-town` palette (`#17120d` ground,
`#d9a441` accent), not the HeyGen anchor grade that governs
`marketing/hyperframes/platform-dependency`. That is a deliberate divergence and
it is argued in README.md → "Why this film is not graded to the anchor".

## Assets — reused, nothing generated

- `marketing/creative/plates/platform-dependency/pd-{a2,b1,b2,c1,c2,p}-vertical-9x16.jpg`
  — six photographs of blank surfaces, already on disk. Re-graded locally with
  ffmpeg into the ghost-town palette by `scripts/stage-assets.sh`. No provider
  call, no image generation, $0.
- Nothing else. The campaign's own `images/` directory is empty, and the one
  plate the plate library labels for this campaign
  (`plate-08-ghost-hook-square-1x1.jpg`) is unusable here — see README.

## Deliberately not used

- `marketing/campaigns/cost-is-not-the-reason/images/01-hair-beauty-booksy-escape-ad.jpg`
  and `04-nail-salon-...` — finished ads with baked-in type, including an
  unverifiable "20% APP FEES" figure.
- Any music bed. See README → Limitations.

## Accuracy gates

Every product claim traces to `backend/config/famtastic-products.json`. Two
claims that appear in the campaign's own source copy are **excluded** because the
backend does not support them; both are recorded in README → Accuracy.
