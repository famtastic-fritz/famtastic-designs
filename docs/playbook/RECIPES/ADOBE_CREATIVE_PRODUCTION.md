# RECIPE: Adobe Creative Production

**Outcome**: Every branded still, video, and customer document is produced with the Creative Cloud subscription FAMtastic already pays for — not with crippled fallback tooling.
**Trigger**: Any campaign still, story frame, blog hero, social video, proposal, or lead magnet.
**Owner**: whichever agent is producing the asset · **Gates**: none for producing an artifact; the existing creative-proof reviewer gate still applies before anything reaches a customer or a channel.
**Grounded in**: `marketing/providers.json` (`adobe_photoshop_desktop_mcp`, `adobe_premiere_desktop_mcp`, `adobe_desktop_suite_unbridged`, `adobe_fonts_typekit`), `docs/marketing/ADOBE_SUITE_CONNECTION_MAP_2026-08-13.md`, `tools/adobe-automation/`, Design DNA v1.

---

## Why this recipe exists

On 2026-09-04 a campaign video was rendered for drop-06 with an `ffmpeg` build
that has neither `drawtext` nor `subtitles`. The result had no wordmark, no
type, no brand colour, and was rejected by the owner as unusable.

At that same moment, on the same machine:

- Adobe Photoshop 2026 was **running**, with a working MCP bridge attached.
- Eighteen Adobe applications were installed, including After Effects, Audition,
  Media Encoder, Illustrator and InDesign.
- The Creative Cloud subscription was paid and current.

The tool that could set type was live and idle while the tool that cannot set
type was doing the work. The cause was not missing capability — it was that
`marketing/providers.json`, the file every agent is required to read first,
listed seven Adobe entries and marked **every one of them pending**. An agent
consulting the registry correctly concluded Adobe was unavailable. The registry
was wrong; it described only the Adobe *cloud APIs* and never mentioned the
*desktop apps under local MCP control*, which need no entitlement at all.

**The rule that follows: a capability that is not in the first file agents read
does not exist, no matter how installed it is.**

---

## The two Adobe surfaces — do not confuse them

| Surface | Entitlement | Status | Use it for |
|---|---|---|---|
| **Adobe desktop apps via local MCP** | none — the paid subscription is enough | Photoshop **proven**; Premiere bridge present | all day-to-day creative production |
| **Adobe cloud APIs** (Firefly Services, Photoshop API, PDF Services, Frame.io, CC Libraries, Express Embed) | client credentials / licence / Adobe approval | genuinely pending | server-side automation, later |

A `*_pending` status on a cloud API says nothing about the desktop app of the
same name.

---

## Steps

| # | Step | Definition of done | Evidence | Status |
|---|------|--------------------|----------|--------|
| 1 | Register the desktop surface in `providers.json` | Working Adobe capabilities appear in the first file agents read, with access path, preconditions and known bugs | 4 entries added, `adobe_photoshop_api` annotated to redirect to the desktop path | ✅ DONE 2026-09-04 |
| 2 | Prove Photoshop end to end | A Design-DNA-compliant asset built and exported entirely through the bridge | `marketing/creative/adobe-proofs/cost-is-not-the-reason-story-1080x1920.png` (**copy superseded 2026-09-04 — it says the bundle is "maintained", which the catalog contradicts; kept only as the first end-to-end proof, never reuse its copy**) + reusable `famtastic-story-9x16-template.psd` | ✅ DONE 2026-09-04 |
| 3 | Install the brand faces | Inter and Space Grotesk resolve inside Photoshop | — | ☐ BLOCKED — see *Known gaps* |
| 4 | Prove Premiere end to end | `ping` answers, then a disposable sequence assembles and exports | `marketing/creative/premiere-proof/PROOF.md` | ☐ BLOCKED 2026-09-04 — app running, bridge panel not started, see *Known gaps* |
| 5 | Media Encoder watch folders | One master render fans out to every social aspect ratio with no bridge work | — | ☐ cheapest remaining win |
| 6 | Audition pass on machine-assembled audio | Voiceover normalised, bed mixed, loudness to platform spec | — | ☐ |
| 7 | Retire ffmpeg text rendering | No script in the repo burns type with `drawtext` | — | ☐ audit outstanding |

---

## How to actually do it

### A still with type on it — Photoshop

Preconditions: **Adobe Photoshop 2026 must be running.** The bridge drives the
live application; it cannot launch it.

```
ps_new_document      1080x1920 (9:16) | 1080x1080 (1:1) | 1920x1080 (16:9)
ps_fill_layer        hex #070907                        ← Design DNA ground
ps_create_text       content, x, y, fontSize, hex #7cfc00 / #ffffff
ps_create_shape      lime rules and dividers
ps_export            png
ps_save_document     psd, when the layout should be reusable
```

