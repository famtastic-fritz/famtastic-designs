# Local from-scratch walking generation assessment — 2026-09-19

## Decision

No pilot of a newly generated, identity-consistent walking video was run on this workstation. The Mac has 16 GiB shared memory, only 6.4 GiB free disk, and no installed video-generation runtime or checkpoint. The candidates require multi-gigabyte weights and additional runtime/model assets that do not fit the present storage headroom. MPS feasibility on this specific M5 is unmeasured, so this report does not infer impossibility from CUDA VRAM figures.

This is different from the previous approved workflow, which reused Fritz’s supplied walking footage, generated a person matte locally with Apple Vision, and composited new graphics. That work preserves source motion and source identity; it does not synthesize a new walk or establish from-scratch identity retention.

## Current workstation snapshot

Measured read-only at **2026-09-19 18:18 UTC** on the same workstation used for the earlier proof:

| Item | Observed |
|---|---|
| Hardware identifier | `Mac17,2`, Apple M5-class Mac |
| Unified memory / CPU | 16 GiB / 10 cores |
| macOS | 26.6 |
| Memory pressure | system-wide free percentage 36%; `vm_stat` throttled pages 0 in this sample |
| Data-volume free space | 6.4 GiB; data volume reported 99% capacity |
| Swift | 6.3.3 |
| Python / Node | Python 3.14.7 / Node 24.19.0 |
| FFmpeg / ffprobe | 9.0 / 9.0 |
| System Python 3.14 imports | `mlx` present; `torch`, `diffusers`, `transformers`, `comfyui`, `coremltools`, `cv2`, and `onnxruntime` absent |
| Executables | no `comfy`, `comfyui`, or `mlx_lm` on PATH |
| ComfyUI check | no process match and no listener on local TCP 8188 |
| Hugging Face hub cache | 313 MiB total; 7 files at the bounded depth checked, with no model weights found |

The raw serial-free sample is [workstation-snapshot.txt](../../../artifacts/video-studio/walking-feasibility-20260919/workstation-snapshot.txt). It contains no serial number, secrets, or private credentials. The free-memory percentage is a point-in-time system-wide indicator, not a GPU allocation guarantee or idle baseline.

An expanded bounded pass checked the five discovered common `.venv` environments; none contained the target PyTorch/diffusers/Comfy/MLX generation packages. MLX core imports from the active Homebrew Python, but `mlx_lm`, `mlx_vlm`, `mlx_video`, and `mlx_image` are absent, and the named standard MLX/Comfy model cache roots are absent. Ollama is present with three text-language models, which are not video generators. This is not a forensic search of every home directory or external volume. The checks and limits are recorded in [expanded-install-check.txt](../../../artifacts/video-studio/walking-feasibility-20260919/expanded-install-check.txt).

## Candidate fit and limits

| Candidate | What it can do | Published sizing / platform evidence | Assessment here |
|---|---|---|---|
| Wan2.2 TI2V-5B | One-image-conditioned or text-generated video; plausible short walking test from an identity still | Wan upstream says its 720p reference path uses at least 24 GB VRAM. Comfy’s native offload tutorial says 8 GB VRAM, but that refers to its own GPU workflow. The Comfy pack lists a 10 GB diffusion file, 6.74 GB FP8 text encoder, and 1.41 GB VAE (18.15 GB decimal combined). | None of the weights/runtime is installed. Those three files alone exceed the 6.4 GiB currently free. Neither cited VRAM figure proves equivalent behavior or impossibility on 16 GiB unified memory; the actual MPS/Comfy path would need a coordinated measured test on this Mac. |
| Wan2.2 Animate-14B | Reference-driven character animation or replacement, including movement/expression transfer | Wan describes Animate-14B as character animation/replacement. Its weights are roughly 14B-parameter scale; Wan’s official single-GPU A14B reference path calls for at least 80 GB VRAM. | Best concept match for identity plus a separate walking-motion driver, but no runtime/weights are installed and the published reference configuration targets a much larger GPU. A driver clip supplies motion; this is not text-only walking from nothing. This is a strong-machine candidate, not a measured M5 impossibility claim. |
| LTX-Video 2B distilled | Image-to-video and short video generation; smallest credible identity-conditioned model family located | Lightricks’ official repository says macOS MPS was tested with PyTorch 2.3 or supported again at PyTorch 2.6+. The 2B 0.9.8 distilled FP8 checkpoint is 4.46 GB; its non-FP8 checkpoint is 6.34 GB. The FP8 config requests `float8_e4m3fn`, whose MPS execution was not verified in this assessment. | Most plausible small-model family to investigate on this Mac, but all checkpoint/support assets/runtime are absent. Even the 6.34 GB checkpoint alone nearly consumes current free disk, before the text encoder, other model assets and environment. Prefer testing the BF16/standard variant on MPS before FP8; the actual short run remains unproven. |
| CogVideoX-2B | Generic text-to-video at 720×480; the official table lists image-to-video as a separate 5B model | Official repository reports a 5 GB minimum single-GPU memory (BF16) or 4.4 GB INT8 for its 2B path; setup requires Python 3.10–3.12. | Could generate a generic short walk, but it does not satisfy identity-conditioned Fritz walking in its 2B version. Current active Python is 3.14 and no Apple MPS proof is installed or measured. |

