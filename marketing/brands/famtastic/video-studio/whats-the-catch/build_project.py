#!/usr/bin/env python3
"""Stage an original, supplied-script film using a declared local AAC narrator.

No fetch, provider call, publishing or email operation occurs here. Assets,
spoken cue times and brand bytes are frozen in an explicit authored-project manifest.
"""
from __future__ import annotations
import argparse,hashlib,html,json,math,shutil,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
REPO=HERE.parents[4]
LOGO_SHA='ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950'
GSAP_SHA='c174bfce53a729418d57a8ad8625e7247c793a22fef8e2851e3cfa3de9cd8280'
GROUPS=[(0,1,'dark'),(2,2,'paper'),(3,4,'forest'),(5,9,'paper'),(10,10,'dark'),(11,11,'paper'),(12,13,'dark'),(14,14,'paper'),(15,15,'forest'),(16,16,'paper'),(17,20,'dark')]

def sha(p):
    with Path(p).open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()
def line(text,cls=''):
    return f'<span class="line {cls}">{text}</span>'
def svg(content,view='0 0 800 600'):
    return f'<svg viewBox="{view}" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">{content}</svg>'
def block(x,y,w,h,label='',cls='build-block'):
    d=45; fill=(f'<polygon class="block-top" points="{x},{y} {x+d},{y-d} {x+w+d},{y-d} {x+w},{y}"/>'
    f'<polygon class="block-side" points="{x+w},{y} {x+w+d},{y-d} {x+w+d},{y+h-d} {x+w},{y+h}"/>'
    f'<rect class="block-front" x="{x}" y="{y}" width="{w}" height="{h}"/>')
    if label:fill+=f'<text x="{x+w/2}" y="{y+h/2+10}" text-anchor="middle" style="fill:#f4f1ea;font-size:28px">{html.escape(label)}</text>'
    return f'<g class="{cls}">{fill}</g>'
def scopes():
    return '''<div class="scope" data-pop><h3>The $199 starter foundation</h3><div class="scope-row"><strong>One focused website</strong>Built around your business.</div><div class="scope-row"><strong>12 months of basic managed hosting</strong>A clear first year.</div><div class="scope-row"><strong>Domain setup</strong>Connect yours, or one standard available first-year domain if needed.</div><div class="renewal">After year one: $9.99/month hosting with separate authorization. Domain renewal is separate. Optional services are quoted separately.</div></div>'''
