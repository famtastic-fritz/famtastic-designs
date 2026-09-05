# Campaign assets — I've Managed Fine

Stills for the six-objection campaign. Every file here is **extracted from a
delivered film**, not generated separately, so the still and the video a
prospect sees are the same frame of the same argument.

## Why extracted rather than generated

Two reasons, both practical:

1. **Provenance.** A still made independently of the film drifts — different
   crop, different grade, different line break — and then the feed post and the
   video contradict each other. Extracting from the render makes drift
   impossible.
2. **Cost.** $0. No image model call.

## Files

| File | From | Frame | What it shows |
|---|---|---|---|
| `01-thirty-years-objection-9x16.jpg` | `f1-thirty-years` | 3.4s | "I'VE MANAGED FINE FOR THIRTY YEARS." over the empty sign bracket |
| `02-know-where-objection-9x16.jpg` | `f2-know-where` | 3.4s | "MY CUSTOMERS KNOW WHERE I AM." over the A-frame board at night |
| `03-not-technical-objection-9x16.jpg` | `f3-not-technical` | 3.0s | "I'M NOT TECHNICAL." on a paper card over the card-file drawer |
| `04-got-burned-objection-9x16.jpg` | `f4-got-burned` | 3.4s | "I TRIED BEFORE. I GOT BURNED." over the open cash drawer |
| `05-too-expensive-offer-9x16.jpg` | `f5-too-expensive` | 9.5s | The $199 offer card — price, 55 cents a day, the three inclusions |
| `06-retiring-objection-9x16.jpg` | `f6-retiring` | 3.4s | "I'M TOO CLOSE TO RETIRING." over the brass letter slot |

All are 1080 × 1920.

## How to regenerate

Render the films first (`marketing/hyperframes/ive-managed-fine/`), then:

```bash
marketing/hyperframes/ive-managed-fine/scripts/export-stills.sh
```

Idempotent, local ffmpeg only, $0.

## Rules that apply to these as much as to the films

No statistic, no percentage, no named competitor, no ranking promise, no
delivery-time promise. Any frame naming `$199` also carries the renewal line
`First year $199. Then $9.99 a month, plus the domain.` — that is BRAND.md's
required disclosure and it is baked into the frame, so a still cannot be
circulated without it.
