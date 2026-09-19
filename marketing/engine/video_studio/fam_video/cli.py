"""Explicit, inexpensive local production. JSON stdout; failures exit nonzero."""
from __future__ import annotations
import argparse, contextlib, hashlib, json, os, shutil, subprocess, sys, time, uuid, wave, math, struct
from pathlib import Path
from .campaign import load_campaign,load_brand,read_json,sha256,fingerprint,FORMATS,CampaignError


def write_json(path,data):
    path=Path(path);path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp');tmp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n',encoding='utf-8');tmp.replace(path)

def output(data):print(json.dumps(data,indent=2,ensure_ascii=False,default=str))

def new_run(root,campaign_id):
    p=root/'artifacts/video-studio'/f'{campaign_id}-{time.strftime("%Y%m%dT%H%M%SZ",time.gmtime())}-{uuid.uuid4().hex[:8]}'
    p.mkdir(parents=True);return p

def executable_default(root):
    pinned=root/'marketing/engine/video_studio/node_modules/.bin/hyperframes'
    return str(pinned) if pinned.exists() else 'hyperframes'

def valid_cached_evidence(hit,root):
    try:
        p=Path(hit['build_dna']).resolve()
        if not p.is_relative_to(root) or not p.is_file() or sha256(p)!=hit.get('build_dna_sha256'):return False
        dna=read_json(p)
        for a in dna.get('artifacts',[]):
            asset=(root/a['path']).resolve()
            if not asset.is_relative_to(root) or not asset.is_file() or sha256(asset)!=a['sha256']:return False
        return bool(dna.get('artifacts')) and dna.get('completion',{}).get('status')!='failed'
    except (KeyError,OSError,ValueError):return False

def build(c,brand,root,args):
    from .composition import compile_project
    from .adapters import hyperframes
    from .evidence import Ledger
    from .verify import verify_video,contact_sheet
    executable=args.hyperframes or executable_default(root)
    provider=hyperframes.inspect(executable)
    key=fingerprint(c,brand,Path(__file__).parent,{k:provider.get(k) for k in ('executable','version')},args.quality)
    cache=root/'artifacts/video-studio/cache'/f'{key}.json'
    if cache.exists() and not args.no_cache:
        hit=read_json(cache);video=Path(hit['video'])
        if video.is_file() and sha256(video)==hit.get('sha256') and valid_cached_evidence(hit,root):
            proof=verify_video(video,{'width':c['width'],'height':c['height'],'fps':c['fps'],'duration':c['duration'],**({'audio':True} if c.get('audio') else {})})
            if proof.get('passed'):return dict(hit,cache_hit=True)
    run=new_run(root,c['id']);ledger=Ledger(run,root,c)
    try:
        with ledger.stage('compose','designed_motion',provider='local-html',inputs={'format':c['format'],'mode':c.get('mode','motion_graphics')}) as stage:
            composition=compile_project(c,brand,run/'composition',root)
            stage['execution']['output']=composition
        for p in sorted((run/'composition').rglob('*')):
            if p.is_file():ledger.add_artifact(p,'composition_asset',rights='source_declared_or_original')
        video=run/'video.mp4'
        with ledger.stage('render','video_render',provider='local-hyperframes',inputs={'version_probe':provider,'input_fingerprint':key}) as stage:
            rendered=hyperframes.render(run/'composition',video,executable=executable,fps=c['fps'],quality=args.quality,timeout=args.timeout)
            stage['execution']['output']=rendered
        ledger.add_artifact(video,'video_draft',rights='source_declared_or_original')
        for log in (run/'composition').glob('*.log'):ledger.add_artifact(log,'renderer_log',rights='original')
        with ledger.stage('verify','technical_qa',provider='local-ffprobe') as stage:
            expected={'width':c['width'],'height':c['height'],'fps':c['fps'],'duration':c['duration']}
            if c.get('audio'):expected['audio']=True
            proof=verify_video(video,expected)
            write_json(run/'verification.json',proof);stage['execution']['output']=proof
            if not proof.get('passed'):raise RuntimeError('Video verification failed: '+json.dumps(proof.get('failures')))
        ledger.add_artifact(run/'verification.json','technical_qa',rights='original')
        starts=[];t=0
        for s in c['scenes']:starts.append(t+s['duration']*.55);t+=s['duration']
        with ledger.stage('contact-sheet','review_preparation',provider='local-ffmpeg'):
            contact_sheet(video,run/'contact-sheet.jpg',starts if len(starts)<=40 else [starts[round(i*(len(starts)-1)/39)] for i in range(40)])
        ledger.add_artifact(run/'contact-sheet.jpg','visual_review_sheet',rights='source_declared_or_original')
        final=ledger.finalize(status='gated',qa={'technical':'passed','visual':'review_pending','publication':'not_requested'})
        if final.get('completion',{}).get('status')=='failed':raise RuntimeError('Build DNA integrity failed')
        result={'video':str(video),'sha256':sha256(video),'run_dir':str(run),'build_dna':str(ledger.path),'build_dna_sha256':sha256(ledger.path),'format':c['format'],'mode':c.get('mode','motion_graphics'),'duration':c['duration'],'cache_hit':False,'provider_fee_usd':0,'electricity_cost':'not_measured','review':'pending','publish':'not_performed'}
        write_json(cache,result);return result
    except Exception:
        ledger.finalize(status='failed');raise

