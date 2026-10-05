#!/usr/bin/env node
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, statSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const base='marketing/campaigns/acquisition-199';
const file=p=>readFileSync(resolve(root,base,p),'utf8');
const recipes=JSON.parse(file('recipes.json'));
const drafts=JSON.parse(file('messages.json'));
let assertions=0;
function check(value,message){assert.ok(value,message);assertions++;}
check(recipes.niches.length===3,'Three niches');
check(drafts.messages.length===9,'Nine complete distinct drafts');
check(new Set(drafts.messages.map(m=>m.subject)).size===9,'Distinct subjects');
check(recipes.review_status==='candidate','No inferred recipe approval');
check(recipes.existing_library_audit.existing_recipe_id==='booked-branded-one-page-v1','Existing booked/branded recipe audited before adaptation');
for(const n of recipes.niches){
 check(n.directions.length===2,`${n.id}: two samples`);
 check(new Set(n.directions.map(d=>JSON.stringify(d.composition.order)+d.style)).size===2,`${n.id}: genuinely different composition`);
 const messages=drafts.messages.filter(m=>m.niche===n.id);
 check(JSON.stringify(messages.map(m=>m.day))==='[0,3,7]',`${n.id}: proposed days 0/3/7`);
 for(const d of n.directions){
  const html=file(d.render_path);
  const review=file(d.review_path);
  check(!/<script\b|<form\b|<input\b|on(?:click|load|error)\s*=/i.test(html),`${d.id}: scanner GET has no script/form mutation surface`);
  for(const field of ['business_name','locality','phone','booking_url','inquiry_url']) check(html.includes(`{{${field}}}`),`${d.id}: ${field} binding declared`);
  check(!/\{\{/.test(review),`${d.id}: fixture fully rendered`);
  check(review.includes('fictional local review fixture'),`${d.id}: fiction disclosed`);
  check(html.includes('no-referrer') && !/google|gtag|analytics\.js/i.test(html),`${d.id}: no Google token disclosure`);
  check(html.includes('prefers-reduced-motion'),`${d.id}: reduced motion`);
  check(html.includes('data-famtastic-creator-credit="v1"'),`${d.id}: exact creator credit consumer`);
  check(html.includes('src="/brand/famtastic-designs-logo-v1.png"') && !/src="(?:https:|\.\.\/assets|assets\/)/.test(html),`${d.id}: same-origin frozen logo compatible with private preview CSP`);
  check(d.recipe_ref.status==='import_request_pending',`${d.id}: import approval pending`);
  check(d.reuse_lineage.direct_code_reuse.includes('scripts/creator-credit.mjs') && d.reuse_lineage.source_media_reused===false,`${d.id}: precise source/pattern/media reuse boundary`);
  check(html.includes('id="owner-view"'),`${d.id}: illustrative phone owner overview`);
  if(n.id==='beauty_hair') check(d.reuse_lineage.beauty_barber_reference.revision==='booked-branded-v5-20260827',`${d.id}: exact existing barber/beauty revision`);
 }
}
for(const m of drafts.messages){
 const html=file(`emails/${m.id}.html`),text=file(`emails/${m.id}.txt`),blocked=file(`emails/${m.id}-images-blocked.html`);
 check(html.includes('data-famtastic-email-brand="v1"'),`${m.id}: existing shared shell`);
 check((html.match(/class="cta"/g)||[]).length===1,`${m.id}: one primary CTA`);
 check(html.includes('https://famtasticdesigns.com/connect"') && text.includes('https://famtasticdesigns.com/connect\n'),`${m.id}: card fallback exact`);
 check(html.includes('https://famtasticdesigns.com/connect/commercial.mp4') && text.includes('https://famtasticdesigns.com/connect/commercial.mp4'),`${m.id}: commercial exact`);
 check(html.includes('Shay-Shay') && html.includes('FAMtastic Designs assistant'),`${m.id}: disclosed signature`);
 check(!/data:image/.test(html) && statSync(resolve(root,base,`emails/${m.id}.html`)).size<100000,`${m.id}: compact review asset`);
 check(!/<img\b/i.test(blocked) && blocked.includes('Digital card') && blocked.includes('class="cta"'),`${m.id}: image-blocked text and action remain`);
 check(m.sample_image_count<=2,`${m.id}: <=2 sample images separate from QR`);
 check((html.match(/-preview\.jpg/g)||[]).length===m.sample_image_count,`${m.id}: actual rendered preview count matches declared count`);
 for(const p of m.sample_previews ?? []) {
  check(p.link_binding==='invitation_url',`${m.id}: all sample previews lead to same primary invitation`);
  check(statSync(resolve(root,base,p.path)).size<60000,`${m.id}: compact shared preview`);
  check(!/Juniper|@|token|recipient/i.test(p.alt),`${m.id}: shared preview metadata contains no fixture or recipient identity`);
 }
 check(m.greeting_binding==='verified_recipient_name_or_verified_business_name' && m.forbidden_greeting_source==='email_localpart',`${m.id}: safe name fallback`);
 check(html.includes('example.invalid/invitation-not-bound') && html.includes('example.invalid/unsubscribe-not-bound'),`${m.id}: unsendable review links`);
 check(!m.send_policy.approved && ['human_reply','purchase','opt_out','complaint','hard_bounce'].every(x=>m.send_policy.stop_on.includes(x)),`${m.id}: all exit events`);
 check(text.includes('$9.99/month only with separate recurring authorization') && text.includes('domain renewal is separate'),`${m.id}: actual renewals disclosed`);
 check(!/48 hours|24.hour|same.day|lost revenue|guaranteed (?:traffic|sales|bookings)|testimonials/i.test(m.paragraphs.join(' ')),`${m.id}: no unsupported results/turnaround`);
 check(text.includes('{{invitation_url}}') && text.includes('{{unsubscribe_url}}'),`${m.id}: required sending bindings`);
 check(/phone/i.test(m.paragraphs.join(' ')) && /illustrative|practice|example/i.test(m.paragraphs.join(' ')+m.journey),`${m.id}: phone owner value with demo truth boundary`);
 check(m.research_observation_binding.default===null && m.research_observation_binding.render_only_when_owner_approved && m.research_observation_binding.allowed_classification==='verified_public_fact',`${m.id}: research personalization defaults absent and needs approved evidence`);
}
const matrix=JSON.parse(file('assets/connect-qr-matrix.json'));
check(matrix.url==='https://famtasticdesigns.com/connect' && matrix.quiet_zone_modules>=4,'QR payload and quiet zone');
check(matrix.matrix.length<=33,'160px email QR keeps >=3px/module including quiet zone');
const png=resolve(root,base,'assets/connect-qr.png');
const decoded=execFileSync('/usr/bin/swift',['-module-cache-path','/private/tmp/acquisition-swift-cache',resolve(root,'scripts/acquisition-creative-decode.swift'),png],{encoding:'utf8',timeout:60000}).trim();
check(decoded==='https://famtasticdesigns.com/connect','Encoded PNG QR independently decoded by macOS Vision');
const imageProof=JSON.parse(execFileSync('python3',['-c',`from PIL import Image\nimport json\nim=Image.open(${JSON.stringify(png)}); colors=im.getcolors(im.width*im.height); print(json.dumps({'width':im.width,'height':im.height,'colors':sorted([list(c[1]) for c in colors]),'contrast_ratio':21.0}))`],{encoding:'utf8'}));
check(JSON.stringify(imageProof.colors)==='[[0,0,0],[255,255,255]]','QR pure black/white 21:1 contrast');
const receipt={schema:'famtastic.acquisition-creative-qa.v1',tested_at:new Date().toISOString(),assertions,status:'passed',qr:{encoded:matrix.url,decoded,width:imageProof.width,height:imageProof.height,display_px:160,module_display_px:160/(matrix.matrix.length+8),quiet_zone_modules:4,contrast_ratio:21,sha256:createHash('sha256').update(readFileSync(png)).digest('hex'),decoder:'macOS Vision'},limits:['Source contracts and local rendering only','Email-client inbox rendering unverified','Physical phone scan at email display size unobserved','Owner creative review pending','Component Studio import acceptance pending','Provider/sending/real cohort held']};
writeFileSync(resolve(root,base,'evidence/source-qa.json'),JSON.stringify(receipt,null,2)+'\n');
console.log(`PASS ${assertions} creative assertions + native QR decode. No mail or payment executed.`);
