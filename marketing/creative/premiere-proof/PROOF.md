# PROOF: Premiere Pro MCP end-to-end (2026-09-04)

**Verdict: Premiere is still NOT production-proven.** The desktop app is running
and idle, the CEP bridge extension is installed and previously configured, but
every MCP call into Premiere timed out because the bridge's own panel has not
been started this session. Root cause is fully diagnosed below, with a
one-step fix that only someone with screen access to this Mac can perform.

This session did **not** fall back to ffmpeg for video assembly, per the
recipe's explicit rule. No video was produced. This document records a
disproof, not a proof — that is the honest and useful outcome the task asked
for.

---

## What was asked

Prove Premiere Pro under local MCP control end-to-end: assemble a disposable
sequence from stills, add a text overlay and a transition, export an .mp4,
then use `auto_reframe_sequence` to derive a 9:16 and a 1:1 version from the
same master timeline and export those too, ffprobing every output.

## What actually happened

### 1. Reachability checks (all failed, all timed out — not a single spurious blip)

| Call | Time (EDT) | Result |
|---|---|---|
| `mcp__premiere-pro__ping` | 21:45 | `Error: Request timed out` |
| `mcp__premiere-pro__ping` (retry) | 21:46 | `Error: Request timed out` |
| `mcp__premiere-pro__ping` (retry) | 21:47 | `Error: Request timed out` |
| `mcp__premiere-pro__get_capabilities` `{checkConnection:false}` | 21:52 | **Succeeded** — see below |
| `mcp__premiere-pro__verify_premiere_connection` | 21:55 | `Error: Request timed out` |
| `mcp__premiere-pro__ping` (after a real 45s wait) | 21:57 | `Error: Request timed out` |
| `mcp__premiere-pro__get_capabilities` `{checkConnection:true}` | 22:02 | `Error: Request timed out` |

Five independent live-connection attempts across **~18 minutes of real wall-clock
time**, spanning the app going from freshly launched to fully idle. None
answered. This rules out "still loading" as the explanation.

`get_capabilities` with `checkConnection:false` is a local, non-Premiere call
and it worked, returning:

```json
{
  "success": true,
  "runtime": {"platform":"darwin","transport":"stdio","bridgeDirectory":"/tmp/premiere-mcp-bridge"},
  "bridge": {
    "cep": {"status":"installed","path":"/Users/famtastic-fritz/Library/Application Support/Adobe/CEP/extensions/MCPBridgeCEP","support":"production bridge for Premiere Pro 2020+"},
    "uxp": {"status":"experimental","support":"not a replacement for the validated CEP bridge"}
  },
  "catalog": {"tools":283,"resources":13,"prompts":10},
  "liveConnection": {"checked":false,"status":"not_checked"},
  "safety": {"recommendedFirstCall":"verify_premiere_connection", "note":"A detected CEP installation does not prove that Premiere is running or the bridge is connected."}
}
```

That note turned out to be exactly right.

### 2. Root-cause diagnosis

Adobe Premiere Pro 2026 (build 26.3.2, confirmed via
`osascript -e 'tell application "Adobe Premiere Pro 2026" to get version'`)
was genuinely running the whole time:

```
PID 55630, ELAPSED 20:17, TIME (CPU) 0:39.36, %CPU 0.5   ← idle, not loading
```

The MCP server writes each tool call as a command file to
`/tmp/premiere-mcp-bridge/command-<uuid>.json` and waits for the CEP panel
inside Premiere to notice it, execute it, and write a response file back. Every
one of the five timed-out calls above left a command file behind with **no
matching response ever written**:

```
command-06ed8520-...json   21:45   ← my 1st ping        — never answered
command-edcb2429-...json   21:46   ← my 2nd ping        — never answered
command-c40117d8-...json   21:47   ← my 3rd ping        — never answered
command-5dd7626d-...json   21:55   ← verify_premiere_connection — never answered
command-1b9cb0bd-...json   21:57   ← 4th ping           — never answered
command-33b78f51-...json   22:02   ← get_capabilities(checkConnection:true) — never answered
```

(A seventh file, `command-51348670-...json` at 21:31, predates this session —
almost certainly the earlier timeout recorded in
`docs/marketing/ADOBE_SUITE_CONNECTION_MAP_2026-08-13.md`, from before Premiere
was even launched.)

This is conclusive: **the MCP server side is healthy and writing commands
correctly. Nothing inside Premiere is reading them.** Per the bridge's own
`KNOWN_ISSUES.md` and `QUICKSTART.md`
(`tools/adobe-automation/premiere-pro-mcp/`):

