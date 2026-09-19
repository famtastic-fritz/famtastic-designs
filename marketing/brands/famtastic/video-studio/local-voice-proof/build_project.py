#!/usr/bin/env python3
"""Freeze three supplied WAVs into a local HyperFrames voice comparison."""
from __future__ import annotations
import argparse, hashlib, json, math, re, shutil, subprocess, sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[4]
LOGO_SHA = "ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950"
GSAP_SHA = "c174bfce53a729418d57a8ad8625e7247c793a22fef8e2851e3cfa3de9cd8280"
FPS, GAP = 30, .5
TITLES = ("Original synthetic narrator", "Reference voice sample (synthetic)", "Local voice conversion")

def sha(path: Path) -> str:
    with path.open("rb") as f: return hashlib.file_digest(f, "sha256").hexdigest()

def call(argv: list[str], capture=False):
    return subprocess.run(argv, check=True, text=True, capture_output=capture, timeout=300)

def wav(path: Path) -> dict:
    info = json.loads(call(["ffprobe","-v","error","-show_streams","-show_format","-of","json",str(path)],True).stdout)
    streams = [s for s in info.get("streams",[]) if s.get("codec_type")=="audio"]
    if len(streams)!=1 or not streams[0].get("codec_name","").startswith("pcm_"):
        raise ValueError(f"Expected one PCM WAV stream: {path.name}")
    duration = float(streams[0].get("duration",info["format"].get("duration","nan")))
    if not math.isfinite(duration) or duration<=0: raise ValueError(f"Invalid WAV duration: {path.name}")
    return {"duration_seconds":duration,"sample_rate":int(streams[0]["sample_rate"]),"channels":int(streams[0]["channels"])}

def graph() -> str:
    return ("[0:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo[a0];"
            "[1:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo[a1];"
            "[2:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo[a2];"
            "anullsrc=r=48000:cl=stereo:d=0.5[g1];anullsrc=r=48000:cl=stereo:d=0.5[g2];"
            "[a0][g1][a1][g2][a2]concat=n=5:v=0:a=1[j];[j]loudnorm=I=-16:TP=-2:LRA=7:print_format=json[o]")

def loudnorm(stderr: str) -> dict:
    blocks = re.findall(r'\{\s*"input_i"[\s\S]*?\}',stderr)
    if not blocks: raise ValueError("FFmpeg did not return loudnorm measurements.")
    return json.loads(blocks[-1])

def vtt_time(seconds: float) -> str:
    ms=round(seconds*1000)
    return f"{ms//3600000:02d}:{ms//60000%60:02d}:{ms//1000%60:02d}.{ms%1000:03d}"

