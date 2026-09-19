# Local narration provenance

This record covers only the owner-script narration for **“What’s the catch?”**. It does not approve the finished film, establish rights in any visuals, or authorize publishing, email, or production use.

The unmodified source is [`user-script.txt`](user-script.txt), SHA-256 `12a242dae7818a836495ebc92bb3ff0274ccddfc230df531c9016a8261c0676f`. It contains 21 nonempty lines. [`voice-tts-normalizations.json`](voice-tts-normalizations.json) binds every spoken input to its exact source/caption line. The subtitle text remains the original user wording, including the brand capitalization.

TTS-only changes speak `$199` as “one hundred ninety-nine dollar,” render the URL as “Famtastic Designs dot com,” and normalize typographic quotation marks and apostrophes. The first pass used uppercase `FAMtastic`; cached local `whisper.cpp` small.en consistently transcribed that as “F.A.M.Tastic.” A bounded local comparison tested `FAMtastic`, `Famtastic`, and `fam-tastic` in the same phrase with Kokoro voice `am_michael`, speed `0.78`. The first was transcribed as separated letters; both title case and hyphenated speech were transcribed as the single word “Famtastic” and as `famtasticdesigns.com`. The final revision uses title-case `Famtastic` only in TTS input, preserving “FAMtastic” in the source and caption cue. No words or claims were added or removed.

## Voice and local runtime

- Preset: `am_michael` (Michael), a synthetic English male voice. No owner voice cloning, impersonation, previous-film audio, cloud TTS, or paid provider call was used.
- TTS engine: HyperFrames CLI **0.8.29**, `tts` command, speed `0.78`, language `en-us`; one native CLI invocation per script line. The resolved executable, Python runtime, commands, and output hashes are retained in `artifacts/video-studio/no-catch-20260919/audio/run/voice-input-freeze.json` and `synthesis-receipts.json`.
- Python: **3.11.15**, isolated local environment; dependency pins are in `artifacts/video-studio/no-catch-20260919/audio/run/requirements-freeze.txt`. The virtual environment occupies about 126 MB.
- Model: `hexgrad/Kokoro-82M v1.0` ONNX full-precision file, **325,532,387 bytes**, SHA-256 `7d5df8ecf7d4b1878015a32686053fd0eebe2bc377234608764cc0ef3636a6c5`. Source: [kokoro-onnx model-files v1.0 release](https://github.com/thewh1teagle/kokoro-onnx/releases/tag/model-files-v1.0). The upstream [Kokoro-82M model card](https://huggingface.co/hexgrad/Kokoro-82M/tree/main) states Apache-2.0 for the model.
- Voice bundle: `voices-v1.0.bin`, **28,214,398 bytes**, SHA-256 `bca610b8308e8d99f32e6fe4197e7ec01679264efed0cac9140fe9c29f1fbf7d`, from the same release. Its exact bytes and source are recorded; this report does not make a separate license conclusion for the bundled voice data.
- Model and voice files together occupy about 354 MB; they were already cached for this run after the authorized local setup. No unrelated runtime was upgraded.

## Final revision and checks

The original complete 21-line pass is preserved at `artifacts/video-studio/no-catch-20260919/audio/`. The pronunciation correction is a separate, immutable revision under `artifacts/video-studio/no-catch-20260919/audio/pronunciation-revision-20260919/`. It regenerated only lines **3, 4, 19, and 20** and reused the other **17** original line WAVs. SHA-256 comparisons of each reused PCM region against the pre-normalized composite passed. Revisions were assembled into 24 kHz mono PCM, then two-pass FFmpeg loudness normalization and AAC-LC encoding were applied. The initial failed FFmpeg option attempt is retained as `audio-master-attempt-1-failed.log`; the corrected command and successful measurements are in `audio-master.log` and `audio-master-receipt.json`.

The final master is `narration.m4a`, SHA-256 `78b793e59e91b485475bef1b9fa878378ba6e4bf738d46111157d9a73a4e0785`. It is AAC-LC, mono, 24 kHz, **101.930 seconds**. The matching PCM WAV is `narration.wav`, SHA-256 `97067e3a8b2304d9fef701fcc4c04b891651607804d4884144927cbddad385fd`. `cues.json` has 21 timed entries with both `text` and `source_text` exactly matching the original nonempty script lines; the last cue ends at 101.930 s. Composition may add its own end hold.

FFmpeg 9.0 measured the final AAC at **−16.5 LUFS integrated**, **−1.8 dBTP**, and **2.1 LU LRA**. It was below full scale and did not clip. A local CPU-only `whisper.cpp` **1.9.2** run with cached `ggml-small.en` (model SHA-256 `c6138d6d58ecc8322097e0f987c32f1be8bb0a18532a3f88f734d1bbf9c41e5d`), 2 threads, and GPU disabled took **33.89 s**, used **1,041,891,328 bytes** maximum resident set, and reported zero swaps. Its transcript follows all 21 lines in order and reads the final brand references as “Famtastic” / `famtasticdesigns.com`; its orthography and punctuation remain machine-transcription evidence, not a hearing claim. The transcript files are retained as `final-asr-check.txt`, `.srt`, and `.json`.

The first complete linewise TTS pass took **54.129 s** for 96.128 seconds of synthesized speech. The four-line corrected pass took **11.31 s**, with maximum child RSS **712,015,872 bytes**. The corrected narration contains **93.760 s** of speech and **8.170 s** of intentional inter-line gaps. Assembly, normalization, and AAC encode took **3.112 s**. Provider fees were **$0**. These timings exclude the earlier bounded pronunciation smoke comparison and local dependency/model setup.

## Reproduction

Use the tracked local-only scripts. They require an already-installed HyperFrames 0.8.29 CLI, a local Python environment with the recorded dependencies, and the exact cached model/voice hashes above. They refuse a missing or mismatched cache and do not upgrade or download models. The output directory must be new and inside this repository.

```sh
export HYPERFRAMES_CLI=/path/to/already-installed/hyperframes-0.8.29
export HYPERFRAMES_PYTHON=/path/to/local/python-3.11
python3 marketing/brands/famtastic/video-studio/whats-the-catch/synthesize-local-voice.py \
  --output-dir artifacts/video-studio/no-catch-20260919/audio/reproduction-unique
python3 marketing/brands/famtastic/video-studio/whats-the-catch/assemble-local-voice.py \
  --audio-dir artifacts/video-studio/no-catch-20260919/audio/reproduction-unique
```

The canonical execution receipt is `artifacts/video-studio/no-catch-20260919/audio/pronunciation-revision-20260919/run/build-dna.json`. It classifies the output as gated for review. No subjective audio review was performed by the tool, and no publishing or email action occurred.
