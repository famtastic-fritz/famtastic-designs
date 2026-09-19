# Local Video Studio workstation core review — 2026-09-19

Branch `codex/local-video-studio-proof`, imported commit
`f24eec09d3a08a667a80463670f188f49f641f5d` (base
`076261ebb8ede3d60a7eb54ada44aa449794fafa`). This report covers the bounded
evidence/verifier lane only. It does not claim a workstation render, visual
acceptance, MoneyPrinterTurbo execution, ComfyUI execution, publication, or
release.

## Checks

- Pre-change baseline: the full suite ran 66 tests, with 61 passing and 5 macOS path-alias failures. Output retained in ignored `artifacts/video-studio/workstation-proof/core-review/unittest.log`.
- Full-suite checkpoint before the later MPT compatibility changes: `PYTHONPATH=marketing/engine/video_studio python3 -m unittest discover -s marketing/engine/video_studio/tests -v` ran 74 tests and passed all 74. The complete output is retained in ignored
  `artifacts/video-studio/workstation-proof/core-review/unittest-final.log`.
- Verifier-focused suite: `PYTHONPATH=marketing/engine/video_studio python3 -m unittest marketing.engine.video_studio.tests.test_verify -v` passed all 10 tests, including real FFmpeg/FFprobe media checks. Output:
  `artifacts/video-studio/workstation-proof/core-review/verify-focused.log`.
- Cache and campaign focused checks passed 13/13; HyperFrames passed 10/10; MoneyPrinter adapter tests passed 11/11. Logs are `cache-focused.log`, `hyperframes-focused.log`, and `moneyprinter-focused.log` in the same ignored directory.
- Canonical brand asset integrity: `node scripts/sync-brand-assets.cjs --check` passed (`PASS: shared brand assets identical`).
- Creator-credit contract: `node --test scripts/test-creator-credit.mjs` passed all 5 tests.
- The full suite's Build DNA evidence test also passed its canonical validator invocation.

The initial failures came from macOS temp directories whose visible `/var/folders/...`
path resolves to `/private/var/folders/...`. Test expectations now compare
canonical paths, and the cache helper canonicalizes its repository-root argument.
That full-suite checkpoint passes on this host. Later MPT adapter changes were verified separately below; the parent task is running the final integrated suite after all lanes stabilize.

## Core findings and changes

The verifier previously treated an explicit stream duration of `0` as missing
and substituted the container duration. That could pass duration checks for a
video stream with no positive duration. It now falls back only when stream
duration is unavailable (`None`), so an explicit zero fails.

Contact-sheet generation previously replaced an existing destination on
success. Since sheets are retained review evidence, it now refuses an existing
destination and installs the completed temporary image with an exclusive hard
link. Regression tests cover both behaviors in
`marketing/engine/video_studio/tests/test_verify.py`.

The evidence ledger review found its existing tested protections intact:
repository-contained resolved paths (including symlinks), SHA-256 artifact
checks, immutable campaign snapshots, append-only attempts, preservation of
failed attempts, and honest unknown external-provider fees. The evidence tests
passed in the complete suite.

Cache reuse now requires canonical Build DNA with an accepted terminal
completion (`gated` or `passed`), `integrity=passed`, at least one artifact,
and nonempty stages whose results all passed. It rechecks every retained
artifact hash and resolves the root before containment checks. Regression tests
show that `in_progress`, missing/failed integrity, failed/running stages,
changed artifacts, malformed ledgers, and path escapes cannot produce a cache
hit; valid gated evidence remains reusable.

## Installed-tool compatibility review

The installed MoneyPrinterTurbo source was reviewed without reading its
configuration or launching a job. Its older single-task CLI accepts local
materials, custom audio, a supplied script, a UUID `--task-id`, and the fixed
subtitle/music controls used by the adapter. It returns that task ID with the
task result, and task outputs are stored beneath `storage/tasks/<task-id>/`.
The adapter binds each output to the UUID passed or returned for that run.
Its result parser accepts notices before the final structured task or batch JSON
while still requiring the expected result shape; the receipt records only the
task IDs whose output paths passed validation.
Comma-containing local material paths are copied to private, comma-free names
because the CLI parses `--video-materials` as a comma-separated string.

The installed task flow enters cross-post scheduling during the `video` stage;
`--stop-at video` is therefore not itself the publishing gate. The adapter
refuses `upload_post_auto_upload=true`, and the installed scheduler cross-posts
only when both the posting service is configured and auto-upload is enabled.
No config or secret values were read or emitted.

My first review found that a symlink at the task-ID directory could make an
external MP4 appear contained under the resolved directory. The adapter now
rejects symlinked task storage and task-ID directories and checks canonical
containment beneath the installation's task root. The regression test
`test_task_directory_symlink_cannot_bind_an_external_video` covers that escape.
The current MoneyPrinter adapter suite passed 15/15; its complete log is
`artifacts/video-studio/workstation-proof/core-review/moneyprinter-task-binding-final.log`.
This was a code and mocked-test review only; no MoneyPrinter render was run.

HyperFrames 0.8.29 `render --help` advertises `draft`, `standard`, and `high`
quality. The quality resolver returned `draft` unchanged, mapped `looks` to
`standard`, and mapped `delivery` to `high`; the installed help contract was
checked without starting a render. No compatibility issue was found in that
negotiation.

## Evidence files

The final suite and brand check logs are in the ignored directory
`artifacts/video-studio/workstation-proof/core-review/`. This core-review lane
made no provider requests, render jobs, publishing operations, production
changes, or customer communications. It does not certify visual approval or a
creative recreation.
