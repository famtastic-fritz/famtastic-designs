---
project: found-then-kept
title: Found, Then Kept
message: "The shop is open and search finds nothing. One product makes you readable, another keeps you that way. Neither is in the $199 bundle."
aspect: 1080x1920
fps: 30
duration: 28.0
mode: autonomous
design: frame.md
---

# Found, Then Kept

Five beats over **one photograph**, silent. A ground plane rises from the
bottom of the frame and takes the photograph's place as the film has more to
say, then drops back for the close. The camera runs one unbroken push
(1.000 -> 1.020 -> 1.045 -> 1.066 -> 1.085 -> 1.098) across all five beats, so
every cut lands on the same frame.

| beat | scene | start | dur | ground top | carries |
|---|---|---|---|---|---|
| hook | 01-hook | 0.00 | 5.60 | 1420 | the problem |
| mechanism | 02-mechanism | 5.60 | 7.00 | 900 | what local search actually reads |
| setup | 03-setup | 12.60 | 6.00 | 820 | Local SEO Setup, $299 one time |
| kept | 04-kept | 18.60 | 5.40 | 820 | Website Maintenance, $49.99/month |
| close | 05-close | 24.00 | 4.00 | 1020 | both are separate + address |

Because the film is silent the cascade IS the pacing: rows stagger at roughly
0.6 s rather than the 0.16 s a narrated film uses.

## Frame 1 - hook

- id: `01-hook`, motion: `waterfall-entry` + `viewport-change`
- The ground sits low (1420) so the whole sign bracket — scrollwork, hook and
  chain — clears it. Eyebrow `GET FOUND` with a 16px accent square beside it;
  `THE SHOP IS OPEN. / SEARCH FINDS NOTHING.` cascades at 1.05.
- The faded rectangle where the sign used to hang is visible above the type.

## Frame 2 - mechanism

- id: `02-mechanism`, motion: `waterfall-entry`
- The ground rises to 900; the bracket goes behind it and what stays visible is
  the roofline and the faded sign rectangle.
- Eyebrow `WHAT LOCAL SEARCH READS`, headline `IT READS FACTS, / NOT
  ADJECTIVES.`, then four rows a machine can actually consume: the name spelled
  one way everywhere, an address that matches the listings, hours a machine can
  parse, the services in words.
- Footnote: *"Nobody can promise you a ranking. This is the part that can be
  done."* The honest version of the beat, and the thing the viewer can check.

## Frame 3 - setup

- id: `03-setup`, motion: `spring-pop-entrance` + `waterfall-entry`
- Eyebrow `LOCAL SEO SETUP`. `$299` pops with `ONE TIME` and its accent marker,
  then three rows condensed from the campaign's own copy: structured local data
  on the site, core profiles set up and consistent, analytics verified.
- Footnote: *"A one-time setup. Not a subscription, and not part of the $199
  bundle."*

## Frame 4 - kept

- id: `04-kept`, motion: `spring-pop-entrance` + `waterfall-entry`
- Same shape, its own numbers. Eyebrow `WEBSITE MAINTENANCE`, `$49.99` with
  `A MONTH`, then updates checked, backups verified, small content touches.
- Footnote: *"Cancel any time. Its own product, and not part of the $199
  bundle."*

## Frame 5 - close

- id: `05-close`, motion: `waterfall-entry` + `spring-pop-entrance`
- The ground drops back to 1020 and the bracket returns at the top of the
  frame — the film ends where it started, on the shop with no sign.
- Eyebrow `TWO SEPARATE PRODUCTS`, `GET FOUND. / THEN STAY FOUND.`, the accent
  marker, `famtasticdesigns.com/packages` (curled, HTTP 200 on apex and www),
  and *"Neither one is included in the $199 bundle. Ask which you need first."*
