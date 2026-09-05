# Local texture library

`texture-library.json` is the manifest for five deterministic SVG texture
sources in `assets/`. Each SVG is editable, scalable, and local; no image model
or provider was used to make it.

These are quiet composition ingredients, not illustrations. Put them behind
Photoshop-, Remotion-, or HyperFrames-set type at low opacity, inspect at the
actual target size, and use a subject-specific plate when the content needs to
make an argument. The palette is selected by the subject, not by habit.

## What is deliberately absent

- No text, CTA, URL, logo, people, product promise, or generated claim.
- No universal black-and-lime default: `famtastic-grid-quiet` is only for
  FAMtastic offer/system surfaces.
- No paid or provider-generated texture receipt. A future external candidate is
  a separate asset-graph experiment, not a replacement for these sources.

## Validate

```bash
node validate-texture-library.mjs
```

The command checks every manifest entry, verifies the corresponding SVG exists,
is text-free, and has a matching `viewBox`. It makes no network call.
