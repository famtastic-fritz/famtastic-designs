# Lite Image Story — 2026-08-20

This is an isolated, static visual-story experiment. It does not alter the live **And If It Is?** experience and is not deployed.

## Contents

- `assets/00-lite-image-canon.jpg` — untouched source copy of the Gemini Flash Lite reference recreation.
- `assets/01-the-pull.jpg` through `04-the-hand-off.jpg` — four original supporting images generated with the source only as art-direction reference.
- `prompts.json` — detailed image contracts and per-image prompts.
- `generate-supporting-images.mjs` — reproducible Gemini Image API generator; it reads the key only from the local Keychain.
- `evidence/generation-receipt.json` — non-secret timing, model, hashes, output dimensions, and estimated image cost.
- `index.html`, `styles.css`, `app.js` — the static responsive story page.

## Running locally

Serve the campaign folder (two levels above this experiment) with any static web server, then open `experiments/lite-image-story-20260820/index.html`. The browser will then load the shared local font assets from `site/assets/fonts/`.

## Boundaries

All scenes are original illustrative photography. The work deliberately avoids official marks, logos, mascots, official text, and claims of affiliation. It is an unofficial fan-culture concept, not an institutional site.
