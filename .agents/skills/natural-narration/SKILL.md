---
name: natural-narration
description: Refine FAMtastic video narration with local stock voices, natural phrase pauses, and verified brand pronunciation while preserving the approved script and pacing.
---

# Natural Narration

Use for locally synthesized FAMtastic video narration when cadence, phrase pauses, or stock voice selection need refinement. This skill covers synthetic stock voices; it does not authorize cloning or impersonating a person.

## Keep the words and performance separate

- Preserve the approved script bytes and spoken meaning. Treat punctuation and phrase boundaries as performance direction, not permission to rewrite copy.
- Keep canonical script/caption text separate from pronunciation-only substitutions. If the TTS engine needs a phonetic spelling, record the original and spoken form in a line-indexed normalization map; keep captions in canonical spelling.
- Keep performance `phrases[].text` bound to the exact script wording. Apply any phonetic substitution through the runner’s separate pronunciation-normalization path, never by changing the performance phrase text or its source digest.
- Say “FAMtastic” as “fam-TAS-tik” (/fæmtˈæstɪk/), never as three spoken letters. Confirm pronunciation in a short local sample; ASR spelling alone cannot establish how it sounds.
- Discover the repository with `git rev-parse --show-toplevel` and locate `scripts/famtastic-local-narration.py` there. Do not bake a checkout-specific absolute path into reusable commands.

## Shape pace with pauses

Use the approved V2 speed and overall cadence as the baseline. Improve rhythm by splitting lines at complete thought groups, using punctuation and short varied rests, and only adjusting speed locally when a phrase needs it. Avoid slowing every line to lengthen a film, uniform mechanical gaps, or synthetic “breaths.” Measure the result; actual duration wins over an estimate.

Keep CLI, line, and phrase speed values within 0.8–1.3, explicit line/phrase pause values within 0–1.2 seconds, and the fallback CLI `--gap` within 0–0.6 seconds; reject out-of-range values instead of silently clipping them. Before a full render, synthesize and audition a short representative sample in the candidate stock voice. Cached local Kokoro female presets such as `af_heart` and `af_bella` are suitable candidates when available. Compare the same lines and pronunciation normalization at the V2 baseline; choose by the actual sample. ASR can flag likely text mismatches, but it does not prove naturalness or brand pronunciation. Report a human listening check as passed only if someone actually listened; otherwise leave subjective review open.

## Optional performance JSON

When the local runner supports `--performance`, keep its existing `--script`, `--voice`, `--speed`, `--gap`, and `--output` arguments working. The performance file is additive and should use `famtastic.narration-performance.v1`:

```json
{
  "schema": "famtastic.narration-performance.v1",
  "source_sha256": "<sha256 of exact script file bytes>",
  "lines": [
    {
      "index": 0,
      "pause_after_seconds": 0.0,
      "phrases": [
        {"text": "A complete thought", "pause_after_seconds": 0.22},
        {"text": "then the next thought.", "speed": 1.06}
      ]
    }
  ]
}
```

Line entries follow nonempty source lines in order. Each may set `speed`, `pause_after_seconds`, or `phrases`; each phrase may also set speed or a pause after it. After whitespace normalization, phrase text joined in order must equal that source line. A final phrase’s trailing pause belongs on its line entry, never on the phrase; the final script line must have a zero pause. Reject a digest mismatch, missing/reordered line, changed wording, or out-of-range control instead of guessing. Defaults remain the runner’s existing speed and gap.

Use only a verified local model and stock voice for a no-provider task. Check that the selected backend is actually local and that any API entitlement is free before invoking it; do not fall back automatically to a billed provider. A stock preset is allowed; a personal voice clone is outside this task.

After duration or phrase changes, regenerate word/phrase timings and captions from the final audio. Retain the voice and model identity/version, model hash when available, normalized-script mapping, performance-file hash, exact command, measured duration, output hash, and actual cost/provider status in the existing Build DNA evidence. Never report zero cost or a pronunciation/naturalness pass without supporting evidence.
