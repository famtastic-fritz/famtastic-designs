# HyperFrames integration research

Checked 2026-09-19 against official documentation, npm package metadata, and upstream source. Source checkout: `research/hyperframes-source`; inspected commit `2f245733950db623903ec16f3e3bbea8d2bd2f95`. This is research, not a tested installation on Fritz's machine.

## Recommendation

Make the **already-installed HyperFrames the primary local compositor**, with FFmpeg for normalization, audio finishing and output verification. FAMtastic's distinctive result should come from original scenes, typography, branded motion and reuse of owned footage; heavy AI video generation is an optional shot supplier, not the default renderer.

HyperFrames is the HeyGen-maintained project at [heygen-com/hyperframes](https://github.com/heygen-com/hyperframes), not the Python HTTP/2 framing package or an unaffiliated similarly named cloud site. It is Apache-2.0, and its official quickstart explicitly says local rendering uses no HeyGen credits. Coding agents, hosted voices/avatars and generated media may cost separately. Local CPU/GPU time, electricity and disk space remain real costs. [License](https://github.com/heygen-com/hyperframes/blob/main/LICENSE), [official quickstart](https://hyperframes.heygen.com/quickstart).

The repository and npm reported CLI version **0.8.50** on inspection. Preserve an already-working installed version until feature compatibility is checked. Node >=22 and FFmpeg are documented prerequisites; Chromium is used for frame capture. Prefer its pinned browser for consistent output. [CLI package](https://github.com/heygen-com/hyperframes/blob/main/packages/cli/package.json), [browser/doctor contract](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-cli/references/doctor-browser.md).

## Exact CLI contract

Use the installed binary resolved explicitly from project `node_modules/.bin/hyperframes`, configured executable, or PATH. Do not run unpinned `npx` in the normal render path; this can download a different version. Initial dependency setup may install a selected version with a lockfile.

```text
hyperframes --version
hyperframes doctor --json
hyperframes check /absolute/path/to/project
hyperframes snapshot /absolute/path/to/project --at 0,2,5
hyperframes render /absolute/path/to/project --output /absolute/path/to/output.mp4 --fps 30 --quality draft
hyperframes preview /absolute/path/to/project --background --port 3017
```

`check` already includes lint and then audits runtime, network failures, layout, motion and contrast. A lint error can prevent the later audits from running. `doctor --json` always returns exit 0: inspect its `ok` field and individual checks. Local rendering is CLI-first; no hosted API is needed. `render` does not expose the ordinary `--json` response that many other commands support. Capture logs, exit status and FFprobe evidence. Exact installed `--help` takes precedence over current upstream docs. [CLI guide](https://hyperframes.heygen.com/developers/cli), [render flags](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-cli/references/preview-render.md).

Output controls include `--quality draft|looks|delivery`, `--format mp4|webm|mov|gif|png-sequence|hls`, `--workers`, and optional hardware encoding `--gpu`. For local proof use 30fps and moderate dimensions; benchmark actual machine before selecting concurrency. Heavy glow/shader scenes can be slow on software graphics. Do not imply fast rendering without a measured sample.

Set these environment values on child processes:

```text
HYPERFRAMES_NO_TELEMETRY=1
HYPERFRAMES_NO_UPDATE_CHECK=1
HYPERFRAMES_NO_AUTO_INSTALL=1
```

They disable telemetry, npm update checks, and automatic background installs respectively; this helps a pinned offline workflow. Avoid `cloud render`, `lambda`, `cloudrun`, hosted TTS/avatar commands, `publish`, and feedback submission in the no-credit path. No need to sign in. [Update checker](https://github.com/heygen-com/hyperframes/blob/main/packages/cli/src/utils/updateCheck.ts), [auto-update behavior](https://github.com/heygen-com/hyperframes/blob/main/packages/cli/src/utils/autoUpdate.ts), [telemetry reference](https://hyperframes.heygen.com/packages/cli).

## Composition contract

Generate a standalone `index.html`, not a template-wrapped root:

```html
<div id="root" data-composition-id="famtastic"
     data-width="1280" data-height="720" data-duration="12" data-fps="30">
  <section id="hook" class="clip" data-start="0" data-duration="4" data-track-index="1">
    <h1 id="hook-title">Your hustle deserves a home.</h1>
  </section>
</div>
```

Give the root CSS `position:relative;width:100%;height:100%;overflow:hidden`; local clips supply positioning. Root duration and dimensions are read at compile time. Generate their correct literal values for every format/variant; changing them later from a script or variable does not change output length. Visibility windows are half-open: `[start,start+duration)`. `data-track-index` is a Studio display lane, not visual z-order; use CSS stacking deliberately. [Attributes contract](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-core/references/data-attributes.md).

### Recommended motion driver: native WAAPI

Native Web Animations avoids any GSAP CDN or dependency. Build animations synchronously with finite duration/iterations, `fill:'both'`, and pause them. HyperFrames seeks their `currentTime` to composition time in milliseconds. Encode scene timing in `delay` rather than assuming automatic local scene time.

```js
const animation = document.getElementById('hook-title').animate(
  [{opacity:0,transform:'translateY(32px)'},
   {opacity:1,transform:'translateY(0)'}],
  {duration:650,delay:150,iterations:1,fill:'both',
   easing:'cubic-bezier(.2,0,0,1)'}
);
animation.pause();
```

Do not depend on animation completion callbacks, timers or clocks. Every output frame must be reconstructible from its requested time. Seed procedural randomness once. Fonts, images, CSS and scripts must be local before rendering. [Official WAAPI adapter](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-animation/adapters/waapi.md), [determinism rules](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-core/references/determinism-rules.md).

### GSAP and 3D when needed

For richer sequencing, vendor a pinned GSAP bundle and register one paused timeline at `window.__timelines['famtastic']` matching the composition ID. Construct all tweens before registration. Animate children rather than visibility/display of framework-owned timed clips. No infinitely repeating timeline.

For custom Canvas/Three.js, upstream's GPU adapters dispatch `hf-seek` with `event.detail.time`. Render state as a pure function of that time; do not run a wall-clock animation loop. Async render work must synchronously register its promise with `event.detail.waitUntil(...)` when available. Set explicit root duration. Bundle Three.js/models/textures locally. [GSAP integration](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-animation/adapters/gsap.md), [Three.js adapter](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-animation/adapters/three.md), [seek event source](https://github.com/heygen-com/hyperframes/blob/main/packages/core/src/runtime/adapters/seek-dispatch.ts).

### Media and audio

Every audio/video element must have a unique `id`. A video uses `muted playsinline`; audio is a separate `<audio>` with matching source and timing when desired. Set `data-media-start` for source trim offset, `data-duration` for timeline length, and `data-volume` for gain. Avoid a timed video inside a plain timed wrapper: this causes inconsistent source frames/visibility and is linted. Sub-compositions are a separate supported mechanism. Do not manually play/seek media inside the composition; HyperFrames owns media. [Media contract](https://github.com/heygen-com/hyperframes/blob/main/skills/hyperframes-core/references/variables-and-media.md).

## Architecture to implement

1. **Canonical campaign manifest:** scene intent, exact copy, timings, aspect ratio, local source paths, asset rights/source, approved price terms, narration/subtitles, seed and template version.
2. **Compile:** validate manifest, copy only referenced media/fonts into a self-contained output project, generate HTML/CSS/WAAPI and a local manifest receipt. No remote URLs in a finished offline project.
3. **Render adapter:** child process argument arrays, explicit installed executable, no shell interpolation; run HyperFrames check, render, then FFprobe. Record command, version, elapsed wall time, output hash, dimensions, duration and status.
4. **Reusable scenes:** confident typography, dramatic lime/green light, stardust/energy elements, deep layered composition, source-preserving portrait motion, exact price/URL end cards. Boldness is designed, not dependent on rerolling a video model.
5. **Cost policy:** local composition has zero service-credit spend. Existing images/footage come first. Optional local generation gets a hardware/model/license benchmark. No automatic paid fallback. Re-render only failed/changed scenes when caching can preserve exact outputs.
6. **Proof gate:** render a real MP4 and inspect representative frames. Then validate the user's two supplied references with a side-by-side rubric for content, pacing, shot structure, identity, typography, audio, aspect ratio and cost. Until source videos are supplied the recreation benchmark remains pending.

This integration is feasible on a normal computer for compositing and existing-footage motion. It does not mean HyperFrames generates photorealistic human movement or voice by itself; those assets must be recorded, supplied, generated by another local model, or acquired through a separately approved service.
