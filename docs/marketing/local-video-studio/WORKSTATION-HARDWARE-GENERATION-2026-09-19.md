# Workstation hardware and local generation check — 2026-09-19

## Finding

No local ComfyUI installation, server, or Wan model was found in the bounded standard installation and cache locations checked on Fritz's workstation. There is therefore no native workflow to execute and no model hash, precision/runtime, frame dimensions, generation time, or visual result to report. No model was downloaded and no inference was attempted. The Comfy adapter's fake-server protocol tests remain separate from model inference, as documented in `LOCAL-GENERATION.md`.

The machine is an Apple M5 Mac with 16 GiB unified memory. The data volume had 11 GiB free during the latest sample, while system-wide memory reported 27% free and no throttled pages. The sample was taken during concurrent local proof work, so it is not an idle baseline. The raw, serial-free snapshot is preserved in ignored `artifacts/video-studio/workstation-proof/hardware/host-snapshot-2026-09-19.txt`.

The available media toolchain is FFmpeg/FFprobe 9.0, Python 3.14.7, Node 24.19.0 and npm 11.17.0. HyperFrames is not on `PATH`; the cached executable at `/Users/famtastic-fritz/.npm/_npx/110f701c48e68d66/node_modules/.bin/hyperframes` reports version 0.8.29. The parent lane is using that cached executable for composition proof.

## ComfyUI and model feasibility

The checks covered `/Applications` and `~/Applications`, common manual-install roots, ComfyUI's documented macOS application-support directory, the `comfy` CLI and Python package inventory, process names, the standard local API port 8188, conventional model roots, and exact Wan2.2 entries in the default Hugging Face cache. No matches were found. This establishes absence in the usual existing-install locations checked; it is not a full forensic search of every user directory or external volume.

ComfyUI documents a native Apple Silicon path using PyTorch/MPS and recommends a separate Python environment; its system-requirements page says Python 3.14 works but some custom nodes can fail. The same page lists M1–M4 for Metal acceleration but does not name M5, so M5-specific ComfyUI behavior remains untested. [ComfyUI system requirements](https://docs.comfy.org/installation/system_requirements) and [manual installation](https://docs.comfy.org/installation/manual_install) describe the supported local setup.

The official Comfy Wan2.2 tutorial says TI2V-5B can fit 8 GB of *VRAM* when Comfy's native offloading is used. That is not a promise that this 16 GiB shared-memory Mac has equivalent headroom: macOS, other applications, model weights and activations use the same unified memory. The official Comfy workflow calls for a 10 GB FP16 diffusion model, 6.74 GB FP8 text encoder and 1.41 GB VAE—about 18.15 GB decimal (16.9 GiB) before ComfyUI, PyTorch, caches or generated files. With 11 GiB free, the current data volume is approximately 5.9 GiB short even for these three model files. [Comfy's native Wan2.2 workflow and model file list](https://docs.comfy.org/tutorials/video/wan/wan2_2), [official Comfy-Org model file sizes](https://huggingface.co/Comfy-Org/Wan_2.2_ComfyUI_Repackaged/tree/main/split_files/diffusion_models), [text encoder sizes](https://huggingface.co/Comfy-Org/Wan_2.2_ComfyUI_Repackaged/tree/main/split_files/text_encoders), [VAE sizes](https://huggingface.co/Comfy-Org/Wan_2.2_ComfyUI_Repackaged/tree/main/split_files/vae).

The upstream Wan reference CLI separately documents at least 24 GB VRAM for its 720p TI2V-5B command. This and Comfy's 8 GB offload note refer to different execution paths and hardware configurations; neither result measures performance on this Mac. [Wan2.2 official repository](https://github.com/Wan-Video/Wan2.2).

## Practical next step

Keep the HyperFrames plus approved-assets workflow as the local production path. Revisit diffusion only if a reviewed ComfyUI install and exact local model set are already available on a target with sufficient free storage, then coordinate with the parent lane so no competing render or model job is using shared memory. A small measured run would still need to prove memory pressure, elapsed time, dimensions/frame count, model hashes and visual quality. No install, large download, paid request or inference was part of this check.