> The CEP panel must be live. If the panel is not open and started, the tools
> cannot reach Premiere even if the MCP server is configured correctly.
> Fix: Open `Window > Extensions > MCP Bridge (CEP)`, confirm the temp
> directory is `/tmp/premiere-mcp-bridge`, click `Start Bridge`.

`~/.premiere-mcp-bridge/config.json` already has the correct temp directory
saved from an earlier session (`{"tempDirectory":"/tmp/premiere-mcp-bridge"}`),
so the one remaining step is purely the `Start Bridge` button click inside the
panel — a **manual, one-time-per-launch UI action** the bridge's own code
requires (`startBridge()` is only ever invoked by a button's `onclick`, never
automatically — confirmed by reading
`~/Library/Application Support/Adobe/CEP/extensions/MCPBridgeCEP/bridge-cep.js`
lines 415–430 and 613–621).

### 3. Why this agent could not perform that one click itself

Two independent automation paths were tried, in preference order, and both are
blocked by macOS permissions that were not granted for this Claude session:

1. **`computer-use` (screen automation).** `request_access(["Adobe Premiere Pro 2026"])`
   was refused outright: *"macOS Screen Recording permission(s) are still not
   granted... these permissions need to be granted in the Claude desktop app."*
   This is a system-level TCC permission for the Claude Desktop app itself: it
   cannot be granted from inside a session, and granting it is explicitly a
   "modify system/security settings" action this agent must not take even if it
   could.
2. **AppleScript/System Events UI scripting**, as a fallback that only needs
   Accessibility (not Screen Recording): also refused —
   `osascript is not allowed assistive access. (-25211)`.
3. **Direct AppleScript to Premiere itself** (bypassing System Events
   entirely) was tried as a longshot and confirmed Premiere exposes no real
   scripting dictionary: `get name` and `get version` succeeded (basic Cocoa
   "Required Suite" properties every app gets for free — this is how
   `docs/marketing/ADOBE_SUITE_CONNECTION_MAP_2026-08-13.md`'s "build 26.3.2"
   figure was obtained), but `get version` returned instantly while
   `count windows` produced `AppleEvent timed out (-1712)` after 120s and
   `do javascript "1+1"` returned a syntax error — Premiere, unlike Photoshop,
   has no AppleScript "do javascript" bridge and no window-list support. There
   is no scripting path into Premiere's menu bar or its extension panels from
   outside CEP/UXP.

A fourth option was considered and deliberately **not** attempted: patching
`bridge-cep.js` to auto-invoke `startBridge()` on load, then quitting and
relaunching Premiere so the panel (which has `AutoVisible=true` in its
manifest) would reopen and self-start. This was rejected for two reasons:
that file lives in `tools/adobe-automation/premiere-pro-mcp/`, outside this
repository and shared by every other agent/session that uses the same bridge —
changing it is out of scope for a task confined to
`site-famtastic-designs`. And quitting an app Fritz had just deliberately
launched for this exact proof, with no way to click through any unexpected
startup dialog afterward (the same missing permissions apply), risked losing a
working, resumable state in exchange for an unverified guess about
`AutoVisible` semantics. Leaving Premiere running untouched, with the fix
documented, was judged the safer choice.

**This is a genuine hard blocker, not a workaround-avoidance.** It needs a
human with screen access to this Mac to either grant Claude's Screen Recording
permission (System Settings → Privacy & Security → Screen Recording → enable
for the Claude desktop app, then relaunch it) or, faster, to just do the fix
directly:

> In Premiere Pro 2026: **Window → Extensions → MCP Bridge (CEP)**, then click
> **Start Bridge**. Takes about five seconds. Everything else (temp directory,
> config) is already saved correctly.

### 4. What was ready and waiting, so the remaining proof is a five-minute job once unblocked

Another agent was concurrently populating exactly the assets this proof needs
(outside the `marketing/video/` exclusion zone this task respected):

- `marketing/creative/plates/plate-01-pillar-hero-16x9.jpg` through
  `plate-08-ghost-hook-square-1x1.jpg` (8 stills, mixed 16:9/9:16/1:1)
- `marketing/creative/adobe-proofs/*.png` (13 PNGs: ad variations, blog heroes,
  plus the existing `cost-is-not-the-reason-story-1080x1920.png` and
  `famtastic-story-9x16-template.psd` from step 2 of this recipe)

No placeholder stills needed to be generated via the Photoshop bridge — real
candidate source material already existed in both required locations by the
time this check ran. Nothing under `marketing/video/` was read, opened, or
modified at any point in this session.

## Exact plan for whoever runs this next (so it is a re-run, not a redesign)