For anything with more than a handful of layers, compose the whole frame in a
single `ps_run_script` call instead of a dozen tool calls — it is one round trip
and the layout stays readable as code. `marketing/creative/adobe-proofs/famtastic-story-9x16-template.psd`
is the worked example.

**Hard-won details**

- **ASCII only.** A non-ASCII character in `ps_create_text` either fails with
  `ExtendScript: Required value is missing` or silently renders mojibake — `55¢`
  came back as `55¬¢`. Write `55 cents`. This is a UTF-8/MacRoman mismatch in
  the bridge, not an ExtendScript limit.
- The parameter is **`fontFamily`**, and it wants a PostScript name
  (`HelveticaNeue-CondensedBold`), not a family name. `font` is silently ignored.
- A failed `ps_create_text` still leaves an empty text layer behind. Clean up
  before exporting.
- `textItem.position` sets the **baseline** of point text, not its top edge.
- 836 fonts are installed; enumerate them with `ps_run_script` over `app.fonts`.

### A video — Premiere Pro

Preconditions: **Adobe Premiere Pro 2026 must be running.** `mcp__premiere-pro__ping`
times out when it is closed; that is the app being shut, not a broken bridge.

Use `add_text_overlay`, `add_transition`, `apply_effect`, `auto_reframe_sequence`
for platform aspect ratios, and `add_to_render_queue` / `export_sequence` to
render. Generated plates (Gemini, HeyGen presenter takes) are the *footage*;
Premiere is what makes them a branded piece.

### Reliability: a GUI app is never the backbone of a pipeline

Owner note, 2026-09-04: **Premiere has been known to freeze.**

That is not a quirk to work around, it is a constraint that decides the
architecture. Both Adobe bridges drive a *live GUI application*. They inherit
every failure mode of that application: it can hang, block on a modal, lose the
project, or sit unresponsive while an agent waits forever on a tool call. A
production pipeline that must run on a schedule cannot have a GUI in its
critical path.

So the split is:

| Need | Use | Why |
|---|---|---|
| **Repeatable, scheduled, or batch video** | **Remotion** (React → MP4, installed locally, free, headless) | Deterministic, no GUI, survives being run unattended, reproducible from source |
| **Programmatic web-native composition** | Remotion, HyperFrames MCP, HTML → Express export | Same reason; the composition is code, so it is diffable and re-renderable |
| **One-off hero pieces, finishing, colour, audio** | Premiere / After Effects / Audition | Best quality, human in the loop, freeze is survivable because someone is watching |
| **Stills** | Photoshop bridge | Fast and proven, but still GUI-bound — same timeout rule applies |

**Rules that follow:**

- Never let an agent block indefinitely on an Adobe MCP call. Set a timeout,
  retry a bounded number of times, then **report the failure and stop** — do not
  silently fall back to ffmpeg and ship unbranded output. That silent fallback is
  exactly what produced the rejected drop-06 video.
- A frozen Premiere is a *degraded* result, not a failed one: the work is not
  wrong, it is unmeasured. Report `BLOCKED: Premiere unresponsive`, never
  `render failed`. (Measurement Discipline, FAMtastic CLAUDE.md.)
- If a video must ship on a deadline and the GUI path is not answering, the
  fallback is **Remotion**, not ffmpeg text burning.
- Prefer the web/programmatic build whenever the same asset will be produced more
  than once. Adobe earns its place on the pieces that are made once and matter.

### Never again

- Do not burn text with `ffmpeg drawtext`. The local build does not have it, and
  Photoshop does the job better anyway.
- Do not assemble a customer- or channel-facing video with `ffmpeg concat` when
  Premiere is installed.
- Do not conclude "Adobe is unavailable" from a `*_pending` cloud-API row.

---

## Known gaps

- **Brand typography is not installed.** The site loads **Inter** (400/500/600/700)
  and **Space Grotesk** (500/600/700) from Google Fonts. Neither exists on this
  machine, so Photoshop output currently falls back to `HelveticaNeue-CondensedBold`
  and `AvenirNext`. Close enough to look deliberate; not actually on brand.
  - `SpaceGrotesk-{Regular,SemiBold,Bold}.woff2` already exist at
    `famtastic-hosting/public/fonts/` and need only a `woff2 → OTF` conversion
    plus a copy into `~/Library/Fonts`. No download needed for the files
    themselves; `fontTools` + `brotli` are not installed, so the converter is.
  - Inter is available on **Adobe Fonts**, included in the subscription.
- **Adobe Fonts has never been used.** `~/Library/Application Support/Adobe/CoreSync/plugins/livetype/.r`
  is empty — zero fonts activated on a paid plan. Activating Inter there is the
  single cleanest fix for the gap above, and costs nothing.
