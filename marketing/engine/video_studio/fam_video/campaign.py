"""Validate and normalize campaign inputs before any provider work."""
from __future__ import annotations
import copy, hashlib, json, math, re
from pathlib import Path

FORMATS = {'9:16': (1080,1920), '4:5': (1080,1350), '1:1': (1080,1080), '16:9': (1920,1080)}
LAYOUTS = {'signal','split','monument','resolve'}
SLUG = re.compile(r'^[a-z0-9][a-z0-9-]{0,79}$')
COLOR = re.compile(r'^#[0-9a-fA-F]{6}$')

class CampaignError(ValueError): pass

def sha256(path):
    h=hashlib.sha256()
    with Path(path).open('rb') as f:
        for chunk in iter(lambda:f.read(1024*1024),b''): h.update(chunk)
    return h.hexdigest()

def read_json(path):
    try: return json.loads(Path(path).read_text(encoding='utf-8'))
    except (OSError,ValueError) as e: raise CampaignError(f'Cannot read JSON {path}: {e}') from e

def local_file(value, base):
    if not isinstance(value,str) or not value or '://' in value: raise CampaignError('Assets must be local file paths')
    p=(Path(base)/value).expanduser().resolve()
    if not p.is_file(): raise CampaignError(f'Missing local asset: {p}')
    return str(p)

def text(value,name,limit,required=False):
    if not isinstance(value,str) or len(value)>limit or (required and not value.strip()):
        raise CampaignError(f'{name} must be text, max {limit} characters'+(' and nonempty' if required else ''))
    return value

def load_campaign(path, format_override=None, scale=1.0):
    path=Path(path).resolve(); c=read_json(path)
    if not isinstance(c,dict) or c.get('schema')!='video-studio.campaign.v1': raise CampaignError('Expected video-studio.campaign.v1')
    if not SLUG.fullmatch(str(c.get('id',''))): raise CampaignError('Campaign id must be a lowercase public slug')
    text(c.get('title'),'title',160,True)
    if c.get('mode','motion_graphics') not in {'motion_graphics','editorial','ai_footage'}: raise CampaignError('Invalid mode')
    fmt=format_override or c.get('format','9:16')
    if fmt not in FORMATS: raise CampaignError(f'Unsupported format {fmt}')
    if type(c.get('fps',30)) is not int or c.get('fps',30) not in (24,25,30,60): raise CampaignError('fps must be 24,25,30,60')
    if not isinstance(scale,(int,float)) or not math.isfinite(scale) or not 0.3<=scale<=1: raise CampaignError('Scale must be 0.3–1')
    c=copy.deepcopy(c); c['fps']=c.get('fps',30); c['format']=fmt
    c['width'],c['height']=[max(2,round(d*scale/2)*2) for d in FORMATS[fmt]]
    scenes=c.get('scenes'); ids=set(); duration=0
    if not isinstance(scenes,list) or not 1<=len(scenes)<=60: raise CampaignError('Use 1–60 scenes')
    for s in scenes:
        if not isinstance(s,dict) or not SLUG.fullmatch(str(s.get('id',''))) or s['id'] in ids: raise CampaignError('Scene IDs must be unique public slugs')
        ids.add(s['id']); d=s.get('duration')
        if type(d) not in (int,float) or not math.isfinite(d) or not 0.5<=d<=60: raise CampaignError('Scene durations must be 0.5–60 seconds')
        if abs(d*c['fps']-round(d*c['fps']))>1e-6: raise CampaignError('Scene duration must align to whole frames')
        duration+=d
        if s.get('layout') not in LAYOUTS: raise CampaignError('Use signal, split, monument, or resolve layout')
        text(s.get('headline'),'headline',100,True)
        for field,limit in [('body',240),('eyebrow',80),('prompt',4000),('ordinary_action',200),('impossible_context',300)]:
            if field in s:text(s[field],field,limit)
        for field in ('accent','background'):
            if field in s and not COLOR.fullmatch(s[field]): raise CampaignError(f'{field} must be #RRGGBB')
        if 'focal_point' in s and not re.fullmatch(r'(?:100|\d{1,2})(?:\.\d+)?% (?:100|\d{1,2})(?:\.\d+)?%',s['focal_point']):raise CampaignError('Use percentage focal_point, e.g. 50% 50%')
        if s.get('media_kind') and not s.get('media'):raise CampaignError('media_kind requires a media path')
        if c.get('mode')=='ai_footage' and s.get('media_kind')!='video':raise CampaignError('ai_footage requires actual supplied video clips; stills belong in editorial')
        if 'media' in s:
            s['media']=local_file(s['media'],path.parent)
            if s.get('media_kind') not in ('image','video'): raise CampaignError('media_kind required: image or video')
            if s.get('rights') not in ('owned','licensed','generated','public_domain'):raise CampaignError('Media needs declared rights: owned, licensed, generated or public_domain')
        elif c.get('mode') in ('editorial','ai_footage'):raise CampaignError(f"{s['id']}: media required for {c['mode']}; no silent substitute")
        trim=s.get('trim_start',0)
        if type(trim) not in (int,float) or not math.isfinite(trim) or trim<0:raise CampaignError('trim_start must be finite and nonnegative')
    if duration>600:raise CampaignError('Campaign limit is ten minutes')
    c['duration']=round(duration,6)
    captions=c.get('captions',[])
    if not isinstance(captions,list) or len(captions)>1000:raise CampaignError('captions must be a list of up to 1000 cues')
    previous_end=0
    for cue in captions:
        if not isinstance(cue,dict):raise CampaignError('Caption cue must be an object')
        text(cue.get('text'),'caption.text',180,True)
        start,end=cue.get('start'),cue.get('end')
        if any(type(t) not in (int,float) or not math.isfinite(t) for t in (start,end)) or not 0<=start<end<=duration or start<previous_end:raise CampaignError('Caption times must be ordered, nonoverlapping, and inside the video')
        previous_end=end
    if 'audio' in c:c['audio']=local_file(c['audio'],path.parent)
    if 'script' in c:text(c['script'],'script',20000)
    from .verify import verify_video
    for scene in c['scenes']:
        if scene.get('media_kind')=='video':
            probe=verify_video(Path(scene['media']))
            if not probe['passed']:raise CampaignError('Invalid scene video: '+scene['id'])
            if probe['audio'] and not c.get('audio') and scene.get('source_audio')!='discard':raise CampaignError('Source contains audio: provide campaign.audio soundtrack or explicitly set scene.source_audio=discard: '+scene['id'])
            if scene.get('trim_start',0)+scene['duration']>probe['duration_seconds']+1/c['fps']:raise CampaignError('Source clip too short for trim and scene duration: '+scene['id'])
    c['_source_path']=str(path)
    return c