def create_plan(c,brand,destination):
    destination=Path(destination).resolve()
    if destination.exists():raise CampaignError('Plan destination already exists; choose a new version directory')
    destination.mkdir(parents=True)
    write_json(destination/'campaign.normalized.json',c)
    lines=[f'# {c["title"]}','',f'Mode: {c.get("mode","motion_graphics")}. Duration: {c["duration"]} seconds. Status: draft.', '',brand.get('creative_rule',''),'','| Time | Scene | Layout | Copy | Media |','| --- | --- | --- | --- | --- |']
    t=0;prompts=[]
    for s in c['scenes']:
        lines.append(f'| {t:g}–{t+s["duration"]:g}s | {s["id"]} | {s["layout"]} | {s["headline"].replace(chr(124),"/")} | {s.get("media_kind","designed motion")} |');t+=s['duration']
        if s.get('prompt'):prompts.append({'scene_id':s['id'],'prompt':s['prompt'],'ordinary_action':s.get('ordinary_action'),'impossible_context':s.get('impossible_context'),'status':'unexecuted'})
    lines+=['','Review the claim, exact copy, crop, logo, identity, transitions and audio before use. Model prompts are requests, not generation receipts.']
    (destination/'STORYBOARD.md').write_text('\n'.join(lines)+'\n',encoding='utf-8');write_json(destination/'prompts.json',prompts)
    return {'directory':str(destination),'scenes':len(c['scenes']),'generation_requests':len(prompts)}

def make_audio(destination,duration):
    """Original restrained pulse bed for a technical demo, not narration."""
    destination=Path(destination)
    if destination.exists():raise CampaignError('Audio output exists')
    destination.parent.mkdir(parents=True,exist_ok=True);rate=24000
    with wave.open(str(destination),'wb') as f:
        f.setnchannels(1);f.setsampwidth(2);f.setframerate(rate)
        buf=bytearray()
        for i in range(int(duration*rate)):
            t=i/rate;phase=t%1.5;env=math.exp(-phase*9)*min(1,t*8)*min(1,(duration-t)*3)
            sample=.12*math.sin(2*math.pi*(65*t+5*(1-math.exp(-phase*10))))*env
            buf.extend(struct.pack('<h',int(max(-1,min(1,sample))*32767)))
            if len(buf)>=48000:f.writeframesraw(buf);buf.clear()
        f.writeframes(buf)
    return {'audio':str(destination.resolve()),'duration':duration,'kind':'original_synthetic_demo_pulse','voice':False}

def compare(reference,candidate,destination):
    from .verify import verify_video,contact_sheet
    destination=Path(destination).resolve()
    if destination.exists():raise CampaignError('Comparison destination exists; use a new version')
    destination.mkdir(parents=True)
    reports={}
    for label,path in [('reference',Path(reference).resolve()),('candidate',Path(candidate).resolve())]:
        r=verify_video(path)
        if not r.get('passed'):raise CampaignError(f'{label} is not a valid video')
        duration=r.get('duration_seconds',r.get('duration',0))
        if not duration:raise CampaignError('No usable duration')
        times=[duration*f for f in (.08,.25,.42,.58,.75,.92)]
        contact_sheet(path,destination/f'{label}.jpg',times)
        reports[label]={'sha256':sha256(path),'file':str(path),'technical':r}
    reports['review']={'status':'pending','scores':{k:None for k in ['identity','composition','motion','timing','typography','brand','audio','story']},'scale':'0–5; human review required','acceptance':'No automatic similarity claim. Match intent and craft, not accidental encoding noise.'}
    write_json(destination/'comparison.json',reports)
    (destination/'REVIEW.md').write_text('# Recreation review\n\nWatch both full videos with sound. Contact sheets show six equal relative positions, not inferred cuts.\n\nScore each dimension 0–5 in comparison.json. Check identity, scene intent, pacing, subject motion, typography, logo, sound and crop. Reject identity drift, garbled text, unsupported claims or missing audio. Record actual production time and cost. Technical validity alone does not pass creative review.\n',encoding='utf-8')
    return {'directory':str(destination),'review':'pending'}

