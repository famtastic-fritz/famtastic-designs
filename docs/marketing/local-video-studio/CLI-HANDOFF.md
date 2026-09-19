# Pull, inspect and prove the local video studio

Owner workstation continuation: the exact bundle has now been imported into
`codex/local-video-studio-proof` in an isolated worktree. See
[WORKSTATION-PROOF-2026-09-19.md](WORKSTATION-PROOF-2026-09-19.md) for current
measurements, repairs and review-branch state. The original remote-write failure
below describes package delivery, not the later workstation continuation.

This is the next CLI agent's execution brief. Repository: `famtastic-fritz/famtastic-designs`. Handoff branch: `feat/famtastic-local-video-studio`. Work only in a clean isolated worktree; preserve ongoing work. Read root `AGENTS.md` before changing anything.

## Assignment

Prove this implementation on Fritz's actual workstation, using the installed HyperFrames and MoneyPrinterTurbo where compatible. Be opinionated about craft and frugal with compute. Do not buy anything, silently use paid providers, publish a campaign, or call successful technical output a finished creative recreation.

The implementation is under `marketing/engine/video_studio/`; FAMtastic inputs live in `marketing/brands/famtastic/video-studio/`; the entrypoint is `scripts/famtastic-video.py`. Research is alongside this document. Canonical brand, product and campaign authority remain where the repository already places them.

## Import the committed branch safely

**Delivery status:** remote write access was denied by the available GitHub integration (HTTP403), and the shell has no GitHub login. The implementation is committed locally and supplied in `FAMtastic-Local-Video-Studio.zip` as an exact Git bundle, patch and inspectable source. It has not been pushed or deployed.

Extract the ZIP into a temporary folder. From the owner's existing repository, inspect status, fetch current public state and verify/import the bundle:

```bash
git status --short --branch
git fetch origin
git bundle verify /absolute/path/FAMtastic-Local-Video-Studio.bundle
git fetch /absolute/path/FAMtastic-Local-Video-Studio.bundle HEAD:refs/heads/proof/local-video-studio
git worktree add ../famtastic-video-proof proof/local-video-studio
cd ../famtastic-video-proof
git log -1 --oneline
```

Choose a different worktree/branch name if those already exist. Do not reset another agent's work. The bundle is based on `076261ebb8ede3d60a7eb54ada44aa449794fafa`; if verification reports that prerequisite missing, fetch the repository history containing it before retrying. Do not blindly copy over a current checkout. Inspect incoming changes from current `origin/main` and reconcile in the isolated worktree before integration.

The included patch is a secondary review/import option, not a second change to apply after importing the bundle. The supplied source tree is for inspection. Once the owner-side CLI has authenticated write access, it can push the proof branch through the normal repository procedure. This is a review handoff, not a production release.

## Prove the deterministic core first

```bash
python3 scripts/famtastic-video.py doctor
PYTHONPATH=marketing/engine/video_studio python3 -m unittest discover -s marketing/engine/video_studio/tests -v
python3 scripts/famtastic-video.py render marketing/brands/famtastic/video-studio/demo.json --scale 0.5
python3 scripts/famtastic-video.py batch marketing/brands/famtastic/video-studio/demo.json
node scripts/sync-brand-assets.cjs --check
node --test scripts/test-creator-credit.mjs
```

Use `--hyperframes /absolute/path/to/installed/hyperframes` if PATH does not find the owner's installation. If missing, the README has the exact pinned install and browser setup. Do not upgrade a working global installation merely to match the research snapshot. Record the chosen version and inspect its help.

Watch the full MP4 and inspect its contact sheet. Confirm all scenes appear, the final frame persists, type and footer remain readable, the supplied logo is intact, and each aspect ratio feels composed. Run the canonical validator on each run's `build-dna.json`. Do not delete failed receipts.

Run the same render twice. The second should return `cache_hit: true` and unchanged video hash. Change one copy field or source byte in a new campaign version; the result must not reuse the old render. A missing/tampered evidence file must invalidate reuse.

