# MoneyPrinterTurbo: low-cost integration research

Research date: 2026-09-19. Read upstream source at commit `e0c9cb77ffd0226a789441420190f80a125c20c4`; checkout in `research/MoneyPrinterTurbo-upstream`. This is upstream evidence, not proof of Fritz's installed version. All implementation contracts must be detected against his actual installation.

## Decision

Use the existing installation as an optional production worker, through its native local CLI. Keep the FAMtastic scene manifest and exact branded composition outside MPT. Do not fork it, reinstall it, or make it a prerequisite for the basic renderer. MPT is valuable for narration-first reels, local image/video montage, transcript captions, and batches; use authored HTML/FFmpeg composition for exact title layout, prices, logos, and scene timing. This recommendation is an architectural inference from the interfaces below.

## What upstream already provides

The project now exposes CLI, API and WebUI workflows. It accepts custom scripts, uploaded images/video and uploaded narration; supports three aspect ratios and batch generation. Current software dependencies include Python >=3.11, MoviePy 2.2.1 and FFmpeg, with `uv.lock` for reproducibility. A GPU is not required for assembly. The application itself is MIT; preserve its license if copying any substantial code. Fonts, music, media, and models remain separately licensed.

Sources: [README](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/README-en.md), [dependencies](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/pyproject.toml), [MIT license](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/LICENSE).

## Exact preferred CLI contract

Launch the installed Python executable with `cli.py`, with cwd set to the installation root. Use an argument list, never a shell string. `--batch-file /absolute/path/tasks.json` accepts a JSON array or JSONL, at most 100 tasks / 1 MiB. Each entry overrides `VideoParams`; unknown fields are rejected. Absolute paths avoid cwd ambiguity and comma-separated filename issues.

```json
[
  {
    "video_subject": "FAMtastic proof reel",
    "video_script": "Prepared narration text. No model call is needed to write this.",
    "video_source": "local",
    "video_materials": [
      {"provider": "local", "url": "/absolute/assets/scene-01.mp4", "duration": 0},
      {"provider": "local", "url": "/absolute/assets/scene-02.png", "duration": 0}
    ],
    "custom_audio_file": "/absolute/assets/narration.wav",
    "voice_name": "no-voice",
    "video_aspect": "16:9",
    "video_concat_mode": "sequential",
    "video_fit_mode": "contain",
    "video_clip_duration": 5,
    "video_clip_speed": 1.0,
    "video_count": 1,
    "match_materials_to_script": false,
    "subtitle_enabled": false,
    "bgm_type": "",
    "bgm_volume": 0,
    "n_threads": 2
  }
]
```

Invocation: `<installed-python> cli.py --batch-file /absolute/tasks.json --stop-at video`.

`--stop-at` values: `script`, `terms`, `audio`, `subtitle`, `materials`, `video`. Local media bypasses search terms. Providing `video_script` bypasses script generation. Providing custom audio bypasses TTS. For an intentionally silent reel omit the audio field and retain `no-voice`, but MPT determines silent audio duration from the script; use a real silence WAV of the required length for deterministic duration.

Single-task success stdout is `{"task_id":"...","result":{...}}`. Batch success stdout contains `total`, `succeeded`, `failed`, `tasks`; each task has `index`, `task_id`, `status`, `result`, `failed_stage`, `error`. Exit 0 means success, 1 task failure, 2 invalid arguments/manifest. Logs use stderr. Results and intermediates are under `storage/tasks/<uuid>/`. Capture returned `result.videos`, and validate the files independently with ffprobe.

Source: [native CLI implementation](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/cli.py).

## Schema and output limitations

`MaterialInfo` has `provider`, `url`, `duration` and optional provenance. `VideoParams` accepts `video_subject` (required), `video_script`, local materials/audio, aspect, concat/transition/fit settings, captions and audio options. Valid aspect values are `16:9`, `9:16`, `1:1`; resolution maps to 1920x1080, 1080x1920, 1080x1080. `bgm_type` disabled in JSON is the empty string (the CLI string `none` converts to it).

