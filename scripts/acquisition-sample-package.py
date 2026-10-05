#!/usr/bin/env python3
"""Create/verify a candidate immutable source bundle. Never deploys or approves."""
import argparse, hashlib, json, pathlib, shutil, subprocess
ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = pathlib.Path('marketing/campaigns/acquisition-199')
IDS = ('beauty_editorial','beauty_service_first','detailing_precision','detailing_route_ready','baking_signature','catering_table_story')
HISTORICAL_FILES = [BASE/'messages.json', BASE/'assets/connect-qr.png'] + [BASE/'templates'/f'{i}.html' for i in IDS] + [BASE/'assets'/f'{i}-preview.jpg' for i in IDS]
APPROVAL = pathlib.Path('docs/research/acquisition-199/CREATIVE-APPROVAL.json')
GENERIC_FILES = [APPROVAL] + [BASE/'generic-review'/name for name in ('beauty-email.html', 'beauty-email.txt', 'beauty-template.html', 'beauty-lab.html', 'beauty-lab.css', 'beauty-practice.js', 'assets/hair-studio.jpg', 'assets/connect-qr.png', 'assets/famtastic-designs-logo-v1.png')]
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
p=argparse.ArgumentParser();p.add_argument('mode',choices=['build','verify']);p.add_argument('--bundle',required=True,type=pathlib.Path);p.add_argument('--scope',choices=['historical','generic-d0'],default='historical');a=p.parse_args();bundle=a.bundle.resolve()
FILES = GENERIC_FILES if a.scope == 'generic-d0' else HISTORICAL_FILES
def valid_size(rel, path):
    return path.stat().st_size <= (2097152 if a.scope == 'generic-d0' and rel == BASE/'generic-review/assets/famtastic-designs-logo-v1.png' else 1048576)
if a.mode=='build':
    if bundle.exists(): raise SystemExit('Bundle destination already exists; immutable bundles are never overwritten.')
    for rel in FILES:
        source=ROOT/rel
        if not source.is_file() or source.is_symlink() or not valid_size(rel, source): raise SystemExit(f'Unsafe/missing source: {rel}')
    bundle.mkdir(parents=True)
    for rel in FILES:
        target=bundle/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/rel,target)
    manifest={'schema':'famtastic.acquisition-source-bundle.v1','classification':'candidate_source_only','source_commit':subprocess.check_output(['git','-C',str(ROOT),'rev-parse','HEAD'],text=True).strip(),'approvals_granted':False,'files':{str(rel):sha(bundle/rel) for rel in FILES}}
    if a.scope == 'generic-d0': manifest['scope'] = 'generic-d0'
    (bundle/'manifest.json').write_text(json.dumps(manifest,indent=2,sort_keys=True)+'\n')
manifest=json.loads((bundle/'manifest.json').read_text())
if manifest.get('schema')!='famtastic.acquisition-source-bundle.v1' or set(manifest['files'])!=set(map(str,FILES)): raise SystemExit('Unexpected bundle manifest.')
if a.scope == 'generic-d0' and manifest.get('scope') != 'generic-d0': raise SystemExit('Unexpected generic scope.')
for rel,digest in manifest['files'].items():
    path=bundle/rel
    if not path.is_file() or path.is_symlink() or not path.resolve().is_relative_to(bundle) or not valid_size(pathlib.Path(rel), path) or sha(path)!=digest: raise SystemExit(f'Bundle integrity failed: {rel}')
if a.scope == 'generic-d0':
    approval=json.loads((bundle/APPROVAL).read_text())
    if approval.get('schema') != 'famtastic.acquisition-creative-approval.v1' or approval.get('status') != 'approved' or approval.get('approved_by') != 'Fritz Medine': raise SystemExit('Approved generic creative receipt required.')
    for rel in GENERIC_FILES[1:]:
        if approval.get('artifact_sha256', {}).get(str(rel)) != sha(bundle/rel): raise SystemExit(f'Approved artifact drift: {rel}')
print(json.dumps({'status':'verified_candidate_source','scope':a.scope,'manifest_sha256':sha(bundle/'manifest.json'),'file_count':len(FILES),'owner_approval':False,'creative_approval':a.scope == 'generic-d0','dispatch_authorized':False,'deployed':False},indent=2))