For audio proof, generate `demo-audio` into an ignored run directory, copy the demo campaign there, set `audio` to the relative WAV, and render. Listen. The demo pulse is not narration. Use a recorded script or the macOS `voice` command for narrated work; check duration before editing scene lengths.

## Connect existing MoneyPrinterTurbo

```bash
python3 scripts/famtastic-video.py doctor --mpt-root /absolute/path/MoneyPrinterTurbo --mpt-python /absolute/path/MoneyPrinterTurbo/.venv/bin/python
python3 scripts/famtastic-video.py mpt-draft /absolute/path/local-media-campaign.json --mpt-root /absolute/path/MoneyPrinterTurbo --mpt-python /absolute/path/MoneyPrinterTurbo/.venv/bin/python
```

The campaign must provide a full `script`, prepared `audio`, and local media for every scene. Supported native aspects are9:16,16:9,1:1. This montage does not preserve HyperFrames layouts or exact per-scene durations; it is explicitly a draft. Auto-upload must be disabled in that installation before execution. The wrapper refuses armed publishing and never edits the config. If installed CLI/schema is older, report the version mismatch; do not silently install/upgrade it or substitute a paid service.

## Optional local generation pilot

Follow `LOCAL-GENERATION.md`. The16GB Mac is a shared-memory machine. Do not unload or interrupt other work blindly. First prove a tiny supported native workflow through the local application, export its real API graph, inspect and bind the actual nodes, then call `generate`. On timeout call `resume` against the original receipt. Record model hashes, precision, frame size/count, elapsed time, memory pressure and rejected takes. HTTP tests in this repository do not prove GPU execution.

## Final acceptance: recreate the two recent movies

The two original files were not attached to this implementation task. Do not guess which repo videos Fritz meant. Obtain those exact two files and any approved identity/clean-plate sources when he provides them. Keep personal inputs in ignored local storage.

For each movie:

1. Probe the original; watch it with sound. Record scene boundaries, lens/framing, subject action, environment, text, timing, transitions, sound and the qualities Fritz actually values.
2. Separate reusable footage from shots requiring new generation. Preserve the person's real identity. Obtain or derive approved clean plates; do not assume a text-to-image prompt locks a face.
3. Create a campaign JSON and scene source ledger. Preserve exact wording and brand assets; recreate the concept with local compositing first. Keep soundtrack explicit.
4. Recreate a6–10-second difficult passage first. If this cannot match the visual intent, fix the technique before producing a whole movie.
5. Build the complete version, then run:

```bash
python3 scripts/famtastic-video.py compare /absolute/path/original.mp4 /absolute/path/recreation.mp4 --output artifacts/video-studio/recreation-01-review
```

6. Watch both full videos; use the sheets only as supporting evidence. Score identity, composition, motion, timing, typography, brand, audio and story0–5. Mark any hard failure: wrong identity, garbled text, omitted scene/audio, unreadable crop, unsupported claim or incorrect logo.
7. Record actual elapsed work, generation attempts, render time, accepted seconds and provider fee. Report quality/cost tradeoffs honestly. A technical pass alone does not pass this acceptance test.
8. Give Fritz both recreations and the comparison packages. Update the canonical capability record only with the evidence achieved.

Target acceptance: no hard failures; average creative score at least4/5; exact expected dimensions/duration/codec/audio; evidence hashes valid; no paid provider request; Fritz accepts the two results. If realistic subject motion cannot be achieved cheaply on the Mac, state that result and propose the smallest specific escalation with a measured estimate. Do not pretend panning a still is generated live action.

## Return these facts

Exact branch/commit; actual machine profile; installed tool versions; test result; MP4 paths/hashes; Build DNA validation; visual/audio findings; MoneyPrinter native proof status; generation pilot status; two recreation scores; remaining limitations. Never claim production deployment, publishing, or human acceptance from this handoff alone.