- **Premiere is still unproven, and the specific reason is now known.** On
  2026-09-04, with Premiere Pro 2026 (build 26.3.2) launched and idle for over
  20 minutes, every one of `ping`, `verify_premiere_connection`, and
  `get_capabilities({checkConnection:true})` timed out — five attempts across
  ~18 minutes. The MCP server side is healthy (`get_capabilities({checkConnection:false})`
  answers instantly); the failure is entirely on the Premiere side. Diagnosis:
  the server writes each call as a command file to `/tmp/premiere-mcp-bridge/`
  and waits for the CEP panel inside Premiere to answer it — every command file
  from this session sat there with no response ever written, because **the CEP
  panel's `Start Bridge` button had not been clicked this launch.** This is a
  manual, one-time-per-launch UI step
  (`Window > Extensions > MCP Bridge (CEP)` → `Start Bridge`) that the bridge's
  own code requires; nothing auto-starts it. Full ledger, diagnostic evidence,
  and the exact five-minute plan for whoever runs this next:
  `marketing/creative/premiere-proof/PROOF.md`.
  - Two automated ways to click that button for the operator were tried and
    both are blocked on this machine: `computer-use` screen automation needs
    macOS Screen Recording permission for the Claude desktop app (not granted,
    and not grantable by an agent — that's a system-settings change); AppleScript
    `System Events` UI scripting needs Accessibility permission (also not
    granted: `osascript is not allowed assistive access (-25211)`). Direct
    AppleScript to Premiere itself has no scripting dictionary beyond `name`/
    `version` — no `do javascript`, no window list — so there is no scriptable
    back door the way there might be for a classic Adobe app.
  - Real source stills for the eventual proof already exist and do not need to
    be generated: `marketing/creative/plates/plate-01..08*.jpg` and 13 PNGs
    under `marketing/creative/adobe-proofs/`.
  - Second gap found while planning the re-run: `add_text_overlay` requires a
    `.mogrt` template path. There is no tool in the current 283-tool catalog
    for writing freeform title text directly the way `ps_create_text` does for
    Photoshop — a minimal reusable MOGRT needs to exist first.
- **Sixteen of eighteen installed Adobe apps have no bridge.** After Effects and
  Illustrator are ExtendScript-scriptable today. Media Encoder needs no bridge at
  all — watch folders would fan one render out to every social spec.

---

## Failure paths

| Where | If it fails | Fallback |
|---|---|---|
| Photoshop bridge returns `No such element` | No document is open | `ps_new_document` first |
| `ps_create_text` returns `Required value is missing` | Non-ASCII in `content` | Replace with ASCII; delete the empty layer left behind |
| `mcp__premiere-pro__ping` times out | Premiere is not running, **or** it is running but the CEP panel's `Start Bridge` was never clicked this launch (check `/tmp/premiere-mcp-bridge/` for command files with no matching response) | Launch Premiere if closed; if already running, open `Window > Extensions > MCP Bridge (CEP)` and click `Start Bridge` — do not fall back to ffmpeg for branded output |
| Brand font missing | Photoshop substitutes silently | Use the documented stand-ins and record the substitution in the Build DNA record |

## Approval gates

Producing an artifact needs no gate. The existing creative-proof reviewer gate
still governs anything that reaches a customer, a campaign, or a channel, and
the Build DNA record must journal the real provider — `adobe_photoshop_desktop_mcp`,
not a generic "local assembly".

## Change log

- 2026-09-04 — Created after a machine-assembled drop-06 video was rejected for
  having no branding, while Photoshop sat running and idle. Root cause was
  `providers.json` describing only the pending Adobe cloud APIs. Registry
  corrected, Photoshop proven end to end, ASCII and `fontFamily` bugs recorded,
  brand-font and Adobe-Fonts gaps opened.
- 2026-09-04 — Attempted the step 4 Premiere proof with Premiere Pro 2026
  launched and confirmed idle for 20+ minutes. Every live-connection call still
  timed out. Root-caused conclusively (not just observed) to the CEP panel's
  `Start Bridge` never having been clicked this launch — the MCP server side is
  healthy, command files queue correctly, nothing inside Premiere answers them.
  Both available ways to click that button on the operator's behalf
  (`computer-use`, AppleScript/System Events) are blocked by macOS permissions
  not granted to this Claude session; a direct-AppleScript-to-Premiere longshot
  confirmed Premiere has no scripting dictionary to fall back to. No video was
  produced and ffmpeg was correctly not used as a substitute. Full ledger:
  `marketing/creative/premiere-proof/PROOF.md`. Step 4 remains open, now with an
  exact five-minute human fix and an exact five-minute re-run plan once
  unblocked, plus a newly discovered `add_text_overlay`/MOGRT gap.