def parser(default_brand=None):
    p=argparse.ArgumentParser(description='Local campaign video studio. No paid fallback or publishing.')
    p.add_argument('--repo-root',type=Path,default=Path.cwd());sub=p.add_subparsers(dest='command',required=True)
    doctor=sub.add_parser('doctor');doctor.add_argument('--hyperframes');doctor.add_argument('--mpt-root',type=Path);doctor.add_argument('--mpt-python',default=sys.executable)
    val=sub.add_parser('validate');val.add_argument('campaign',type=Path);val.add_argument('--brand',type=Path,default=default_brand)
    plan=sub.add_parser('plan');plan.add_argument('campaign',type=Path);plan.add_argument('--brand',type=Path,default=default_brand);plan.add_argument('--output',type=Path,required=True)
    for name in ('render','batch'):
        q=sub.add_parser(name);q.add_argument('campaign',type=Path);q.add_argument('--brand',type=Path,default=default_brand);q.add_argument('--format',choices=FORMATS);q.add_argument('--scale',type=float,default=1);q.add_argument('--hyperframes');q.add_argument('--quality',choices=['draft','looks','delivery'],default='draft');q.add_argument('--timeout',type=int,default=1800);q.add_argument('--no-cache',action='store_true')
        if name=='batch':q.add_argument('--formats',nargs='+',choices=FORMATS,default=list(FORMATS))
    q=sub.add_parser('verify');q.add_argument('video',type=Path)
    q=sub.add_parser('compare');q.add_argument('reference',type=Path);q.add_argument('candidate',type=Path);q.add_argument('--output',type=Path,required=True)
    q=sub.add_parser('voice');q.add_argument('script',type=Path);q.add_argument('--output',type=Path,required=True);q.add_argument('--voice-name')
    q=sub.add_parser('demo-audio');q.add_argument('--output',type=Path,required=True);q.add_argument('--duration',type=float,default=12)
    q=sub.add_parser('generate');q.add_argument('--workflow',type=Path,required=True);q.add_argument('--bindings',type=Path,required=True);q.add_argument('--values',type=Path,required=True);q.add_argument('--input-image',type=Path);q.add_argument('--output',type=Path,required=True);q.add_argument('--url',default='http://127.0.0.1:8188');q.add_argument('--timeout',type=int,default=3600)
    q=sub.add_parser('resume');q.add_argument('receipt',type=Path);q.add_argument('--timeout',type=int,default=3600)
    q=sub.add_parser('mpt-draft');q.add_argument('campaign',type=Path);q.add_argument('--mpt-root',type=Path,required=True);q.add_argument('--mpt-python',required=True);q.add_argument('--timeout',type=int,default=1800)
    return p