The model sizes refer to separate files and exclude runtimes, caches, outputs, and swap. For Wan TI2V-5B, the 18.15 GB combined weight files are already roughly 11.3 GB beyond the available space when comparing the 6.4 GiB free sample (about 6.87 GB decimal). As a conservative operations target—not an upstream minimum—reserve at least 30 GiB free before staging the full Wan set, runtime, cache and render outputs. For LTX-Video 2B, reserve at least 15 GiB free as an initial engineering target for the 6.34 GB standard checkpoint, support models, environment, caches and output; verify actual component sizes before setup. The 16 GiB unified-memory figure alone cannot determine success: model weights, activations, OS, other open apps, and any concurrent render share physical memory, so record live pressure throughout a coordinated pilot. The measured 36% system-wide free-memory percentage is only a point-in-time snapshot, not a per-process GPU budget.

Lightricks’ current LTX-2.5 repository documents its standard model set at roughly 66 GiB. That is not the light path on this Mac; the older LTX-Video 2B checkpoint above remains the smallest candidate located in this survey.

## Body motion, identity, and speech are separate stages

An image-to-video model can start from a still portrait and invent a walking sequence, but that is not proof that the face/body remains recognizable throughout the clip. That needs a short controlled pilot and frame-by-frame identity review. A motion-transfer model such as Wan Animate instead needs a motion-driver video as well as the target identity; the body movement is then driven by that source. Neither stage was run here.

Voice cloning is a separate audio task. Lip-sync systems such as MuseTalk alter a small face region from supplied speech audio; they do not create the person’s body movement or solve identity-preserving full-body video generation. MuseTalk’s upstream describes a 256×256 face edit and reports a minimum test on Windows with an NVIDIA RTX 3050 Ti 4 GB, about five minutes for eight seconds in fp16. Its setup is CUDA/PyTorch-oriented. No voice model, voice clone, or lip-sync model was installed or tested in this assessment.

## Practical recommendation

For a no-spend proof that retains the real person today, use the supplied walk as footage and label the work as edited/recomposited source video. That is the only demonstrated identity-preserving path in this project so far.

For a genuine new walk without a paid provider, the most conservative next experiment is a **silent, 1–2 second, low-resolution, image-conditioned LTX-Video 2B BF16/MPS pilot** from one owner-supplied still. First establish sufficient disk headroom and an idle inference window, then verify the exact model/runtime combination. This is a plausible experiment on Apple MPS, not a guarantee that it will run acceptably on the M5; do not assume the FP8 config works on MPS without a separate test. Log model and auxiliary file hashes, precision, frame count, runtime, peak/after memory pressure, free disk, and every frame inspected. If stable body identity is not retained, stop and do not turn the result into a full-length promise.

If the requirement is specifically to transfer Fritz’s walk to a generated/replacement character, the stronger cited candidate is Wan2.2 Animate-14B on a different machine with high-memory GPU capacity, paired with a separately licensed/approved walking driver. Treat voice cloning and lip sync as subsequent stages, each requiring its own evidence.

No inference, model download, paid provider call, runtime install, or source footage upload occurred during this assessment.

## Official primary sources checked

- [Wan2.2 official repository](https://github.com/Wan-Video/Wan2.2) — model purposes, supported tasks, and upstream VRAM notes.
- [ComfyUI’s Wan2.2 native workflow guide](https://docs.comfy.org/tutorials/video/wan/wan2_2) — 8 GB VRAM offload statement and required local model components.
- [Official Comfy-Org Wan2.2 diffusion file](https://huggingface.co/Comfy-Org/Wan_2.2_ComfyUI_Repackaged/blob/main/split_files/diffusion_models/wan2.2_ti2v_5B_fp16.safetensors), [FP8 text encoder](https://huggingface.co/Comfy-Org/Wan_2.2_ComfyUI_Repackaged/blob/main/split_files/text_encoders/umt5_xxl_fp8_e4m3fn_scaled.safetensors), and [VAE](https://huggingface.co/Comfy-Org/Wan_2.2_ComfyUI_Repackaged/blob/main/split_files/vae/wan2.2_vae.safetensors) — file sizes.
- [Lightricks LTX-Video official repository](https://github.com/Lightricks/LTX-Video) — image-to-video support, MPS note, and model variants.
- [Lightricks 2B distilled FP8 checkpoint listing](https://huggingface.co/Lightricks/LTX-Video/tree/main) — checkpoint size.
- [Lightricks official 2B FP8 inference config](https://github.com/Lightricks/LTX-Video/blob/main/configs/ltxv-2b-0.9.8-distilled-fp8.yaml) — it selects `float8_e4m3fn`; MPS compatibility for that precision was not verified here.
- [Lightricks LTX-2.5 official repository](https://github.com/Lightricks/LTX-2) — the current 22B pipeline and approximately 66 GiB model set.
- [zai-org CogVideo official repository](https://github.com/zai-org/CogVideo) — CogVideoX-2B generation tasks, memory figures, and Python requirements.
- [MuseTalk official repository](https://github.com/TMElyralab/MuseTalk) — face-region lip-sync scope and reported minimum demo hardware.

## Later same-session runtime change

The separate voice-conversion task subsequently installed PyTorch 2.7.1 and torchaudio 2.7.1 in an isolated Python 3.11 environment. That does not install a video-generation checkpoint or prove image-to-video execution. Disk headroom fell to about 5.4 GiB after the bounded voice setup; the earlier table remains its time-stamped read-only snapshot.