def load_brand(path, repo_root):
    b=read_json(path)
    if not isinstance(b,dict):raise CampaignError('Brand must be an object')
    text(b.get('name'),'brand.name',100,True)
    url=b.get('url','')
    if not re.fullmatch(r'https://[A-Za-z0-9.-]+(?::\d+)?(?:/[A-Za-z0-9/_.?=&%-]*)?',url):raise CampaignError('Brand URL must be a public HTTPS URL without credentials')
    for key in ('logo','display_font','body_font','signature_font','credit_module'):
        if key in b:b[key]=local_file(b[key],repo_root)
    if not b.get('logo') or sha256(b['logo'])!=b.get('logo_sha256'):raise CampaignError('Brand logo SHA-256 mismatch')
    for v in b.get('palette',{}).values():
        if not isinstance(v,str) or not COLOR.fullmatch(v):raise CampaignError('Brand palette colors must be #RRGGBB')
    return b

def fingerprint(campaign,brand,engine_root,provider_version,quality):
    inputs={'campaign':campaign,'brand':brand,'provider_version':provider_version,'quality':quality,'assets':{},'engine':{}}
    for s in campaign['scenes']:
        if 'media'in s:inputs['assets'][s['media']]=sha256(s['media'])
    for p in [campaign.get('audio')]+[brand.get(k) for k in ('logo','display_font','body_font','signature_font','credit_module')]:
        if p:inputs['assets'][p]=sha256(p)
    credit_module=brand.get('credit_module')
    if credit_module:
        # The canonical renderer imports these repo-owned style constants.
        credit_dependency=Path(credit_module).parent.parent/'frontend/src/lib/creatorCredit.js'
        if credit_dependency.is_file():inputs['assets'][str(credit_dependency)]=sha256(credit_dependency)
    for p in sorted(Path(engine_root).rglob('*.py')):inputs['engine'][str(p.relative_to(engine_root))]=sha256(p)
    return hashlib.sha256(json.dumps(inputs,sort_keys=True).encode()).hexdigest()