Once `mcp__premiere-pro__ping` answers:

1. `create_project` → `marketing/creative/premiere-proof/`
2. `create_sequence` (1920x1080, 30fps, an installed `.sqpreset` — resolve
   the exact preset path via `get_encoder_presets`/the sequence-preset
   equivalent first; do not guess a path)
3. `import_media` the plates/PNGs above, `add_to_timeline` 3-4 of them across
   the sequence at sensible durations
4. `add_transition` (Cross Dissolve) between two adjacent clips
5. `add_text_overlay` — **note this tool requires a `.mogrt` file**, not
   freeform text; a minimal MOGRT will need to exist or be created first (this
   is a second, smaller gap worth flagging: there is no "just render this
   string as a title" tool in the current 283-tool catalog — everything text
   goes through MOGRT templates)
6. `export_sequence` with an explicit `.epr` preset path (resolved via
   `get_encoder_presets`, not guessed) → `marketing/creative/premiere-proof/master-1920x1080.mp4`
7. `auto_reframe_sequence` numerator=9 denominator=16 → new sequence →
   `export_sequence` → `.../reframe-1080x1920.mp4`
8. `auto_reframe_sequence` numerator=1 denominator=1 → new sequence →
   `export_sequence` → `.../reframe-1080x1080.mp4`
9. `ffprobe -v error -show_entries stream=width,height,codec_name,duration -show_entries format=duration,size -of json <file>`
   on all three outputs — do not report success without this.

## Marginal cost

**$0 either way.** The Creative Cloud subscription is already paid and
current; no per-render API was called, no cloud credits were spent. The cost
of this session was wall-clock time only (~24 minutes), not money. That part
of the recipe's economic claim stands regardless of today's outcome — it will
still be true whenever the bridge is actually started.

## Tool-by-tool ledger (every Premiere MCP call made this session)

| Tool | Args | Result |
|---|---|---|
| `mcp__premiere-pro__ping` | `{}` | timeout |
| `mcp__premiere-pro__ping` | `{}` | timeout |
| `mcp__premiere-pro__ping` | `{}` | timeout |
| `mcp__premiere-pro__get_capabilities` | `{checkConnection:false}` | success (see JSON above) |
| `mcp__premiere-pro__verify_premiere_connection` | `{}` | timeout |
| `mcp__premiere-pro__ping` | `{}` (after 45s real wait) | timeout |
| `mcp__premiere-pro__get_capabilities` | `{checkConnection:true}` | timeout |

No `create_project`, `create_sequence`, `import_media`, `add_to_timeline`,
`add_text_overlay`, `add_transition`, `export_sequence`, or
`auto_reframe_sequence` calls were made — there was nothing live to call them
against, and calling a mutating tool against a bridge that cannot even answer
`ping` would have produced more unanswered command files, not evidence.

## Files produced by this session

- `marketing/creative/premiere-proof/PROOF.md` (this file) — the only file
  this session wrote. No project, sequence, or export exists yet.

## What worked / what failed — summary

**Worked:**
- Confirmed Adobe Premiere Pro 2026 (build 26.3.2) is genuinely running, not
  crashed, not still loading.
- Confirmed the CEP bridge extension is correctly installed at the right path
  with the right saved config.
- Confirmed the MCP server side (`get_capabilities` with no live check) is
  healthy — the failure is 100% on the Premiere side, not the Node process.
- Root-caused the exact failure mechanism (command files with no response)
  rather than guessing.
- Found real source stills already available in both candidate locations,
  removing one entire step from whoever runs this next.

**Failed / blocked:**
- Every live Premiere reachability check timed out.
- Could not click "Start Bridge" myself: `computer-use` blocked on Screen
  Recording permission not granted to the Claude desktop app;
  AppleScript/System Events UI scripting blocked on Accessibility permission
  not granted; Premiere has no direct AppleScript scripting bridge to fall
  back to.
- Zero of the requested exports (master 16:9, 9:16 reframe, 1:1 reframe) were
  produced. `auto_reframe_sequence` — the "one master, every platform, zero
  marginal cost" multiplier — is therefore **still unverified, not disproven**.
  It could not be exercised at all.

## Known/missing Premiere MCP tool gap discovered while planning the re-run

`add_text_overlay` requires a `.mogrt` template path — there is no tool in the
283-tool catalog for writing plain freeform text/title graphics directly the
way `ps_create_text` does in the Photoshop bridge. A minimal reusable MOGRT
(or `execute_extendscript` call to build one) will be needed before step 5
above can run. This is a real gap, not a today-only blocker — flagging it now
so the next attempt doesn't rediscover it from scratch.
