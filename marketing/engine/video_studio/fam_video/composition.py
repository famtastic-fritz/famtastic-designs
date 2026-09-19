"""Compile local campaign data into a portable, seekable HyperFrames project.

The compositor owns visual presentation only. Offer truth, campaign approval and
publication stay with the calling application. No network requests or AI services.
"""

from __future__ import annotations

import hashlib
import html
import json
import math
import re
import shutil
import subprocess
from decimal import Decimal
from pathlib import Path
from urllib.parse import urlsplit


_COLOR = re.compile(r"#[0-9a-fA-F]{6}\Z")
_FOCAL = re.compile(r"(?:\d+(?:\.\d+)?% ){1}\d+(?:\.\d+)?%\Z")
_LAYOUTS = {"signal", "split", "monument", "resolve"}


def _color(value: str) -> str:
    if not isinstance(value, str) or not _COLOR.fullmatch(value):
        raise ValueError(f"Expected a six-digit hexadecimal color, got {value!r}")
    return value.lower()


def _escape(value: object) -> str:
    return html.escape(str(value), quote=True)


def _number(value: float | Decimal) -> str:
    return format(Decimal(str(value)).normalize(), "f")


def _script_json(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":")).replace("<", "\\u003c")


def _creator_credit(module: Path) -> str:
    """Use the injected brand credit renderer, including its asset checks."""
    module = Path(module)
    if not module.is_file():
        raise FileNotFoundError(f"Required creator-credit adapter is missing: {module}")
    node = shutil.which("node")
    if not node:
        raise RuntimeError("Node.js is required for the repository creator-credit contract")
    script = (
        "import {creatorCreditHtml} from " + json.dumps(module.resolve().as_uri()) + ";"
        "process.stdout.write(creatorCreditHtml({embedded:true}));"
    )
    result = subprocess.run(
        [node, "--input-type=module", "--eval", script],
        capture_output=True, text=True, check=False, timeout=30,
    )
    if result.returncode != 0 or not result.stdout.strip():
        raise RuntimeError("Canonical creator-credit rendering failed: " + result.stderr[-1500:])
    return result.stdout


