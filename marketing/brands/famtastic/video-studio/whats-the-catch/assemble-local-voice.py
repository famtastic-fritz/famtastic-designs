#!/usr/bin/env python3
"""Assemble the 21 line WAVs, bind script cues, loudness-normalize, and encode AAC."""
from __future__ import annotations
import argparse, hashlib, json, re, subprocess, wave
from pathlib import Path
ROOT=Path(__file__).resolve().parents[5]
PROJECT=Path(__file__).resolve().parent
SOURCE=PROJECT/'user-script.txt'; NORMALIZATIONS=PROJECT/'voice-tts-normalizations.json'
GAPS=[0.35,0.55,0.30,0.35,0.60,0.42,0.35,0.35,0.45,0.35,0.30,0.45,0.35,0.30,0.35,0.55,0.40,0.50,0.35,0.55,0.0]
FFMPEG='/opt/homebrew/bin/ffmpeg'; FFPROBE='/opt/homebrew/bin/ffprobe'
def digest(path):
    h=hashlib.sha256()
    with Path(path).open('rb') as f:
        for block in iter(lambda:f.read(1024*1024),b''): h.update(block)
    return h.hexdigest()
def run(argv,log):
    p=subprocess.run(argv,cwd=ROOT,text=True,capture_output=True)
    with Path(log).open('a',encoding='utf-8') as f: f.write('$ '+json.dumps(argv)+'\n'+p.stdout+'\n'+p.stderr+'\n')
    if p.returncode: raise SystemExit(f'local media command failed: {argv[0]} (exit {p.returncode}); see {log}')
    return p.stdout,p.stderr
