#!/usr/bin/env python3
"""Synthesize the owner script locally with the pinned Kokoro CLI and voice."""
from __future__ import annotations
import argparse, hashlib, json, os, shutil, subprocess, sys, time, wave
from pathlib import Path
ROOT=Path(__file__).resolve().parents[5]
PROJECT=Path(__file__).resolve().parent
SOURCE=PROJECT/'user-script.txt'
NORMALIZATIONS=PROJECT/'voice-tts-normalizations.json'
EXPECTED_SOURCE_SHA='12a242dae7818a836495ebc92bb3ff0274ccddfc230df531c9016a8261c0676f'
EXPECTED_MODEL_SHA='7d5df8ecf7d4b1878015a32686053fd0eebe2bc377234608764cc0ef3636a6c5'
EXPECTED_VOICES_SHA='bca610b8308e8d99f32e6fe4197e7ec01679264efed0cac9140fe9c29f1fbf7d'
VOICE='am_michael'; SPEED='0.78'; LANG='en-us'; VERSION='0.8.29'

def digest(path:Path)->str:
    h=hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda:f.read(1024*1024),b''): h.update(block)
    return h.hexdigest()
def run(argv,**kwargs): return subprocess.run(argv,check=True,text=True,capture_output=True,**kwargs)
def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--output-dir',required=True,help='new repository-local artifact directory; must not exist')
    ap.add_argument('--python',default=os.environ.get('HYPERFRAMES_PYTHON') or sys.executable,help='Python runtime for the pinned HyperFrames local TTS CLI')
    args=ap.parse_args()
    output=(ROOT/args.output_dir).resolve() if not Path(args.output_dir).is_absolute() else Path(args.output_dir).resolve()
    output.relative_to(ROOT)
    if output.exists(): raise SystemExit(f'refusing to overwrite existing output directory: {output.relative_to(ROOT)}')
    lines=[x.strip() for x in SOURCE.read_text(encoding='utf-8').splitlines() if x.strip()]
    manifest=json.loads(NORMALIZATIONS.read_text(encoding='utf-8'))
    if digest(SOURCE)!=EXPECTED_SOURCE_SHA or manifest['source_script_sha256']!=EXPECTED_SOURCE_SHA: raise SystemExit('owner script hash changed; inspect before synthesis')
    if len(lines)!=21 or len(manifest['lines'])!=len(lines): raise SystemExit('expected exactly 21 nonempty source lines and matching normalizations')
    for i,(src,row) in enumerate(zip(lines,manifest['lines'])):
        if row['index']!=i or row['source_text']!=src: raise SystemExit(f'normalization/source binding mismatch at line {i}')
    cache=Path.home()/'.cache/hyperframes/tts'
    model=cache/'models/kokoro-v1.0.onnx'; voices=cache/'voices/voices-v1.0.bin'
    if not model.is_file() or digest(model)!=EXPECTED_MODEL_SHA: raise SystemExit('pinned local Kokoro model is absent or has a different hash; no download attempted')
    if not voices.is_file() or digest(voices)!=EXPECTED_VOICES_SHA: raise SystemExit('pinned local voice data is absent or has a different hash; no download attempted')
    cli=os.environ.get('HYPERFRAMES_CLI') or shutil.which('hyperframes')
    if not cli or not Path(cli).is_file(): raise SystemExit('set HYPERFRAMES_CLI to an already-installed local HyperFrames 0.8.29 executable')
    version_out=run([cli,'--version']).stdout.strip()
    if VERSION not in version_out: raise SystemExit(f'expected installed HyperFrames {VERSION}; got {version_out!r}; no upgrade attempted')
    python=Path(args.python).expanduser().resolve()
    if not python.is_file(): raise SystemExit('the selected local Python runtime does not exist')
    output.mkdir(parents=True)
    started=time.monotonic(); rows=[]
    for row in manifest['lines']:
        i=row['index']; wav_path=output/f'line-{i:02d}.wav'
        argv=['env',f'HYPERFRAMES_PYTHON={python}',cli,'tts',row['tts_text'],'--voice',VOICE,'--speed',SPEED,'--lang',LANG,'--output',str(wav_path),'--json']
        call_start=time.monotonic(); p=run(argv,cwd=ROOT); elapsed=time.monotonic()-call_start
        log=output/f'line-{i:02d}.log'; log.write_text(p.stdout+'\n--- STDERR ---\n'+p.stderr,encoding='utf-8')
        if not wav_path.is_file(): raise SystemExit(f'TTS CLI did not create line {i:02d} WAV')
        with wave.open(str(wav_path),'rb') as wf:
            media={'sample_rate_hz':wf.getframerate(),'channels':wf.getnchannels(),'sample_width_bytes':wf.getsampwidth(),'frames':wf.getnframes(),'duration_seconds':wf.getnframes()/wf.getframerate()}
        cli_json=[x for x in p.stdout.splitlines() if x.strip().startswith('{')]
        rows.append({'index':i,'source_text':row['source_text'],'tts_text':row['tts_text'],'command':argv,'wall_seconds':round(elapsed,3),'wav_path':wav_path.relative_to(ROOT).as_posix(),'wav_sha256':digest(wav_path),'media':media,'log_path':log.relative_to(ROOT).as_posix(),'cli_receipt':json.loads(cli_json[-1]) if cli_json else None})
    if digest(model)!=EXPECTED_MODEL_SHA or digest(voices)!=EXPECTED_VOICES_SHA: raise SystemExit('local model or voice data changed while generating')
    receipt={'schema':'famtastic.no-catch.local-voice-reproduction.v1','provider':'local Kokoro-ONNX only','voice':VOICE,'speed':float(SPEED),'language':LANG,'hyperframes_cli_version':VERSION,'hyperframes_python':str(python),'source_script_sha256':digest(SOURCE),'normalizations_sha256':digest(NORMALIZATIONS),'model':{'id':'hexgrad/Kokoro-82M v1.0 ONNX full precision','sha256':digest(model),'license':'Apache-2.0','source':'https://github.com/thewh1teagle/kokoro-onnx/releases/tag/model-files-v1.0'},'voice_data':{'sha256':digest(voices),'source':'https://github.com/thewh1teagle/kokoro-onnx/releases/tag/model-files-v1.0'},'local_cli_version_output':version_out,'line_count':len(rows),'elapsed_seconds':round(time.monotonic()-started,3),'lines':rows,'provider_fee_usd':0}
    (output/'synthesis-receipts.json').write_text(json.dumps(receipt,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(json.dumps({'output_dir':output.relative_to(ROOT).as_posix(),'line_count':len(rows),'elapsed_seconds':receipt['elapsed_seconds'],'receipt':'synthesis-receipts.json'},indent=2))
if __name__=='__main__': main()