def main(argv=None,repo_root=None,default_brand=None):
    p=parser(default_brand);args=p.parse_args(argv);root=Path(repo_root or args.repo_root).resolve()
    try:
        if sys.version_info<(3,11):raise RuntimeError('Python 3.11+ required')
        if args.command=='doctor':
            from .doctor import inspect
            from .adapters import hyperframes,moneyprinter
            result=inspect();result['hyperframes']=hyperframes.inspect(args.hyperframes or executable_default(root))
            if args.mpt_root:result['moneyprinter']=moneyprinter.inspect(args.mpt_root,args.mpt_python)
            result['repository_profile']=read_json(root/'marketing/local-models.json').get('machine_profile') if (root/'marketing/local-models.json').exists() else None
            result['profile_note']='Current host observations are distinct from the repository workstation profile.'
            output(result);return 0
        if args.command=='verify':
            from .verify import verify_video
            result=verify_video(args.video);output(result);return 0 if result.get('passed') else 1
        if args.command=='compare':output(compare(args.reference,args.candidate,args.output));return 0
        if args.command=='voice':
            from .audio import voice
            output(voice(args.script,args.output,args.voice_name));return 0
        if args.command=='demo-audio':
            if not math.isfinite(args.duration) or not 0<args.duration<=600:raise CampaignError('duration must be >0 and <=600')
            output(make_audio(args.output,args.duration));return 0
        if args.command in ('generate','resume'):
            from .adapters.comfy import run_workflow,resume_workflow
            from .evidence import Ledger
            if args.command=='generate':
                run=args.output.resolve()
                campaign={'id':'local-generation','workflow_sha256':sha256(args.workflow),'bindings_sha256':sha256(args.bindings),'values':read_json(args.values),'base_url':args.url,'input_sha256':sha256(args.input_image) if args.input_image else None}
                if (run/'build-dna.json').exists():raise CampaignError('Generation run exists; use resume with its receipt')
            else:
                run=args.receipt.resolve().parent
                campaign=read_json(run/'campaign.snapshot.json')
            ledger=Ledger(run,root,campaign)
            try:
                if args.command=='generate':
                    with ledger.stage('freeze-inputs','freeze_generation_sources',provider='local-filesystem'):
                        frozen=run/'inputs';frozen.mkdir()
                        for attribute,name in [('workflow','workflow.api.json'),('bindings','bindings.json'),('values','values.json')]:
                            source=Path(getattr(args,attribute));target=frozen/name;shutil.copyfile(source,target)
                            ledger.add_artifact(target,'generation_'+attribute,rights='operator_supplied')
                            setattr(args,attribute,target)
                        if args.input_image:
                            target=frozen/('reference'+args.input_image.suffix.lower());shutil.copyfile(args.input_image,target)
                            args.input_image=target;ledger.add_artifact(target,'generation_reference',rights='operator_supplied_requires_review')
                with ledger.stage('generation','local_model_generation',provider='local-comfyui',model_status='workflow_declared_not_runtime_verified',inputs=campaign) as stage:
                    if args.command=='generate':result=run_workflow(args.workflow,args.bindings,campaign['values'],run,base_url=args.url,input_image=args.input_image,timeout=args.timeout)
                    else:result=resume_workflow(args.receipt,timeout=args.timeout)
                    stage['execution']['output']=result
                for item in result.get('files',[]):ledger.add_artifact(Path(item['path']),'generated_media',rights='model_and_source_rights_require_review')
                frozen=run/('comfy-receipt-'+uuid.uuid4().hex[:8]+'.json');write_json(frozen,result);ledger.add_artifact(frozen,'provider_receipt',rights='original')
                final=ledger.finalize(status='gated')
                if final.get('completion',{}).get('status')=='failed':raise RuntimeError('Generation evidence integrity failed')
                output(result);return 0
            except Exception:
                ledger.finalize(status='partial');raise
        c=load_campaign(args.campaign,getattr(args,'format',None),getattr(args,'scale',1))
        if args.command=='mpt-draft':
            from .adapters import moneyprinter
            from .evidence import Ledger
            run=new_run(root,c['id']+'-mpt');ledger=Ledger(run,root,c)
            try:
                with ledger.stage('mpt','draft_assembly',provider='local-moneyprinterturbo') as stage:
                    moneyprinter.prepare_batch(c,run/'batch.json')
                    result=moneyprinter.run(args.mpt_root,run/'batch.json',args.mpt_python,run/'output',timeout=args.timeout)
                    stage['execution']['output']=result
                for path in run.rglob('*'):
                    if path.is_file() and path!=ledger.path:ledger.add_artifact(path,'draft_evidence',rights='source_declared')
                ledger.finalize(status='gated');output(result);return 0
            except Exception:ledger.finalize(status='failed');raise
        if not args.brand:raise CampaignError('--brand is required outside the repository wrapper')
        brand=load_brand(args.brand,root)
        if args.command=='validate':output({'passed':True,'id':c['id'],'duration':c['duration'],'scenes':len(c['scenes']),'mode':c.get('mode','motion_graphics')});return 0
        if args.command=='plan':output(create_plan(c,brand,args.output));return 0
        if args.command=='render':output(build(c,brand,root,args));return 0
        if args.command=='batch':
            results=[]
            for fmt in args.formats:
                c=load_campaign(args.campaign,fmt,args.scale);results.append(build(c,brand,root,args))
            output({'outputs':results});return 0
    except (ValueError,RuntimeError,OSError,subprocess.SubprocessError) as e:
        print(json.dumps({'status':'failed','error':str(e)},ensure_ascii=False),file=sys.stderr);return 1
    return 0

if __name__=='__main__':raise SystemExit(main())