_CSS = r"""
*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;overflow:hidden}
body{background:var(--ground);color:var(--ink);font-family:var(--body-font)}
#root{position:relative;width:100%;height:100%;overflow:hidden;background:var(--ground);isolation:isolate}
.scene{position:absolute;inset:0;overflow:hidden;background:var(--scene-ground)}
.material{position:absolute;inset:0;pointer-events:none;background:repeating-linear-gradient(117deg,transparent 0 5px,rgba(255,255,255,.012) 6px 7px)}
.frame-edge{position:absolute;left:var(--margin);right:var(--margin);top:var(--top);height:2px;background:linear-gradient(90deg,var(--edge-1) 0 18%,var(--edge-2) 18% 35%,var(--edge-3) 35% 53%,#ffffff18 53%)}
.scene-grid{position:absolute;left:var(--margin);right:var(--margin);top:var(--content-top);bottom:var(--content-bottom);display:grid;grid-template-columns:minmax(0,1.12fr) minmax(0,1fr);gap:var(--gap);align-items:center;min-width:0;min-height:0}
.copy{min-width:0;max-width:100%;position:relative;z-index:3;display:flex;flex-direction:column;align-items:flex-start;gap:var(--copy-gap)}
.eyebrow{font-size:var(--eyebrow-size);line-height:1.25;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--scene-accent);margin:0;max-width:100%;overflow-wrap:anywhere}
.headline{font-family:var(--display-font);font-weight:800;font-size:var(--headline-size);line-height:.98;letter-spacing:-.055em;margin:0;max-width:100%;overflow-wrap:anywhere;text-wrap:balance}
.body{margin:0;font-size:var(--body-size);line-height:1.38;letter-spacing:-.018em;max-width:36em;white-space:pre-line;overflow-wrap:anywhere;color:var(--ink)}
.accent-rule{height:var(--rule-size);width:var(--rule-width);background:var(--scene-accent);transform-origin:left center;flex-shrink:0}
.concept{position:relative;min-width:0;width:100%;height:100%;min-height:0;display:flex;align-items:center;justify-content:center;perspective:1100px}
.concept-stage{width:100%;flex-shrink:0;transform-origin:center center}
.dm-stack{width:100%;display:flex;flex-direction:column;gap:var(--object-gap);padding:var(--object-pad);transform:rotate(-5deg)}
.message{position:relative;padding:var(--object-pad);background:#151916;color:#f7f7f4;border:1px solid #394037;border-radius:var(--object-radius);font-size:var(--object-type);font-weight:650;line-height:1.15;min-width:0;overflow-wrap:anywhere}
.message:nth-child(2){margin-left:12%;background:var(--scene-accent);color:#070907;border-color:var(--scene-accent)}
.message:nth-child(3){margin-right:10%}
.message small{display:block;font-weight:400;font-size:.48em;line-height:1.5;letter-spacing:.16em;text-transform:uppercase;margin-bottom:.55em;opacity:.75}
.browser-object{width:100%;padding:var(--object-pad);background:#101512;border:1px solid #52604b;border-radius:var(--object-radius);color:#f7f7f4;transform:rotateY(-8deg) rotateZ(3deg);box-shadow:0 0 24px color-mix(in srgb,var(--scene-accent) 15%,transparent)}
.browser-bar{display:flex;align-items:center;gap:.5em;border-bottom:1px solid #36432f;padding-bottom:.7em;margin-bottom:.8em;font-size:var(--object-label);line-height:1.3;min-width:0}
.browser-dot{display:block;width:.36em;height:.36em;border-radius:50%;background:#829575;flex-shrink:0}
.address{display:block;margin-left:.25em;overflow-wrap:anywhere;min-width:0;color:var(--scene-accent);font-weight:600}
.site-name{font-family:var(--display-font);font-size:var(--object-type);font-weight:800;line-height:1.02;letter-spacing:-.04em;overflow-wrap:anywhere;margin:.15em 0 .7em}
.site-row{display:flex;align-items:center;gap:.5em;border-top:1px solid #34402d;padding:.65em 0;font-size:var(--object-label);line-height:1.2}
.site-mark{width:.42em;height:.42em;background:var(--scene-accent);flex-shrink:0;transform:rotate(45deg)}
.media-frame{width:100%;height:100%;overflow:hidden;border:1px solid #ffffff26;border-radius:var(--object-radius);position:relative;background:#101310}
.scene-image{display:block;width:100%;height:100%;object-fit:cover;object-position:var(--focal);transform-origin:var(--focal)}
.video-slot{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:var(--focal);z-index:1}
.video-scrim{position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,9,7,.96),rgba(7,9,7,.77) 52%,rgba(7,9,7,.12));z-index:2}
.video-scene .scene-grid{z-index:3}.video-scene .concept{opacity:0}
.layout-split .copy{border-left:var(--rule-size) solid var(--scene-accent);padding-left:var(--split-inset)}
.layout-split .headline{font-size:calc(var(--headline-size)*.87)}
.layout-monument .scene-grid{grid-template-columns:1fr;align-items:center}
.layout-monument .copy{max-width:90%;gap:var(--copy-gap)}
.layout-monument .headline{font-size:calc(var(--headline-size)*1.35);letter-spacing:-.06em}
.layout-monument .concept{display:none}
.layout-monument.image-scene .scene-grid{grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr)}
.layout-monument.image-scene .concept{display:flex}
.layout-resolve .scene-grid{grid-template-columns:1fr;place-items:center;text-align:center}
.layout-resolve .copy{align-items:center;max-width:91%}
.layout-resolve .headline{font-size:calc(var(--headline-size)*1.1)}
.layout-resolve .body{max-width:28em}
.layout-resolve .concept{display:none}
.layout-resolve.image-scene .concept{display:flex;position:absolute;inset:0;z-index:0;opacity:.28}
.destination{display:inline-flex;align-items:center;justify-content:center;max-width:100%;padding:.68em 1em;background:var(--scene-accent);color:#070907;font-size:var(--destination-size);font-weight:800;line-height:1.1;letter-spacing:-.03em;overflow-wrap:anywhere;text-decoration:none;border-radius:.15em;min-height:44px}
.persistent-header{position:absolute;z-index:20;top:var(--header-top);left:var(--margin);right:var(--margin);height:var(--logo-height);display:flex;align-items:center;justify-content:space-between;gap:var(--gap)}
.brand-logo{display:block;width:var(--logo-width);height:auto;object-fit:contain;max-height:100%;filter:none}
.chapter{font-size:var(--chapter-size);letter-spacing:.18em;text-transform:uppercase;line-height:1.2;color:#f7f7f499;text-align:right}
.creator-footer{position:absolute;z-index:30;bottom:var(--footer-bottom);left:0;right:0;display:flex;justify-content:center;pointer-events:auto}
.creator-footer>div{width:auto!important;padding:0!important}
.creator-footer>div a{padding:8px 12px!important}
.caption-slot{position:absolute;inset:0;z-index:25;pointer-events:none}
.caption-rail{position:absolute;left:12%;right:12%;bottom:calc(var(--footer-bottom) + 94px);display:flex;justify-content:center}
.caption-text{margin:0;padding:.36em .65em;background:#070907;color:#f7f7f4;border:1px solid #596252;border-radius:.2em;font-size:var(--caption-size);font-weight:650;line-height:1.3;text-align:center;white-space:pre-line;overflow-wrap:anywhere;max-width:100%}
.portrait .scene-grid{grid-template-columns:1fr;grid-template-rows:auto minmax(0,1fr);align-items:start;gap:var(--gap)}
.portrait .concept{min-height:0;max-height:100%}
.portrait .copy{max-width:100%}
.portrait .dm-stack{width:90%;margin:auto;transform:rotate(-3deg)}
.portrait .browser-object{width:94%;align-self:center;transform:rotateZ(2deg)}
.portrait .layout-monument .scene-grid,.portrait .layout-resolve .scene-grid{grid-template-rows:1fr;align-items:center}
.portrait .layout-monument.image-scene .scene-grid{grid-template-columns:1fr;grid-template-rows:auto minmax(0,1fr)}
.portrait .video-scrim{background:linear-gradient(180deg,rgba(7,9,7,.88),rgba(7,9,7,.63) 56%,rgba(7,9,7,.2))}
.square .scene-grid{grid-template-columns:minmax(0,1.12fr) minmax(0,1fr);align-items:center}
.square .message small{display:none}.square .message{padding:calc(var(--object-pad)*.65);font-size:calc(var(--object-type)*.8)}
.square .layout-monument .scene-grid,.square .layout-resolve .scene-grid{grid-template-columns:1fr;grid-template-rows:1fr;align-items:center}
"""


