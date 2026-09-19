# Local image and video generation

Research checked: 2026-09-19. Implementation: optional local ComfyUI adapter.
Evidence level: HTTP protocol tested with a local fake server. Model inference,
identity preservation and performance on Fritz's Mac remain unproven.

## The economical decision

Make the everyday production line HyperFrames plus approved footage, clean plates,
exact typography, reusable compositions and recorded/local audio. Spend compute
on the few shots where subject motion changes the story. A 30-second campaign
need not be 30 seconds of newly generated diffusion video.

The repository already specifies **Apple Silicon with 16 GB unified memory** in
`marketing/local-models.json`. This is shared by the OS, GPU, application and model;
it is not an NVIDIA card with 16 GB dedicated VRAM plus separate system RAM.
Follow the local routing policy's approximately 8–9 GB default model-weight ceiling.
Do not download large weights merely because they fit on disk.

| Workstation | Initial choice | Expansion gate |
| --- | --- | --- |
| Existing 16 GB Mac | HyperFrames composition and reuse of approved assets; existing Qwen/GLM local copy lane | One measured, visually reviewed local generation pilot with acceptable memory pressure |
| Compatible NVIDIA GPU already owned | Native ComfyUI workflow; consider Wan2.2 TI2V-5B for hero-shot motion | Record exact GPU, system RAM, model files, resolution, frames, runtime and output quality |
| CPU-only host | Designed motion, editing, import/export and QA | No assumption that diffusion generation is practical |