def main():
    ap=argparse.ArgumentParser(description=__doc__); ap.add_argument('--audio-dir',required=True,help='directory created by synthesize-local-voice.py')
    args=ap.parse_args(); folder=(ROOT/args.audio_dir).resolve() if not Path(args.audio_dir).is_absolute() else Path(args.audio_dir).resolve(); folder.relative_to(ROOT)
    receipt=json.loads((folder/'synthesis-receipts.json').read_text(encoding='utf-8'))
    src=[x.strip() for x in SOURCE.read_text(encoding='utf-8').splitlines() if x.strip()]
    norm=json.loads(NORMALIZATIONS.read_text(encoding='utf-8'))
    if digest(SOURCE)!=receipt['source_script_sha256'] or receipt['normalizations_sha256']!=digest(NORMALIZATIONS): raise SystemExit('script/normalization hashes do not match synthesis receipt')
    if len(src)!=21 or receipt['line_count']!=21: raise SystemExit('expected all 21 source lines')
    log=folder/'assembly-commands.log'; log.write_text('',encoding='utf-8')
    pre=folder/'narration-before-loudnorm.wav'; final=folder/'narration.wav'; m4a=folder/'narration.m4a'
    cues=[]; line_inputs=[]; total=0; speech=0; gap_frames=0
    if any(p.exists() for p in [pre,final,m4a,folder/'cues.json']): raise SystemExit('refusing to overwrite existing assembled audio; choose a fresh output directory')
    with wave.open(str(pre),'wb') as out:
        out.setnchannels(1);out.setsampwidth(2);out.setframerate(24000)
        for i,text in enumerate(src):
            path=folder/f'line-{i:02d}.wav'; row=receipt['lines'][i]
            if row['index']!=i or row['source_text']!=text or digest(path)!=row['wav_sha256']: raise SystemExit(f'line {i} source/hash binding failed')
            with wave.open(str(path),'rb') as inp:
                if (inp.getnchannels(),inp.getsampwidth(),inp.getframerate())!=(1,2,24000): raise SystemExit(f'line {i} audio format mismatch')
                frames=inp.getnframes(); raw=inp.readframes(frames)
            start=total/24000; out.writeframesraw(raw); total+=frames; speech+=frames; end=total/24000
            cues.append({'index':i,'text':text,'source_text':text,'start':round(start,6),'end':round(end,6)})
            line_inputs.append({'index':i,'wav_path':path.relative_to(ROOT).as_posix(),'sha256':row['wav_sha256'],'duration_seconds':frames/24000})
            if i<20:
                gf=round(GAPS[i]*24000);out.writeframesraw(b'\0\0'*gf);total+=gf;gap_frames+=gf
    cues_path=folder/'cues.json'; cues_path.write_text(json.dumps(cues,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    target_i=-16.0;target_tp=-2.0;target_lra=7.0
    analysis=[FFMPEG,'-hide_banner','-nostats','-i',str(pre),'-af',f'loudnorm=I={target_i}:TP={target_tp}:LRA={target_lra}:print_format=json','-f','null','-']
    _,stderr=run(analysis,log); blocks=re.findall(r'\{\s*"input_i".*?\}',stderr,re.S)
    if not blocks: raise SystemExit('unable to parse first-pass loudness analysis')
    measured=json.loads(blocks[-1]); filt=(f"loudnorm=I={target_i}:TP={target_tp}:LRA={target_lra}:measured_I={measured['input_i']}:measured_TP={measured['input_tp']}:measured_LRA={measured['input_lra']}:measured_thresh={measured['input_thresh']}:offset={measured['target_offset']}:linear=false:print_format=json")
    normalize=[FFMPEG,'-hide_banner','-nostats','-y','-i',str(pre),'-af',filt,'-ar','24000','-ac','1','-c:a','pcm_s16le',str(final)]
    _,normerr=run(normalize,log); blocks=re.findall(r'\{\s*"input_i".*?\}',normerr,re.S)
    second=json.loads(blocks[-1]) if blocks else {}
    encode=[FFMPEG,'-hide_banner','-nostats','-y','-i',str(final),'-vn','-c:a','aac','-b:a','128k','-ar','24000','-ac','1','-movflags','+faststart',str(m4a)]
    run(encode,log)
    probe_cmd=[FFPROBE,'-v','error','-show_streams','-show_format','-of','json',str(m4a)]; probeout,_=run(probe_cmd,log);probe=json.loads(probeout)
    meter_cmd=[FFMPEG,'-hide_banner','-nostats','-i',str(m4a),'-af','ebur128=framelog=quiet:peak=true','-f','null','-'];_,metererr=run(meter_cmd,log)
    integrated=re.findall(r'Integrated loudness:\s+I:\s+(-?[0-9.]+) LUFS',metererr,re.S);peaks=re.findall(r'True peak:.*?Peak:\s+(-?[0-9.]+) dBFS',metererr,re.S)
    meta={'schema':'famtastic.no-catch.local-voice-assembly.v1','source_script_sha256':digest(SOURCE),'normalizations_sha256':digest(NORMALIZATIONS),'synthesis_receipt_sha256':digest(folder/'synthesis-receipts.json'),'line_count':21,'speech_duration_seconds':speech/24000,'gap_duration_seconds':gap_frames/24000,'duration_seconds':total/24000,'sample_rate_hz':24000,'channels':1,'cues_path':cues_path.relative_to(ROOT).as_posix(),'cues_sha256':digest(cues_path),'line_inputs':line_inputs,'pre_normalized_sha256':digest(pre),'normalized_wav_sha256':digest(final),'m4a_sha256':digest(m4a),'m4a_duration_seconds':float(probe['format']['duration']),'loudnorm':{'targets':{'I':target_i,'TP':target_tp,'LRA':target_lra},'first_pass':measured,'second_pass':second,'final_m4a_lufs':float(integrated[-1]) if integrated else None,'final_m4a_true_peak_dbtp':float(peaks[-1]) if peaks else None,'filter':filt},'ffprobe':probe,'provider_fee_usd':0,'final_hold_seconds':0}
    (folder/'assembly-receipt.json').write_text(json.dumps(meta,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps({k:meta[k] for k in ('duration_seconds','speech_duration_seconds','gap_duration_seconds','normalized_wav_sha256','m4a_sha256','m4a_duration_seconds','loudnorm')},indent=2))
if __name__=='__main__':main()