_MOTION = r"""
const spec=JSON.parse(document.getElementById('motion-spec').textContent);
const motions=[];
// Fit illustrative objects to their allocated cell, including caption-safe cuts.
// The scene is measured once, independent of the requested output time.
function fitConcepts(){
  for(const stage of document.querySelectorAll('.concept-stage')){
    const parent=stage.parentElement;
    const available=Math.max(0,parent.clientHeight-12);
    if(available>0&&stage.scrollHeight>0){
      const scale=Math.min(1,available/(stage.scrollHeight*1.09));
      stage.style.transform='scale('+scale+')';
    }
  }
}
fitConcepts();
window.__hf=window.__hf||{};window.__hf.buildReady=window.__hf.buildReady||{};
window.__hf.buildReady['local-concept-layout']=document.fonts.ready.then(fitConcepts);
function tween(element,frames,start,duration,easing='cubic-bezier(.16,1,.3,1)'){
  if(!element||duration<=0)return;
  const animation=element.animate(frames,{delay:start*1000,duration:duration*1000,iterations:1,fill:'both',easing});
  animation.pause();motions.push(animation);
}
for(const scene of spec.scenes){
  const node=document.getElementById(scene.dom_id);
  const entrance=Math.min(.65,scene.duration*.2);
  tween(node.querySelector('.eyebrow'),[{opacity:0,transform:'translateY(12px)'},{opacity:1,transform:'translateY(0)'}],scene.start,entrance);
  tween(node.querySelector('.headline'),[{opacity:0,transform:'translateY(30px)'},{opacity:1,transform:'translateY(0)'}],scene.start+.06,entrance);
  tween(node.querySelector('.accent-rule'),[{transform:'scaleX(0)'},{transform:'scaleX(1)'}],scene.start+.12,entrance);
  tween(node.querySelector('.body'),[{opacity:0,transform:'translateY(14px)'},{opacity:1,transform:'translateY(0)'}],scene.start+.16,entrance);
  tween(node.querySelector('.destination'),[{opacity:0,transform:'translateY(18px)'},{opacity:1,transform:'translateY(0)'}],scene.start+.24,entrance);
  node.querySelectorAll('.message').forEach((item,i)=>tween(item,[{opacity:0,transform:'translateX(35px)'},{opacity:1,transform:'translateX(0)'}],scene.start+.18+i*.12,Math.min(.55,scene.duration*.17)));
  tween(node.querySelector('.browser-object'),[{opacity:0,transform:'rotateY(-14deg) rotateZ(4deg) translateY(24px)'},{opacity:1,transform:'rotateY(-8deg) rotateZ(3deg) translateY(0)'}],scene.start+.2,entrance);
  tween(node.querySelector('.scene-image'),[{transform:'scale(1)'},{transform:'scale(1.045)'}],scene.start,scene.duration,'linear');
}
// Exposed for deterministic proof tools; never starts a second playback clock.
window.famVideoSeek=function(seconds){for(const a of motions){a.pause();a.currentTime=Math.max(0,seconds)*1000;}};
window.famVideoMeta={duration:spec.duration,fps:spec.fps,scenes:spec.scenes};
"""