def scenes():
    foundation=svg(block(84,475,590,75,'YOUR FOUNDATION')+block(142,330,460,110,'THE FIRST STEP')+block(198,145,343,144,'YOUR VISION'))
    steps=''
    for i in range(5):
        x=100+i*147; y=460-i*74
        steps+=f'<g class="value-step"><rect x="{x}" y="{y}" width="140" height="{500-y}" fill="{["#d3dfca","#b8d0ad","#8db180","#55864e","#1f6f4a"][i]}" stroke="#1f6f4a" stroke-width="2"/><path d="M{x},{y}l24,-24h140l-24,24" fill="#e2eada" stroke="#1f6f4a" stroke-width="2"/></g>'
    steps+='<path class="value-path" d="M80 430L240 350L390 276L540 202L704 127" stroke="#1f6f4a" fill="none" stroke-width="8"/><circle class="value-point" cx="704" cy="127" r="19" fill="#d9a441"/>'
    growth='<path d="M10 327H1540" stroke="#bcd0b7" stroke-width="3"/>'
    for i,(x,w) in enumerate([(190,290),(670,290),(1150,290)]):
        for j in range(i+2):
            growth+=f'<g class="growth-level"><rect x="{x}" y="{274-j*65}" width="{w}" height="60" rx="3" fill="{["#a4bf8f","#cfdcbb","#d9a441"][i]}"/><path d="M{x+15} {294-j*65}h{w-30}" stroke="#193b2b" stroke-width="4"/></g>'
    memory=svg(block(70,465,480,75,'THE BEGINNING')+block(140,334,345,95,'YOUR BUSINESS')+block(205,155,215,134,'YOUR VISION'), '0 0 700 600')
    strategy=svg('<path d="M65 114H394M227 44V184" stroke="#78966f" stroke-width="5"/><rect x="35" y="75" width="100" height="78" rx="5" fill="#1f6f4a"/><rect x="177" y="17" width="100" height="60" rx="5" fill="#afc69f"/><rect x="177" y="158" width="100" height="60" rx="5" fill="#afc69f"/><rect x="319" y="75" width="100" height="78" rx="5" fill="#d9a441"/><path d="M61 111l15 15 29-33M345 111l15 15 29-33" stroke="#f4f1ea" fill="none" stroke-width="7"/>','0 0 455 228')
    return [
      '<div class="left-copy"><p class="eyebrow" data-pop>THE $199 SPECIAL</p><h1 class="question" data-pop>'+line("What’s the")+line('catch?','gold')+'</h1><p class="body muted" style="margin-top:44px" data-pop>A fair question.<span class="line">A different philosophy.</span></p></div><div class="ticket" data-pop><p class="ticket-label">START YOUR WEB PRESENCE</p><div class="price">$199</div><div class="ticket-rule"></div><div class="ticket-foot">One starter website.<span class="line">One year of hosting.</span></div><div class="ticket-bottom">THE BEGINNING OF SOMETHING.</div></div>',
      '<p class="eyebrow" data-pop>CLARITY FROM THE START</p><h2 class="answer-title" data-pop>'+line('No hidden')+line('fees.','accent')+'</h2><p class="answer-line" data-fraction="0.22">No surprise add-ons.</p><p class="body" style="position:absolute;left:0;top:432px;width:785px" data-fraction="0.52">Know the scope.<span class="line">Know the terms.</span></p>'+scopes(),
      '<div class="philosophy"><p class="eyebrow" data-pop>UNDERSTAND THE NAME</p><h2 class="meaning" data-pop>'+line('FAMtastic is a')+line('philosophy.','gold')+'</h2><div class="foundation-rule"></div><p class="philosophy-note" data-line="4">BELIEF COMES FIRST.</p></div>',
      '<h2 class="belief-title" data-pop>We believe in <span class="line script">you.</span></h2><div class="belief-cards"><div class="belief-card" data-line="6"><p class="number">01 / SEE IT</p><p class="word">Your vision.</p></div><div class="belief-card" data-line="7"><p class="number">02 / CHASE IT</p><p class="word">Your hustle.</p></div><div class="belief-card" data-line="8"><p class="number">03 / BUILD IT</p><p class="word">Your grind.</p></div></div><p class="belief-footer" data-line="9">THE BUSINESS YOU’RE TRYING TO BUILD.</p>',
      '<div class="left-copy"><p class="eyebrow" data-pop>THE FIRST MOVE</p><h2 class="vision-copy" data-pop>'+line('Your vision.')+line('First.','gold')+'</h2><p class="vision-small muted" data-fraction="0.48">A strong beginning.<span class="line">Room to grow.</span></p></div><div class="foundation-art">'+foundation+'</div>',
      '<p class="eyebrow" data-pop>MORE THAN A STARTING POINT</p><h2 class="triptych-title" data-pop>Build a stronger business.</h2><div class="triptych"><div class="tool-card" data-fraction="0"><div class="object"><div class="address">YOURBUSINESS.COM</div><div class="web-word">Your work.<span class="line">Your story.</span></div><div class="web-stripes"><span></span><span></span><span></span></div><span class="object-label">WEBSITE ILLUSTRATION</span></div><h3>A web presence.</h3><p>A place for your business.</p></div><div class="tool-card" data-fraction="0.28"><div class="object"><div class="plan-line">Your direction</div><div class="plan-line">Your priorities</div><div class="plan-line">Your next step</div></div><h3>A plan.</h3><p>Turn intention into direction.</p></div><div class="tool-card" data-fraction="0.49"><div class="object">'+strategy+'</div><h3>Tools + strategy.</h3><p>Build from a stronger foundation.</p></div></div>',
      '<p class="eyebrow" data-pop>A FAIR QUESTION</p><div class="why" data-pop>Why?</div><h2 class="confidence" data-line="13">Because we’re<span class="line accent">confident</span>in what we do.</h2><div class="confidence-line"></div>',
      '<div class="left-copy"><p class="eyebrow" data-pop>LET US EARN IT</p><h2 class="next-title" data-pop>'+line('The next')+line('level.','accent')+'</h2><p class="next-copy" data-fraction="0.48">Let us prove our value<span class="line">with results.</span></p></div><div class="step-art">'+svg(steps,'0 0 940 540')+'</div>',
      '<p class="eyebrow" data-pop>YOUR BUSINESS, MOVING FORWARD</p><div class="ready" data-fraction="0.56">WHEN YOU’RE READY</div><h2 class="growth-head" style="margin-top:111px"><span data-fraction="0">Build.</span> <span class="accent" data-fraction="0.23">Grow.</span> <span class="gold" data-fraction="0.56">Evolve.</span></h2><div class="growth-floor">'+svg(growth,'0 0 1556 335')+'</div>',
      '<p class="eyebrow" data-pop>REMEMBER THE BEGINNING</p><h2 class="remember-title" data-pop>'+line('The first')+line('believer.','accent')+'</h2><p class="remember-note" data-fraction="0.27">In you. In your vision.</p><div class="memory-art">'+memory+'</div>',
      '<p class="closing-note" data-pop>THAT’S THE PHILOSOPHY.</p><div class="shared"><div class="you accent" data-line="18">You grow.</div><div class="we" data-line="18">We grow.</div></div><img class="closing-logo" data-line="19" src="assets/famtastic-designs-logo-v1.png" alt="FAMtastic Designs"><div class="closing-url" data-line="20">famtasticdesigns.com</div>'
    ]

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--audio',type=Path,required=True);p.add_argument('--cues',type=Path,required=True)
    p.add_argument('--gsap',type=Path,required=True);p.add_argument('--output',type=Path,required=True)
    a=p.parse_args()
    if a.output.exists():p.error('Use a fresh output directory; never overwrite a prior project.')
    if sha(a.gsap)!=GSAP_SHA:p.error('Pinned GSAP 3.14.2 bytes required.')
    logo=REPO/'frontend/public/brand/famtastic-designs-logo-v1.png'
    if sha(logo)!=LOGO_SHA:p.error('Canonical logo digest mismatch.')
    raw=json.loads(a.cues.read_text());cues=raw.get('cues',raw) if isinstance(raw,dict) else raw
    lines=[x.strip() for x in (HERE/'user-script.txt').read_text().splitlines() if x.strip()]
    if len(cues)!=len(lines):p.error('Expected one real timing cue per nonempty script line.')
    for i,c in enumerate(cues):
        if not all(isinstance(c.get(k),(int,float)) and math.isfinite(c[k]) for k in ('start','end')) or c['start']<0 or c['end']<=c['start']:p.error(f'Invalid cue {i}.')
        if i and c['start']<cues[i-1]['end']-1e-6:p.error('Overlapping narration cues.')
        if c.get('source_text',c.get('text'))!=lines[i]:p.error(f'Cue {i} is not bound to the supplied script line.')
    info=json.loads(subprocess.check_output(['ffprobe','-v','error','-show_streams','-of','json',str(a.audio)]))
    audio=[s for s in info['streams'] if s['codec_type']=='audio']
    if len(audio)!=1 or audio[0]['codec_name']!='aac':p.error('Provide one AAC master.')
    ad=float(audio[0]['duration']); duration=math.ceil((ad+2)*30)/30
    if cues[-1]['end']>ad+.08:p.error('Cue timing exceeds audio master.')
    a.output.mkdir(parents=True);assets=a.output/'assets';assets.mkdir()
    copies={'gsap.min.js':a.gsap,'narration.m4a':a.audio,'famtastic-designs-logo-v1.png':logo,
       'metropolis-bold.woff2':REPO/'frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-bold.woff2',
       'metropolis-regular.woff2':REPO/'frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-regular.woff2',
       'kaushan-script.woff2':REPO/'frontend/public/brand/fonts/kaushan-script-latin-v19.woff2'}
    for name,source in copies.items():shutil.copyfile(source,assets/name)
    timing=[];markup=[]
    for i,((first,last,theme),content) in enumerate(zip(GROUPS,scenes())):
        start=0 if i==0 else math.floor(cues[first]['start']*30)/30
        end=math.floor(cues[GROUPS[i+1][0]]['start']*30)/30 if i<len(GROUPS)-1 else duration
        timing.append({'id':f'scene-{i:02d}','start':start,'end':end,'duration':end-start,'first_line':first,'last_line':last})
        markup.append(f'<section id="scene-{i:02d}" class="scene clip {theme}" data-start="{start}" data-duration="{end-start}" data-track-index="{i+1}"><div class="scene-content"><div class="inside">{content}</div></div></section>')
    captions=[]
    for i,c in enumerate(cues):
        words=lines[i].replace('“','').replace('”','').split(); groups=[words[j:j+7] for j in range(0,len(words),7)];offset=0
        for words_in_group in groups:
            start=c['start']+(c['end']-c['start'])*offset/len(words);offset+=len(words_in_group)
            end=c['start']+(c['end']-c['start'])*offset/len(words)
            captions.append({'start':start,'end':end,'text':' '.join(words_in_group)})
    caption_html=''.join(f'<div class="caption" id="cap-{i}" data-cue-start="{c["start"]}" data-cue-end="{c["end"]}"><span>{html.escape(c["text"])}</span></div>' for i,c in enumerate(captions))
    credit=subprocess.check_output(['node','--input-type=module','-e','import {pathToFileURL} from "node:url";const m=await import(pathToFileURL(process.argv[1]));process.stdout.write(m.creatorCreditHtml({embedded:true}));',str(REPO/'scripts/creator-credit.mjs')],text=True)
    template=(HERE/'index.template.html').read_text()
    for k,v in {'DURATION':str(duration),'AUDIO_DURATION':str(ad),'SCENES':''.join(markup),'CAPTIONS':caption_html,'CREDIT':credit,'TIMINGS':json.dumps(timing),'CUES':json.dumps(cues),'MOTION':(HERE/'motion.js').read_text()}.items():template=template.replace('{{'+k+'}}',v)
    template=template.replace('class="closing-logo"',f'class="closing-logo" data-start="{cues[19]["start"]}" data-duration="{duration-cues[19]["start"]}"')
    (a.output/'index.html').write_text(template)
    for name in ('style.css','motion.js','BRIEF.md','design.md','STORYBOARD.md','user-script.txt'):shutil.copyfile(HERE/name,a.output/name)
    (a.output/'scene-timing.json').write_text(json.dumps(timing,indent=2)+'\n')
    focals=['.ticket','.answer-title','.meaning','.belief-title','.vision-copy','.triptych-title','.why','.next-title','.growth-head','.remember-title','.closing-note']
    assertions=[]
    for scene,selector in zip(timing,focals):
        target='#'+scene['id']+' '+selector
        assertions.extend([{'kind':'appearsBy','selector':target,'bySec':scene['start']+1.3},{'kind':'staysInFrame','selector':target}])
    for cue,selector in [(6,'.belief-card[data-line="6"]'),(7,'.belief-card[data-line="7"]'),(8,'.belief-card[data-line="8"]'),(19,'.closing-logo'),(20,'.closing-url')]:
        assertions.append({'kind':'appearsBy','selector':selector,'bySec':cues[cue]['start']+1})
    assertions.append({'kind':'staysInFrame','selector':'.closing-url'})
    (a.output/'index.motion.json').write_text(json.dumps({'duration':duration,'assertions':assertions},indent=2)+'\n')
    (a.output/'captions.json').write_text(json.dumps(captions,indent=2,ensure_ascii=False)+'\n')
    def ts(t):
        ms=round(t*1000);return f'{ms//3600000:02d}:{ms//60000%60:02d}:{ms//1000%60:02d}.{ms%1000:03d}'
    (a.output/'captions.vtt').write_text('WEBVTT\n\n'+'\n'.join(f'{i+1}\n{ts(c["start"])} --> {ts(c["end"])}\n{c["text"]}\n' for i,c in enumerate(captions)))
    (a.output/'asset-provenance.json').write_text(json.dumps({'narration':'Local synthetic narrator; see source voice-provenance document and audio run receipts','graphics':'Original deterministic HTML/SVG concept objects','script_sha256':sha(HERE/'user-script.txt'),'timing_source_sha256':sha(a.cues),'files':[{'path':'assets/'+n,'sha256':sha(p)} for n,p in copies.items()],'caption_timing':'Seven-word editorial grouping inside measured utterance bounds; not word-exact alignment','provider_fee_usd':0},indent=2)+'\n')
    (a.output/'hyperframes.json').write_text('{"media":{"autoProxy":false}}\n')
    files=sorted(p.relative_to(a.output).as_posix() for p in a.output.rglob('*') if p.is_file())
    manifest={'schema':'famtastic.local-hyperframes-project.v1','id':'whats-the-catch','title':'What’s the catch? — You grow. We grow.','entrypoint':'index.html','width':1920,'height':1080,'fps':30,'duration_seconds':duration,'files':files,'audio_master':{'path':'assets/narration.m4a','mode':'copy','start_seconds':0},'brand_logo':{'path':'assets/famtastic-designs-logo-v1.png','sha256':LOGO_SHA},'creator_credit_marker':'data-famtastic-creator-credit="v1"'}
    (a.output/'project.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n')
    print(json.dumps({'project':str(a.output.resolve()),'duration_seconds':duration,'audio_seconds':ad,'scenes':len(timing),'caption_cues':len(captions),'manifest_sha256':sha(a.output/'project.json')},indent=2))
if __name__=='__main__':main()