Comfy's Wan tutorial reports 5B can fit 8 GB VRAM with native offloading. Its
specified model files are the 5B FP16 diffusion model, Wan2.2 VAE and scaled FP8
UMT5 encoder. That statement is about its execution path and is **not a promise
for a 16 GB Mac**. [Official Comfy Wan tutorial](https://docs.comfy.org/tutorials/video/wan/wan2_2)

Wan's own reference CLI documents a 24 GB GPU example for TI2V-5B at its supported
720p sizes. Different runtimes/offloading explain different published memory
figures. Its 5B model supports text or one-image conditioning; first/last-frame
workflows are a separate choice. No face-lock guarantee follows from either.
[Official Wan repository](https://github.com/Wan-Video/Wan2.2)

ComfyUI supports native installations on macOS, Windows and Linux; select the
matching PyTorch/backend instructions. Install it in a separate environment from
this engine. Existing working installations take priority over reinstalling.
[Official installation instructions](https://docs.comfy.org/installation/manual_install)

## Connect an existing installation

1. Start local ComfyUI bound to `127.0.0.1` using its existing environment. In a
   manual installation the normal entry point is `python main.py`; inspect that
   installation's `--help` for its supported flags. Keep it on loopback. Do not
   enable a public listener for this adapter.
2. In ComfyUI choose an official **native** workflow for the installed models.
   Keep cloud/partner nodes out. Pin the working ComfyUI commit/version and record
   model filenames and hashes in the campaign's generation evidence.
3. If adding a model, review its exact download size, model card and license before
   downloading. Run a short, explicitly bounded pilot after unloading other large
   models. Stop on sustained memory pressure or impractical latency. No automatic
   model downloads are performed by this package.
4. Execute the workflow once in the local application. Export its API form using
   **Export (API)** or **Save (API Format)**; enable developer options if hidden.
   Keep the UI export as an editing source too. The official project distinguishes
   the UI and API formats. [Comfy CLI format discussion](https://github.com/Comfy-Org/comfy-cli/issues/446)
5. Inspect actual inputs. From the repository root, after package installation:

```bash
PYTHONPATH=marketing/engine/video_studio python3 - <<'PY'
import json
from fam_video.adapters.comfy import binding_candidates
with open("/absolute/path/your-workflow.api.json", encoding="utf-8") as f:
    workflow = json.load(f)
print(json.dumps(binding_candidates(workflow), indent=2, ensure_ascii=False))
PY
```

6. Write a binding map whose keys are the logical names you want to set, such as
   `positive_prompt`, `negative_prompt`, `seed`, `width`, `height`, `frame_count`
   and `input_image`. Each value is exactly `{"node":"actual-id","input":"actual-field"}`.
   Use titles and graph connections to distinguish positive and negative encoders.
   Do not infer them from a model's usual node numbers.
7. Write a values JSON. An example **pilot request, not a benchmark claim**:

```json
{
  "positive_prompt": "A composed subject calmly checks a watch while the impossible environment moves. Preserve the source identity. Natural lighting, restrained motion. No text or logos.",
  "negative_prompt": "text, logo, face distortion, deformed hands, flicker",
  "seed": 42,
  "width": 512,
  "height": 896,
  "frame_count": 33
}
```

For Wan5B the adapter requires spatial multiples of 32 and `4n+1` frames. These
pilot settings are smaller than the upstream 720p target; they are a resource
experiment, not a statement of equivalent quality. Keep FPS explicit in the
exported video creation node, or add your own `fps` binding.

8. Execute with the package CLI. Use a new output directory for every deliberate
   generation; the command creates `comfy-receipt.json` there.

```bash
python3 scripts/famtastic-video.py generate \
  --workflow /absolute/path/your-workflow.api.json \
  --bindings /absolute/path/your-bindings.json \
  --values /absolute/path/your-values.json \
  --input-image /absolute/path/approved-clean-plate.png \
  --output artifacts/video-studio/generation-pilot-001 \
  --timeout 1800
```

Omit `--input-image` for a text-only graph. If it is supplied, the graph must have
an explicit `input_image` binding to a real LoadImage input. The image is uploaded
with a content-derived safe filename and the server's returned name is bound.

## What the adapter proves and preserves

The client validates the API graph against the installed `/object_info` and a
reviewed native-node allowlist. It checks missing classes, required inputs,
model/file dropdown choices, linked output compatibility and numeric bounds.
Custom nodes require a deliberate reviewed code change; they are not silently
trusted because their names sound local. Dynamic input validation remains subject
to the installed Comfy server's own `/prompt` validation.

It writes durable submission intent before POST, then the returned prompt ID
before polling history. It downloads outputs through `/view` using the actual
history metadata, saves them under safe hash-derived names and records SHA-256.
Its interface follows the [official Comfy server routes](https://docs.comfy.org/development/comfyui-server/comms_routes).

No request goes to a LAN/cloud host; HTTP redirects and environment proxies are
refused. A native allowlist cannot sandbox malicious code installed inside a
trusted ComfyUI process. Keep that installation reviewed. Partner/API generation
uses credits even when invoked through local ComfyUI; this adapter excludes it.
[Comfy credit documentation](https://docs.comfy.org/interface/credits)

`api_spend_usd: 0` means this adapter made no paid API request. Electricity, model
download bandwidth, hardware ownership and operator time are not measured and
are not represented as zero.

## Resume instead of accidentally generating twice

```bash
python3 scripts/famtastic-video.py resume artifacts/video-studio/generation-pilot-001/comfy-receipt.json --timeout 1800
```

A polling timeout leaves the same job active in ComfyUI. Resume only polls and
retrieves that prompt; it never submits another one. A completed receipt validates
its local output hashes. Deleted or changed outputs are re-fetched from the same
history when available.

If POST may have reached the server but no prompt ID was received, the receipt
is `submission_unknown`. Inspect that local Comfy queue/history manually and
reconcile the returned ID. Do not delete the receipt and rerun blindly. A lock
file after an abrupt process death also requires checking that the original runner
is no longer active. No global `/interrupt` is sent because it could cancel another
person's queued work.

Generation waiting uses a deadline, with each HTTP request bounded. Output
transfers use a 30-second socket timeout and 1 GB per-file limit. A later resume
can retry retrieval without recomputing the scene. Successful files remain draft
assets until technical checks and creative review pass.

## Record the pilot result

Keep the receipt plus source image hash, exact software versions, model filenames
and hashes, machine/OS/backend, wall time, peak memory or memory-pressure evidence,
output dimensions/FPS/duration, operator notes, rights and the actual visual review.
Review identity, hands, motion coherence and lighting. A truthful failure is useful:
record it, keep the existing designed-motion lane working and do not quietly switch
to a paid provider. Add accepted files and their receipts to the campaign's common
Build DNA record before handoff; generation alone does not approve a campaign.

No downloaded weights or experimental customer assets belong in the reusable
engine's Git tree. New model licenses and voice/identity rights must be recorded
for the actual selected artifacts; this document does not grant those rights.
