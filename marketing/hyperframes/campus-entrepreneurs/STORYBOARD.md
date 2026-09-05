---
project: campus-entrepreneurs
title: Somebody Else's App
message: "You already run a business between classes — it just lives in somebody else's app instead of at an address you own."
aspect: 1080x1920
fps: 30
duration: 27.5
mode: autonomous
design: frame.md
---

# Somebody Else's App — 27.5s

Five beats. The length is set by the music bed, not the other way round: the
campaign's own attached video runs 27.6s, so the film is 27.5s and the bed fades
out over its last 1.6s.

| beat | scene | start | dur | plate | card rotation |
|---|---|---|---|---|---|
| hook | `01-hook` | 0.00 | 6.40 | `campus-dorm.mp4`, graded + silent, full bleed | — |
| desk | `02-desk` | 6.40 | 6.40 | `cm-quad.jpg` as a card below the type | `-1.4deg` |
| address | `03-address` | 12.80 | 6.00 | `cm-card.jpg` full bleed (olive clip in plate) | — |
| offer | `04-offer` | 18.80 | 6.00 | `cm-drawer.jpg` as a squared card | `0deg` |
| close | `05-close` | 24.80 | 2.70 | paper only | — |

## The cards square up

The film's move is one thing getting straight. Beat 2's photograph is laid on
the paper at `-1.4deg`, the way something lands on a desk. Beat 4's is at `0deg`
and shares the type's left edge and width exactly. Nothing else changes — same
paper, same margin, same shadow — so the only thing the eye reads between them is
that it got straight.

Rotation is static per beat and lives on a wrapper, not on the timed element, so
no seek can land the frame mid-rotation.

## Copy, and where it comes from

| beat | on screen | source |
|---|---|---|
| hook | `YOU ALREADY / RUN A / BUSINESS.` + the trades list | the package's audience line |
| desk | `IN A DM. / IN A BIO LINK. / IN SOMEBODY / ELSE'S APP.` | "stop building on rented land" |
| address | `ONE PAGE / WITH YOUR / NAME ON IT.` + support | the offer, restated to what the SKU is |
| offer | `$199` / `55 CENTS A DAY` / three included items / terms | `FAM-FOOT-199`, checked |
| close | `STOP RENTING. / START OWNING.` / `famtasticdesigns.com` | the package's own closing line |

The offer beat's three items are the SKU's `summary` restated word for word, and
the terms line names everything the price does **not** include. Four deliverables
that appear in the campaign's posting copy are absent on purpose; README →
Accuracy says which and why.

## Sound

One `<audio>` element, `#bed`, playing `assets/campus-bed.m4a` from 0.00 for the
whole 27.5s. No voiceover. No `<video>` audio — the picture plate is stripped to
silent during staging, per the framework's media contract.