The API request type is the same `VideoParams` model, but the HTTP file access rules differ from the trusted local CLI. No generic scene-per-title layout or exact independent scene duration schema exists here. A scene manifest adapter should warn that the MPT montage is not an exact reproduction of an authored timeline.

Source: [schema](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/models/schema.py).

## API fallback contract

Base route `/api/v1`. `POST /videos` submits `TaskVideoRequest` and returns `{"status":200,"data":{"task_id":"...","request_id":"...","params":{...}}}`; then `GET /tasks/{task_id}`. States: -1 failed, 1 complete, 4 processing. `POST /video_materials` is multipart field `file`; response `data.file` is a managed filename. `GET /video_materials` lists local materials. `POST /musics` similarly uploads music. `POST /audio` and `/subtitle` have separate request models; they do not accept custom audio.

HTTP arbitrary absolute file paths are not a valid local-media integration: media must be inside MPT's `storage/local_videos`; custom narration must be inside that task's directory. CLI prepares/copies external local media and explicitly allows trusted server-side audio paths. Prefer CLI for custom narration instead of inventing an upload endpoint. For older installations use that instance's `/openapi.json` and report unsupported features.

Sources: [controller](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/controllers/v1/video.py), [route prefix](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/controllers/v1/base.py), [state constants](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/models/const.py).

## Cost and publication boundaries

1. Defaults are not an offline guarantee: default material source is Pexels; default LLM configuration is cloud-backed; default Edge TTS needs internet despite no API key. Always supply local media, prepared script, custom narration, and disabled music generation for the zero-provider-spend path.
2. `bgm_type=sonilo` or `elevenlabs` calls external generation. Cloud media sources and paid voice selections also incur separate service costs. Explicit fields must override saved UI choices.
3. **Final video generation can automatically publish.** `upload_post_enabled`, a configured account/key, and `upload_post_auto_upload` together enable posting. There is no per-task disable field. The wrapper should inspect these booleans without logging credentials, and refuse execution while auto-upload is enabled. Never turn publishing on as part of proof.
4. Local Kokoro and Chatterbox are already supported through OpenAI-compatible services; default endpoints are loopback ports 8880 and 4123, respectively. Prefixes include `kokoro:` and `chatterbox:`. Recorded voice is the cheapest, most identity-faithful starting choice.
5. Custom-audio captions need `app.subtitle_provider="whisper"`; otherwise they are skipped. MPT can finish successfully with absent captions. Verify captions as a separate acceptance condition.

Sources: [configuration](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/config.example.toml), [task execution](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/services/task.py), [publishing service](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/services/upload_post.py).

## Resource gotchas

The local-image path adds zoom animation before assembly. Local media is resolution-checked, and files can be skipped; inspect returned assets rather than assuming every manifest item appears. Montage length follows narration, not a strict story-board duration. Native captions support sentence or word-by-word display plus a spring entrance, but they cannot guarantee brand-safe on-screen typography by themselves.

Whisper defaults to `large-v3` on CPU/int8 in current config. It may download a large model on first use; choose a predownloaded smaller model for the initial local proof. The implementation checks `models/whisper-<size>/model.bin` before resolving a download by model name.

Sources: [video processing](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/services/video.py), [local captions](https://github.com/harry0703/MoneyPrinterTurbo/blob/e0c9cb77ffd0226a789441420190f80a125c20c4/app/services/subtitle.py).

## Build and test now; prove on Fritz's machine later

Implement a manifest exporter, installed-path detector, non-secret capability report, safe CLI argv builder, config preflight, output parser and result-copy routine. Tests can use fake native CLI processes to exercise exits/JSON/artifact verification, filenames with spaces, disabled publishing, missing install and stale schema. These are adapter tests, not a claim that MPT ran. Real proof must record installed git revision, native --help capabilities, Python/FFmpeg versions, effective local-only fields, source media hashes, runtime, output ffprobe, captions present, and zero external-provider requests. Avoid modifying or upgrading the user's current working install without a specific reason.
