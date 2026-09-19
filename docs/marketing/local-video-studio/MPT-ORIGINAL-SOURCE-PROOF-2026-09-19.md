# MoneyPrinterTurbo original-source integration proof — 2026-09-19

This is a native integration exercise, separate from the authored HyperFrames remake. It proves that the existing local MoneyPrinterTurbo installation accepts the owner-supplied source MP4 and its copied original AAC soundtrack. It is not creative, rights, or owner acceptance.

## Inputs and preflight

- Source: /Users/famtastic-fritz/Downloads/FAMtastic-Designs-30sec-FINAL-COPY-FIXED.mp4, copied byte-for-byte to artifacts/video-studio/mpt-real-source-20260919/inputs/owner-original.mp4; 7,473,049 bytes; SHA-256 95ae3ca64a6eaee00c772ddd70063c210b55dff03632b9690ec645ffa2bae49f.
- Soundtrack: artifacts/video-studio/recreation-20260919/project-v3/assets/soundtrack.m4a, copied byte-for-byte to artifacts/video-studio/mpt-real-source-20260919/inputs/owner-original-soundtrack.m4a; 491,447 bytes; SHA-256 94511f1cbb791c99f14d047a8c5316457df4fe0af0787454cdc23e8054648d1c.
- The source is H.264 480×854 at 24 fps for 30.041667 s, with AAC stereo 32 kHz audio for 29.952 s. The supplied soundtrack copy is AAC stereo 32 kHz for 29.952 s. The source files were re-hashed after rendering and unchanged.
- Existing installation: MoneyPrinterTurbo 1.3.4, CPython 3.11.15, MoviePy 2.2.1, FFmpeg 9.0; supported single-task CLI. The installation source revision was unavailable. CLI and schema hashes, publishing booleans and config hash are retained in artifacts/video-studio/mpt-real-source-20260919/preflight.json. Both upload settings were false; the post-run receipt retains the same config hash. No credentials were printed or changed.

## Native run

The stock wrapper ran one task with local video input, the copied custom M4A, a locally transcribed script, sequential montage, a five-second maximum clip, portrait aspect, two threads, and one output. Voice/TTS, subtitles, stock search, script generation, background music and automatic publishing were disabled. The installed MPT code opens source clips with audio=False and attaches the supplied custom audio as the final track.

Exact prepared request and its validated frozen copy are artifacts/video-studio/mpt-real-source-20260919/request-batch.json and artifacts/video-studio/mpt-real-source-20260919-mpt-20260919T154638Z-1863a08c/batch.json. The stock wrapper response is retained at artifacts/video-studio/mpt-real-source-20260919/wrapper-response.json; the adapter receipt contains native task ID d1aa87df-f2dc-4617-8c8a-755b9d325478 and the verified output path. The prepared request SHA-256 is 9a114b13cec7a148af610efe6134c168d1324fc2573f580be3011f03dbce6287; the frozen batch SHA-256 is 1f12063bf82fb456cb3d3cbde1870495453c3fa1ef3f0621ebf850dabaa70589.

The run completed in 61.54 s wall time (61.237 s measured by the adapter). The draft is artifacts/video-studio/mpt-real-source-20260919-mpt-20260919T154638Z-1863a08c/output/moneyprinter-draft-01.mp4: H.264 1080×1920, 30 fps, 899 frames, 29.966667 s; AAC stereo 44.1 kHz, 29.95 s; 15,518,980 bytes; SHA-256 bc13af7009171657fda3c8a3ee7f5f33278c5c4c62f42dc6ad8772aedafd577c.

The adapter's independent metadata validation passed. The canonical Build DNA validator passed with two stage records and seven artifact checksums. Full FFprobe JSON, verifier JSON, adapter receipt, wrapper response, Build DNA and validator output are retained in the run directory. The adapter intentionally does not persist raw native stdout/stderr; it keeps those process streams in memory to avoid exposing dependency diagnostics or credentials.

After decoding both AAC tracks to mono 8 kHz PCM, the output soundtrack correlated with the supplied soundtrack at 0.999788 Pearson correlation and zero measured offset. This is signal evidence only; no subjective listening is claimed. The adapter records $0 external-provider spend by the local-input contract; workstation network activity was not monitored.

## Limits

This is an MPT montage integration result, not the designed remake. MPT may trim and repeat source clips to cover the supplied soundtrack; it does not reproduce the authored scene layouts or exact timing. The output remains review-pending, and this proof does not imply visual approval, rights clearance, or human acceptance.
