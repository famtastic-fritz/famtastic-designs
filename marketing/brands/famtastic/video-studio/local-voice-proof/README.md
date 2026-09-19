# Local voice comparison project

This builder freezes three supplied PCM WAVs and creates a fresh 1080×1080 HyperFrames project. It sequences the original synthetic narrator, a synthetic reference sample, and an experimental local conversion with 0.5 seconds of silence between chapters. A two-pass local FFmpeg `loudnorm` stage targets −16 LUFS and −2 dBTP and writes one 48 kHz stereo AAC master. Chapter titles are the only captions; the animated bars are decorative and do not represent measured audio amplitude.

The third chapter is labeled experimental and requires pronunciation review. The comparison makes no claim that the converted sample reproduces Fritz’s voice. ASR mismatches are not rendered as if they were heard speech. Human playback and approval remain pending; running this builder does not render or publish a film.

Example with the current local proof inputs:

```sh
python3 marketing/brands/famtastic/video-studio/local-voice-proof/build_project.py \
  --source artifacts/video-studio/no-catch-v2-20260919/audio/narration.wav \
  --reference artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/synthetic-reference-af-bella.wav \
  --converted artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/conversion/converted-synthetic-af-bella-v2.wav \
  --receipt artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/conversion/conversion-attempt-03.json \
  --gsap artifacts/video-studio/no-catch-v2-20260919/project/assets/gsap.min.js \
  --output artifacts/video-studio/local-voice-proof-20260919/project-v1
```

Inputs, the optional conversion receipt, pinned GSAP, fonts, logo, mastering measurements, and builder source are hashed into the local Build DNA. The output must be a new path under ignored `artifacts/video-studio/`; retries use a different path so previous evidence is retained. This process uses only the local FFmpeg/Node/Python tools and makes no provider request or install.