def _metrics(width: int, height: int) -> dict[str, str]:
    unit = min(width, height)
    portrait = height > width * 1.5
    square = not portrait and width < height * 1.35
    headline = unit * (.106 if portrait else .080 if square else .115)
    return {
        "margin": f"{width * .063:.3f}px", "top": f"{height * .17:.3f}px",
        "header-top": f"{height * .052:.3f}px", "content-top": f"{height * (.19 if square else .22):.3f}px",
        "content-bottom": f"{max(128, height * .16):.3f}px",
        "footer-bottom": f"{max(12, height * .028):.3f}px",
        "gap": f"{unit * .047:.3f}px", "copy-gap": f"{unit * (.020 if square else .027):.3f}px",
        "headline-size": f"{headline:.3f}px", "body-size": f"{unit * .030:.3f}px",
        "eyebrow-size": f"{unit * .021:.3f}px", "chapter-size": f"{unit * .019:.3f}px",
        "destination-size": f"{unit * .043:.3f}px", "rule-size": f"{max(3, unit * .007):.3f}px",
        "rule-width": f"{unit * .16:.3f}px", "split-inset": f"{unit * .028:.3f}px",
        "object-gap": f"{unit * .024:.3f}px", "object-pad": f"{unit * .032:.3f}px",
        "object-radius": f"{unit * .021:.3f}px", "object-type": f"{unit * .050:.3f}px",
        "object-label": f"{unit * .025:.3f}px", "logo-width": f"{unit * .32:.3f}px",
        "logo-height": f"{unit * .112:.3f}px",
        "caption-size": f"{unit * .037:.3f}px",
    }


