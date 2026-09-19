# Bring the workflow your ComfyUI actually runs

This folder deliberately contains no claimed runnable Wan API export. The official
Wan template is **UI format**. Converting its widget arrays into presumed API
inputs is brittle: native video save inputs changed between ComfyUI versions.
An API export from the installed, working application is the authority.

1. In local ComfyUI, load an official native template and verify it can execute.
2. Enable developer options in Settings if necessary. Choose **Export (API)**
   (older UI: **Save (API Format)**). A valid export maps node IDs to
   `class_type` and `inputs`; it does not have a top-level `nodes` array.
3. Store the export and its bindings in the campaign's source/evidence folder.
4. Use `binding_candidates()` to inspect actual node IDs, titles and scalar
   inputs. Bind the correct positive and negative text encoders explicitly.
5. The adapter checks every used class against its reviewed native allowlist and
   installed `/object_info`, including local model/file choices. Missing models
   stop the request. It never fetches models or installs custom nodes.

Example **binding syntax only**, not IDs guaranteed to exist in your workflow:

```json
{
  "positive_prompt": {"node": "6", "input": "text"},
  "negative_prompt": {"node": "7", "input": "text"},
  "seed": {"node": "3", "input": "seed"},
  "width": {"node": "55", "input": "width"},
  "height": {"node": "55", "input": "height"},
  "frame_count": {"node": "55", "input": "length"},
  "input_image": {"node": "56", "input": "image"}
}
```

Only keys supplied in the values JSON are changed. Other exported values stay
unchanged; review them before submission. `--input-image` uploads the image first
and replaces the `input_image` binding with the server's actual returned filename.
Binding values must be scalars; graph edits require a fresh reviewed export.

See `docs/marketing/local-video-studio/LOCAL-GENERATION.md` from the repository root
for setup, resource tradeoffs, CLI commands and acceptance evidence.
