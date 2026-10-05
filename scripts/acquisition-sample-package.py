#!/usr/bin/env python3
"""Create/verify a candidate immutable source bundle. Never deploys or approves."""
import argparse, hashlib, json, pathlib, shutil, subprocess
ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = pathlib.Path('marketing/campaigns/acquisition-199')
IDS = ('beauty_editorial','beauty_service_first','detailing_precision','detailing_route_ready','baking_signature','catering_table_story')
FILES = [BASE/'messages.json', BASE/'assets/connect-qr.png'] + [BASE/'templates'/f'{i}.html' for i in IDS] + [BASE/'assets'/f'{i}-preview.jpg' for i in IDS]
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
p=argparse.ArgumentParser();p.add_argument('mode',choices=['build','verify']);p.add_argument('--bundle',required=True,type=pathlib.Path);a=p.parse_args();bundle=a.bundle.resolve()
if a.mode=='build':
    if bundle.exists(): raise SystemExit('Bundle destination already exists; immutable bundles are never overwritten.')
    for rel in FILES:
        source=ROOT/rel
        if not source.is_file() or source.is_symlink() or source.stat().st_size>1048576: raise SystemExit(f'Unsafe/missing source: {rel}')
    bundle.mkdir(parents=True)
    for rel in FILES:
        target=bundle/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/rel,target)
    manifest={'schema':'famtastic.acquisition-source-bundle.v1','classification':'candidate_source_only','source_commit':subprocess.check_output(['git','-C',str(ROOT),'rev-parse','HEAD'],text=True).strip(),'approvals_granted':False,'files':{str(rel):sha(bundle/rel) for rel in FILES}}
    (bundle/'manifest.json').write_text(json.dumps(manifest,indent=2,sort_keys=True)+'\n')
manifest=json.loads((bundle/'manifest.json').read_text())
if manifest.get('schema')!='famtastic.acquisition-source-bundle.v1' or set(manifest['files'])!=set(map(str,FILES)): raise SystemExit('Unexpected bundle manifest.')
for rel,digest in manifest['files'].items():
    path=bundle/rel
    if not path.is_file() or path.is_symlink() or not path.resolve().is_relative_to(bundle) or sha(path)!=digest: raise SystemExit(f'Bundle integrity failed: {rel}')
print(json.dumps({'status':'verified_candidate_source','manifest_sha256':sha(bundle/'manifest.json'),'file_count':len(FILES),'owner_approval':False,'deployed':False},indent=2))