def compile_project(campaign: dict, brand: dict, project_dir: Path, repo_root: Path) -> dict:
    """Write a local HTML project and return its reproducible compile receipt.

    The caller normalizes and validates campaign/brand schemas. Defense in depth
    here prevents HTML/CSS injection and accidental reads from remote locations.
    """
    project_dir, repo_root = Path(project_dir).resolve(), Path(repo_root).resolve()
    if project_dir == repo_root:
        raise ValueError("Compile into a dedicated project directory, not the repository root")
    width, height, fps = int(campaign["width"]), int(campaign["height"]), int(campaign.get("fps", 30))
    if width < 320 or height < 320 or fps <= 0:
        raise ValueError("Composition requires dimensions >=320 and a positive frame rate")
    scenes = campaign["scenes"]
    if not scenes:
        raise ValueError("A composition needs at least one scene")
    duration_decimal = sum((Decimal(str(s["duration"])) for s in scenes), Decimal(0))
    if not duration_decimal.is_finite() or duration_decimal <= 0:
        raise ValueError("Composition duration must be positive and finite")
    duration = float(duration_decimal)
    if any(not math.isfinite(float(s["duration"])) or float(s["duration"]) <= 0 for s in scenes):
        raise ValueError("Every scene duration must be positive and finite")
    project_dir.mkdir(parents=True, exist_ok=True)
    asset_dir = project_dir / "assets"
    if asset_dir.is_symlink():
        raise ValueError("Composition asset directory cannot be a symlink")
    asset_dir.mkdir(exist_ok=True)
    assets: list[dict] = []

    def copy_asset(source: object, role: str, expected_hash: str | None = None) -> str:
        original = Path(str(source))
        if not original.is_absolute() or not original.is_file():
            raise ValueError(f"Asset must be an existing absolute local file: {source}")
        content = original.read_bytes()
        digest = hashlib.sha256(content).hexdigest()
        if expected_hash and digest != expected_hash.lower():
            raise ValueError(f"Asset hash does not match approved {role}")
        suffix = original.suffix.lower()
        if not re.fullmatch(r"\.[a-z0-9]{1,8}", suffix):
            raise ValueError(f"Unsupported asset filename extension: {original.name}")
        relative = f"assets/{role}-{digest[:16]}{suffix}"
        destination = project_dir / relative
        if destination.is_symlink():
            raise ValueError("Refusing to overwrite a symlinked asset")
        destination.write_bytes(content)
        assets.append({"role": role, "path": relative, "sha256": digest, "bytes": len(content)})
        return relative

    logo = copy_asset(brand["logo"], "brand-logo", brand.get("logo_sha256"))
    font_faces: list[str] = []
    fonts = {"display-font": "system-ui,sans-serif", "body-font": "system-ui,sans-serif"}
    for key, varname in (("display_font", "display-font"), ("body_font", "body-font"), ("signature_font", "signature-font")):
        if brand.get(key):
            path = copy_asset(brand[key], key.replace("_", "-"))
            family = "Local" + key.replace("_", "").title()
            font_faces.append(f"@font-face{{font-family:{family};src:url('{path}');font-weight:100 900;font-style:normal;font-display:block}}")
            fonts[varname] = family + ",sans-serif"
    palette = brand.get("palette", {})
    ground, ink, accent = (_color(palette.get(k, v)) for k, v in (("background", "#070907"), ("text", "#f7f7f4"), ("accent", "#7cfc00")))
    variables = {**_metrics(width, height), **fonts, "ground": ground, "ink": ink, "accent": accent}
    edge_colors = palette.get("edge_colors", [ink, ink, ink])
    if not isinstance(edge_colors, list) or len(edge_colors) != 3:
        raise ValueError("Optional palette.edge_colors must contain three hex colors")
    variables.update({f"edge-{i+1}": _color(color) + "24" for i, color in enumerate(edge_colors)})
    if campaign.get("captions"):
        variables["content-bottom"] = f"{float(variables['content-bottom'][:-2]) + min(width, height) * .13:.3f}px"
    variable_css = ":root{" + ";".join(f"--{k}:{v}" for k, v in variables.items()) + "}"
    parts: list[str] = []
    motion_scenes: list[dict] = []
    start_decimal = Decimal(0)
    address = urlsplit(str(brand["url"]))
    if address.scheme not in {"https", "http"} or not address.netloc:
        raise ValueError("Brand URL must be a public http(s) address")
    display_url = address.netloc + address.path.rstrip("/")
    for index, scene in enumerate(scenes):
        layout = scene.get("layout", "signal")
        if layout not in _LAYOUTS:
            raise ValueError(f"Unknown layout: {layout}")
        scene_id = f"scene-{index + 1:02d}"
        scene_duration = Decimal(str(scene["duration"]))
        current_accent = _color(scene.get("accent") or accent)
        current_ground = _color(scene.get("background") or ground)
        focal = scene.get("focal_point", "50% 50%")
        if not _FOCAL.fullmatch(focal):
            raise ValueError("focal_point must contain two percentage positions")
        headline = str(scene["headline"])
        # A conservative static fit keeps multi-format text independent of clocks.
        headline_scale = min(1.0, math.sqrt(42 / max(42, len(headline))))
        scene_style = f"--scene-accent:{current_accent};--scene-ground:{current_ground};--focal:{focal};--headline-size:{float(variables['headline-size'][:-2]) * headline_scale:.3f}px"
        media_src = None
        media_kind = scene.get("media_kind", "image")
        if scene.get("media"):
            media_src = copy_asset(scene["media"], f"scene-{index + 1:02d}-{media_kind}")
        classes = f"scene clip layout-{layout}" + (f" {media_kind}-scene" if media_src else "")
        # Timed video is a sibling, never a child of a plain timed scene wrapper.
        if media_src and media_kind == "video":
            trim = float(scene.get("trim_start", 0))
            if not math.isfinite(trim) or trim < 0:
                raise ValueError("Video trim_start must be finite and nonnegative")
            parts.append(f'<video id="video-{index + 1:02d}" class="video-slot" style="--focal:{_escape(focal)}" src="{media_src}" data-start="{_number(start_decimal)}" data-duration="{_number(scene_duration)}" data-media-start="{_number(trim)}" data-track-index="0" muted playsinline></video>')
            scene_style += ";background:transparent"
        concept = ""
        if media_src and media_kind == "image":
            concept = f'<div class="media-frame"><img id="image-{index + 1:02d}" class="scene-image" src="{media_src}" alt="{_escape(scene.get("media_alt", "Campaign source image"))}" /></div>'
        elif layout == "signal":
            concept = '<div class="dm-stack" aria-label="Illustration of common customer questions"><div class="message"><small>A customer asks</small>What are your hours?</div><div class="message"><small>Another message</small>Where do I book?</div><div class="message"><small>One more question</small>Send me your link.</div></div>'
        elif layout == "split":
            concept = f'<div class="browser-object" aria-label="Illustration of a business website"><div class="browser-bar"><i class="browser-dot"></i><i class="browser-dot"></i><span class="address">{_escape(display_url)}</span></div><div class="site-name">{_escape(brand["name"])}</div><div class="site-row"><i class="site-mark"></i>Find you.</div><div class="site-row"><i class="site-mark"></i>Understand what you do.</div><div class="site-row"><i class="site-mark"></i>Take the next step.</div></div>'
        if concept and not media_src:
            concept = '<div class="concept-stage">' + concept + '</div>'
        eyebrow = f'<p class="eyebrow">{_escape(scene["eyebrow"])}</p>' if scene.get("eyebrow") else ""
        body = f'<p class="body">{_escape(scene["body"])}</p>' if scene.get("body") else ""
        destination = f'<a class="destination" href="{_escape(brand["url"])}">{_escape(display_url)}</a>' if layout == "resolve" else ""
        scrim = '<div class="video-scrim"></div>' if media_src and media_kind == "video" else ""
        parts.append(f'<section id="{scene_id}" class="{classes}" style="{scene_style}" data-start="{_number(start_decimal)}" data-duration="{_number(scene_duration)}" data-track-index="2" data-scene-id="{_escape(scene["id"])}">{scrim}<div class="material"></div><div class="frame-edge"></div><div class="scene-grid"><div class="copy">{eyebrow}<h1 class="headline">{_escape(headline)}</h1><div class="accent-rule"></div>{body}{destination}</div><div class="concept">{concept}</div></div></section>')
        motion_scenes.append({"id": scene["id"], "dom_id": scene_id, "start": float(start_decimal), "duration": float(scene_duration), "layout": layout})
        start_decimal += scene_duration
    if campaign.get("audio"):
        audio = copy_asset(campaign["audio"], "soundtrack")
        parts.append(f'<audio id="campaign-audio" src="{audio}" data-start="0" data-duration="{_number(duration_decimal)}" data-track-index="10" data-volume="1"></audio>')
    for index, caption in enumerate(campaign.get("captions", [])):
        begin, end = float(caption["start"]), float(caption["end"])
        if not (math.isfinite(begin) and math.isfinite(end) and 0 <= begin < end <= duration):
            raise ValueError("Caption timing must fit within the composition")
        parts.append(f'<section id="caption-{index + 1:03d}" class="caption-slot clip" data-start="{_number(begin)}" data-duration="{_number(end - begin)}" data-track-index="20"><div class="caption-rail"><p class="caption-text">{_escape(caption["text"])}</p></div></section>')
    credit = _creator_credit(Path(brand["credit_module"])) if brand.get("credit_module") else ""
    shape = "portrait" if height > width * 1.5 else "square" if width < height * 1.35 else "landscape"
    motion_spec = {"duration": duration, "fps": fps, "scenes": motion_scenes}
    composition_id = "campaign-" + hashlib.sha256(str(campaign["id"]).encode()).hexdigest()[:12]
    result_html = (
        '<!doctype html>\n<html lang="en"><head><meta charset="utf-8">'
        f'<meta name="viewport" content="width={width},height={height}">'
        '<meta name="robots" content="noindex,nofollow">'
        f'<title>{_escape(campaign.get("title", campaign["id"]))}</title>'
        '<style>' + variable_css + "".join(font_faces) + _CSS + '</style></head><body>'
        f'<main id="root" class="{shape}" data-composition-id="{composition_id}" data-no-timeline data-start="0" data-duration="{_number(duration_decimal)}" data-width="{width}" data-height="{height}" data-fps="{fps}">'
        + "\n".join(parts)
        + f'<header class="persistent-header"><img class="brand-logo" src="{logo}" alt="{_escape(brand["name"])}"><span class="chapter">{_escape(campaign.get("series_label", ""))}</span></header>'
        + ('<footer class="creator-footer">' + credit + '</footer>' if credit else '') + '</main>'
        + '<script type="application/json" id="motion-spec">' + _script_json(motion_spec) + '</script>'
        + '<script>' + _MOTION + '</script></body></html>\n'
    )
    html_path = project_dir / "index.html"
    if html_path.is_symlink():
        raise ValueError("Refusing to overwrite a symlinked composition")
    html_path.write_text(result_html, encoding="utf-8")
    receipt = {
        "schema": "local-video.composition.v1", "campaign_id": campaign["id"],
        "project_dir": str(project_dir), "index_html": str(html_path),
        "manifest_path": str(project_dir / "composition.json"),
        "duration": duration, "frames": math.ceil(duration_decimal * fps),
        "fps": fps, "width": width, "height": height, "format": campaign.get("format"),
        "assets": assets, "scenes": motion_scenes, "animation_runtime": "native-waapi",
        "creator_credit": "configured-embedded-credit" if credit else None,
        "caption_count": len(campaign.get("captions", [])),
        "sha256": hashlib.sha256(result_html.encode("utf-8")).hexdigest(),
        "external_service_cost_usd": 0, "network_assets": False,
    }
    (project_dir / "composition.json").write_text(json.dumps(receipt, indent=2) + "\n", encoding="utf-8")
    return receipt
