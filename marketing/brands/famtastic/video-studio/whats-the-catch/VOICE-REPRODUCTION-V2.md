# Local voice reproduction scripts, revision 2

This addendum is the current reproduction guide for **“What’s the catch?”** narration. The original `VOICE-PROVENANCE.md` and the original unversioned `synthesize-local-voice.py` / `assemble-local-voice.py` names are immutable historical records tied to the completed final-audio Build DNA. Use the versioned `synthesize-local-voice-v2.py` and `assemble-local-voice-v2.py` scripts for a new run. The historic report is kept byte-for-byte because the final voice ledger records its SHA-256.

The v2 synthesis runner requires the already-installed HyperFrames CLI to report exactly `0.8.29`, the selected local Python runtime, and the existing hash-verified cached Kokoro model/voice. It does not install or download anything. Its CLI child processes set `HYPERFRAMES_NO_TELEMETRY=1`, `HYPERFRAMES_NO_UPDATE_CHECK=1`, `HYPERFRAMES_NO_AUTO_INSTALL=1`, and `DO_NOT_TRACK=1`. The version and native TTS commands run with stdin disabled. Native TTS stdout may contain notices followed by a multiline JSON receipt; the adapter scans and validates the receipt against the requested WAV path.

Synthesis creates a new repository-local output directory and a canonical `run/campaign.snapshot.json` / Build DNA ledger. Before registering each runner as an artifact, it copies the exact runner bytes into `run/source-scripts/`; the resulting receipt is tied to the frozen copy rather than a mutable tracked path. Assembly resumes that same frozen campaign, resolves relative line-WAV receipt paths from the repository root, validates each path/hash and source-script line, and refuses to overwrite any existing assembly output (including the command log) before writing. Assembly uses local FFmpeg only and records the source-bound cues, measurement, AAC output, and commands in the run ledger.

Run the bounded script fixtures without invoking TTS or FFmpeg:

```sh
python3 marketing/brands/famtastic/video-studio/whats-the-catch/synthesize-local-voice-v2.py --self-test
python3 marketing/brands/famtastic/video-studio/whats-the-catch/assemble-local-voice-v2.py --self-test
```

For a new local reproduction, choose a fresh output directory and set paths to already-installed binaries:

```sh
export HYPERFRAMES_CLI=/path/to/already-installed/hyperframes-0.8.29
export HYPERFRAMES_PYTHON=/path/to/local/python-3.11
python3 marketing/brands/famtastic/video-studio/whats-the-catch/synthesize-local-voice-v2.py \
  --output-dir artifacts/video-studio/no-catch-20260919/audio/reproduction-unique
python3 marketing/brands/famtastic/video-studio/whats-the-catch/assemble-local-voice-v2.py \
  --audio-dir artifacts/video-studio/no-catch-20260919/audio/reproduction-unique
```

A reproduction is a new local run, not a replacement for the final narration or its historical ledger. The voice and model cache must match the hashes recorded in the campaign; no network TTS, paid provider, voice cloning, publishing, or email is used by these scripts.