def main() -> None:
    p=argparse.ArgumentParser(description=__doc__)
    for name,help_text in (("source","Original synthetic narrator WAV"),("reference","Synthetic reference WAV"),("converted","Local conversion WAV")):
        p.add_argument("--"+name,type=Path,required=True,help=help_text)
    p.add_argument("--gsap",type=Path,required=True,help="Pinned local GSAP 3.14.2")
    p.add_argument("--output",type=Path,required=True,help="New directory under artifacts/video-studio/")
    p.add_argument("--receipt",type=Path,help="Optional conversion receipt JSON, copied byte-for-byte")
    a=p.parse_args(); sources={"source-narrator.wav":a.source,"reference-synthetic.wav":a.reference,"converted-local.wav":a.converted}
    for f in [*sources.values(),a.gsap,*([a.receipt] if a.receipt else [])]:
        if not f.is_file(): p.error(f"Missing local input: {f}")
    if any(f.suffix.lower()!=".wav" for f in sources.values()): p.error("All audio inputs must be WAV files.")
    if sha(a.gsap)!=GSAP_SHA: p.error("Pinned GSAP 3.14.2 bytes required.")
    if a.receipt:
        try: json.loads(a.receipt.read_text(encoding="utf-8"))
        except (UnicodeError,json.JSONDecodeError) as e: p.error(f"Receipt must be valid JSON: {e}")
    out=a.output.expanduser().resolve(); ignored=(REPO/"artifacts/video-studio").resolve()
    if not out.is_relative_to(ignored): p.error("Output must be under ignored artifacts/video-studio.")
    if out.exists(): p.error("Use a new output directory; prior attempts are retained.")
    logo=REPO/"frontend/public/brand/famtastic-designs-logo-v1.png"
    copies={"gsap.min.js":a.gsap,"famtastic-designs-logo-v1.png":logo,
            "metropolis-regular.woff2":REPO/"frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-regular.woff2",
            "metropolis-bold.woff2":REPO/"frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-bold.woff2",
            "kaushan-script.woff2":REPO/"frontend/public/brand/fonts/kaushan-script-latin-v19.woff2"}
    if sha(logo)!=LOGO_SHA or any(not f.is_file() for f in copies.values()): p.error("Canonical logo/fonts unavailable or changed.")
    if not all(shutil.which(x) for x in ("ffmpeg","ffprobe","node")): p.error("Local ffmpeg, ffprobe and Node.js are required; no installs are attempted.")
    rows={n:{"sha256":sha(f),"size_bytes":f.stat().st_size,"probe":wav(f)} for n,f in sources.items()}
    receipt=({"name":a.receipt.name,"sha256":sha(a.receipt),"size_bytes":a.receipt.stat().st_size} if a.receipt else None)
    campaign={"id":"local-voice-comparison-v1","scope":"synthetic/local audio comparison; no Fritz-voice claim",
              "inputs":rows,"conversion_receipt":receipt,"gsap_sha256":GSAP_SHA,"gsap_version":"3.14.2",
              "builder_sha256":sha(HERE/"build_project.py"),"target_loudness_lufs":-16,
              "true_peak_ceiling_dbtp":-2,"gap_seconds":GAP,"rendered":False,"published":False}
    sys.path.insert(0,str(REPO/"marketing/engine/video_studio"))
    from fam_video.evidence import Ledger
    out.mkdir(parents=True); ledger=Ledger(out/"run",REPO,campaign)  # Build DNA precedes audio assembly.
    frozen_builder=out/"provenance/build_project.py"; frozen_builder.parent.mkdir(parents=True)
    shutil.copyfile(HERE/"build_project.py",frozen_builder)
    if sha(frozen_builder)!=campaign["builder_sha256"]: raise ValueError("Builder source changed during startup.")
    ledger.add_artifact(frozen_builder,"frozen_project_builder_source",rights="original")
    assets=out/"assets"; assets.mkdir(); frozen={}
    with ledger.stage("freeze-inputs","freeze_local_comparison_inputs",provider="local-filesystem",inputs=campaign) as stage:
        for name,source in sources.items():
            target=assets/name; shutil.copyfile(source,target)
            if sha(target)!=rows[name]["sha256"]: raise ValueError(f"Input hash changed: {name}")
            frozen[name]=target; ledger.add_artifact(target,"comparison_audio_input",rights="operator_supplied; review_required")
        for name,source in copies.items():
            target=assets/name; shutil.copyfile(source,target); expected=LOGO_SHA if name=="famtastic-designs-logo-v1.png" else sha(source)
            if sha(target)!=expected: raise ValueError(f"Asset hash changed: {name}")
            ledger.add_artifact(target,"canonical_brand_asset" if "logo" in name else "frozen_runtime_asset",rights="canonical_brand_asset" if "logo" in name else "bundled_local_asset")
        if a.receipt:
            receipt_path=out/"provenance/conversion-receipt.json"; receipt_path.parent.mkdir(parents=True,exist_ok=True)
            shutil.copyfile(a.receipt,receipt_path)
            if sha(receipt_path)!=receipt["sha256"]: raise ValueError("Conversion receipt hash changed.")
            ledger.add_artifact(receipt_path,"operator_supplied_conversion_receipt",rights="operator_supplied")
        stage["execution"]["output"]={"frozen_inputs":len(frozen),"receipt_frozen":bool(a.receipt)}

    lens=[rows[n]["probe"]["duration_seconds"] for n in sources]
    starts=[0,lens[0]+GAP,lens[0]+GAP+lens[1]+GAP]; ends=[starts[i]+lens[i] for i in range(3)]
    chapters=[{"id":f"chapter-{i+1}","title":title,"start":starts[i],"end":ends[i],"duration":lens[i],"audio":f"assets/{name}"}
              for i,(title,name) in enumerate(zip(TITLES,sources))]
    duration=math.ceil((ends[-1]+1e-5)*FPS)/FPS; master=assets/"audio_master.m4a"
    base=["ffmpeg","-hide_banner","-nostdin","-nostats","-loglevel","info",*[x for n in sources for x in ("-i",str(frozen[n]))]]
    g=graph()
    with ledger.stage("assemble-audio-master","sequence-and-loudnorm-comparison-audio",provider="local-ffmpeg",
        command=base+["-filter_complex",g,"-map","[o]","-f","null","-"],inputs={"durations_seconds":lens,"gap_seconds":GAP,"target_i_lufs":-16,"target_tp_dbtp":-2}) as stage:
        first=call(base+["-filter_complex",g,"-map","[o]","-f","null","-"],True); measured=loudnorm(first.stderr)
        keys=("input_i","input_tp","input_lra","input_thresh","target_offset"); vals={k:float(measured[k]) for k in keys}
        if not all(math.isfinite(v) for v in vals.values()): raise ValueError("Non-finite loudnorm measurement.")
        params={"measured_I":vals["input_i"],"measured_TP":vals["input_tp"],"measured_LRA":vals["input_lra"],"measured_thresh":vals["input_thresh"],"offset":vals["target_offset"]}
        g2=g.replace("loudnorm=I=-16:TP=-2:LRA=7:print_format=json","loudnorm=I=-16:TP=-2:LRA=7:"+":".join(f"{k}={v:.6f}" for k,v in params.items())+":linear=false:print_format=json")
        encoded=call(base+["-filter_complex",g2,"-map","[o]","-c:a","aac","-b:a","192k","-ar","48000","-y",str(master)],True)
        post=loudnorm(encoded.stderr); info=json.loads(call(["ffprobe","-v","error","-show_streams","-show_format","-of","json",str(master)],True).stdout)
        audio=[s for s in info["streams"] if s.get("codec_type")=="audio"]
        if len(audio)!=1 or audio[0].get("codec_name")!="aac": raise ValueError("Expected one AAC audio master.")
        master_len=float(audio[0].get("duration",info["format"]["duration"]))
        if not math.isfinite(master_len) or master_len<=0 or master_len>duration+1/FPS: raise ValueError("AAC duration exceeds composition.")
        mastering={"filter":"two-pass FFmpeg loudnorm","target_i_lufs":-16,"target_tp_dbtp":-2,"target_lra_lu":7,
                   "codec":"AAC","bitrate":"192k","sample_rate_hz":48000,"channel_layout":"stereo","gap_seconds":GAP,
                   "first_pass":measured,"second_pass":post,"encoded_duration_seconds":master_len}
        (out/"audio-mastering.json").write_text(json.dumps(mastering,indent=2)+"\n")
        stage["execution"]["output"]={"path":"assets/audio_master.m4a","sha256":sha(master),"first_pass":measured,"second_pass":post}
    ledger.add_artifact(master,"audio_master",rights="operator_supplied_audio_transformed_locally")
    ledger.add_artifact(out/"audio-mastering.json","audio_mastering_receipt",rights="original_measurements")

    node='import {pathToFileURL} from "node:url";const m=await import(pathToFileURL(process.argv[1]));process.stdout.write(m.creatorCreditHtml({embedded:true}));'
    credit=call(["node","--input-type=module","-e",node,str(REPO/"scripts/creator-credit.mjs")],True).stdout
    chapter_data=json.dumps(chapters,ensure_ascii=False,separators=(",",":")); bars="".join(f'<i style="--h:{h}px"></i>' for h in (32,56,39,74,47,63,35,68,43,57,30,50))
    cards="".join(f'<article class="chapter clip" id="{c["id"]}" data-start="{c["start"]:.6f}" data-duration="{c["duration"]:.6f}" data-track-index="{i+1}"><div class="card"><p class="eyebrow">LOCAL AUDIO COMPARISON</p><h1>{c["title"]}</h1><div class="bars" aria-hidden="true">{bars}</div><p class="note">{("Experimental conversion; pronunciation review needed." if i==2 else "Synthetic reference sample; no claim this is Fritz’s voice.")}</p><p class="chapter-counter">{i+1:02d} / 03</p></div></article>' for i,c in enumerate(chapters))
    vtt="WEBVTT\n\n"+"\n\n".join(f'{i+1}\n{vtt_time(c["start"])} --> {vtt_time(c["end"])}\n{c["title"]}' for i,c in enumerate(chapters))+"\n"
    (out/"captions.vtt").write_text(vtt); (out/"chapters.json").write_text(json.dumps(chapters,indent=2,ensure_ascii=False)+"\n")
    html=f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Local voice comparison — FAMtastic</title><meta name="viewport" content="width=1080,initial-scale=1"><link rel="stylesheet" href="style.css"><script src="assets/gsap.min.js"></script></head><body><main id="local-voice-proof" data-composition-id="local-voice-proof" data-width="1080" data-height="1080" data-duration="{duration:.9f}"><header><img class="brand" src="assets/famtastic-designs-logo-v1.png" alt="FAMtastic Designs"><span>LOCAL VOICE PROOF</span></header>{cards}<audio id="comparison-master" class="clip" data-start="0" data-duration="{master_len:.6f}" data-track-index="10" src="assets/audio_master.m4a"></audio><footer>{credit}</footer></main><script>const CHAPTERS={chapter_data};</script><script src="motion.js"></script></body></html>'''
    css='''@font-face{font-family:FAM;src:url("assets/metropolis-regular.woff2")}@font-face{font-family:FAM;src:url("assets/metropolis-bold.woff2");font-weight:700}@font-face{font-family:FAMScript;src:url("assets/kaushan-script.woff2")}*{box-sizing:border-box}html,body{margin:0;width:1080px;height:1080px;overflow:hidden;background:#080b09;color:#f2f3ef;font-family:FAM,Arial,sans-serif}#local-voice-proof{position:relative;width:1080px;height:1080px;background:radial-gradient(ellipse at 50% 38%,#18251c,#0a0e0b 58%,#070907);overflow:hidden}header{position:absolute;z-index:4;top:42px;left:72px;right:72px;display:flex;align-items:center;justify-content:space-between;color:#aab3a8;font-size:17px;letter-spacing:.2em}.brand{width:164px;height:auto}.chapter{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0}.card{position:relative;width:820px;min-height:490px;padding:72px 64px 54px;text-align:center;border:1px solid #526050;border-radius:18px;background:linear-gradient(145deg,#162018,#0c100d);box-shadow:0 28px 100px #0006}.eyebrow{color:#7cfc00;font-weight:700;letter-spacing:.22em;font-size:16px}.card h1{font-size:51px;line-height:1.13;margin:32px 0 44px}.bars{display:flex;align-items:center;justify-content:center;gap:13px;height:82px}.bars i{width:12px;height:var(--h);border-radius:8px;background:linear-gradient(#7cfc00,#1d7d4c);transform-origin:center}.note{max-width:620px;margin:35px auto 0;color:#aab3a8;font-size:17px;line-height:1.5}.chapter-counter{margin:30px 0 0;color:#838c81;font-size:14px;letter-spacing:.2em}footer{position:absolute;bottom:18px;left:0;right:0;height:88px;display:flex;align-items:center;justify-content:center}footer [data-famtastic-creator-credit="v1"]{padding:0!important}'''
    (out/"index.html").write_text(html); (out/"style.css").write_text(css)
    motion='''const tl=gsap.timeline({paused:true});CHAPTERS.forEach((c,i)=>{const root=document.getElementById(c.id),card=root.querySelector(".card");tl.fromTo(root,{opacity:0},{opacity:1,duration:.01},c.start);tl.fromTo(card,{opacity:0,y:18,scale:.985},{opacity:1,y:0,scale:1,duration:.48,ease:"power3.out"},c.start);if(i<CHAPTERS.length-1){tl.to(root,{opacity:0,duration:.22},c.end-.22);tl.set(root,{opacity:0},c.end);}document.querySelectorAll("#"+c.id+" .bars i").forEach((bar,j)=>{const loops=Math.max(0,Math.floor((c.duration-.65)/.76));tl.fromTo(bar,{scaleY:.42},{scaleY:1,duration:.38,repeat:loops,yoyo:true,ease:"sine.inOut"},c.start+j*.012);});});window.__timelines=window.__timelines||{};window.__timelines["local-voice-proof"]=tl;'''
    (out/"motion.js").write_text(motion)
    (out/"index.html").write_text(html.replace('<script src="motion.js"></script>','<script>'+motion+'</script>'))
    provenance={"inputs":[{"frozen_path":f"assets/{n}",**rows[n]} for n in sources],
        "conversion_receipt":({"frozen_path":"provenance/conversion-receipt.json",**receipt} if receipt else {"status":"not_supplied"}),
        "runtime_assets":[{"path":f"assets/{n}","sha256":sha(out/"assets"/n)} for n in copies],
        "creator_credit":{"module":"scripts/creator-credit.mjs","logo_sha256":LOGO_SHA,"markup":"canonical embedded creator-credit row"},
        "comparison_claim":"No claim that converted audio is Fritz's voice.","provider_fee_usd":0,"electricity_and_hardware_cost":"unmeasured"}
    (out/"asset-provenance.json").write_text(json.dumps(provenance,indent=2,ensure_ascii=False)+"\n")
    (out/"hyperframes.json").write_text('{"media":{"autoProxy":false}}\n')
    (out/"index.motion.json").write_text(json.dumps({"duration":duration,"assertions":[{"kind":"appearsBy","selector":f"#{c['id']} .card","bySec":c["start"]+.6} for c in chapters]+[{"kind":"staysInFrame","selector":"footer [data-famtastic-creator-credit=\"v1\"]"}]},indent=2)+"\n")
    files=sorted(f.relative_to(out).as_posix() for f in out.rglob("*") if f.is_file() and f.parent!=out/"run" and "provenance" not in f.parts)
    manifest={"schema":"famtastic.local-hyperframes-project.v1","id":"local-voice-proof","title":"Local voice comparison — synthetic sample",
        "entrypoint":"index.html","width":1080,"height":1080,"fps":FPS,"duration_seconds":duration,"files":files,
        "audio_master":{"path":"assets/audio_master.m4a","mode":"copy","start_seconds":0},
        "brand_logo":{"path":"assets/famtastic-designs-logo-v1.png","sha256":LOGO_SHA},"creator_credit_marker":'data-famtastic-creator-credit="v1"'}
    (out/"project.json").write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+"\n")
    for name,role in (("index.html","hyperframes_composition"),("style.css","composition_styles"),("motion.js","native_gsap_timeline"),("project.json","project_manifest"),("captions.vtt","fixed_chapter_captions"),("chapters.json","chapter_timing"),("asset-provenance.json","asset_provenance"),("audio-mastering.json","audio_mastering_receipt"),("hyperframes.json","runtime_config"),("index.motion.json","motion_assertions")):
        ledger.add_artifact(out/name,role,rights="original_or_frozen_local_input")
    ledger.finalize("partial",qa={"project_built":True,"rendered":False,"human_review":"pending","conversion_receipt":"supplied" if a.receipt else "not supplied","publishing":"not performed"})
    print(json.dumps({"project":str(out),"manifest_sha256":sha(out/"project.json"),"duration_seconds":duration,"chapters":len(chapters),"rendered":False},indent=2))

if __name__=="__main__": main()
