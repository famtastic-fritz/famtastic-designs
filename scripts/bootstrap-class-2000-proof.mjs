import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { createRepoScaffold } from '/Users/famtastic-fritz/Development/FAMtastic-Repos/site-studio-next/vendor/site-foundation/index.js';
import { createGitDelivery } from '/Users/famtastic-fritz/Development/FAMtastic-Repos/site-studio-next/server/kernel/git-delivery.js';

const root = '/Users/famtastic-fritz/Development/FAMtastic-Repos/site-mbsh-class-of-2000';
const site_id = 'mbsh-class-of-2000';
if (fs.existsSync(root)) throw new Error('Refusing to overwrite an existing customer repository');
const design = {
  schema_version: 1, status: 'three_directions_for_client_choice_not_selected',
  source: 'Fritz-approved staff-assisted brief; original customer request remains authoritative',
  source_request: 16, routine: 'website_proof.generate.v1',
  purpose: 'Mobile-first alumni reunion experience with clear next actions and committee operation.',
  directions: [
    { id: 'a', name: 'Hi-Tide Legacy', recipe: 'editorial-keepsake-v1', colors: ['#f5f1e7','#0b443b','#c59b51'], typography: 'Georgia editorial serif / system sans', composition: 'Oversized yearbook typography, ruled masthead, split daylight art, paper invitation, orderly event guide.' },
    { id: 'b', name: 'Miami After Dark', recipe: 'cinematic-invitation-v1', colors: ['#0d1124','#dbd9ff','#7fe3d7','#edc784'], typography: 'Impact condensed display / system sans', composition: 'Full-bleed night artwork, oversized stacked display, vertical program, bordered ticket object.' },
    { id: 'c', name: 'Class of 2000: Then & Now', recipe: 'living-yearbook-v1', colors: ['#fff7e5','#252720','#c35637','#3f6552'], typography: 'Georgia italic / system sans', composition: 'Asymmetric scrapbook, overlapping keepsake frames, native then-now toggle, warm story timeline.' }
  ],
  shared: { min_touch_target: 44, widths: [320,390,768,1440], reduced_motion: true, hierarchy: 'one h1; semantic section h2; real selectable text', no_js_proof_policy: true },
  unknown: ['Date','Venue','Ticket price','Capacity','Dinner menu','Committee contacts','Alumni photos and permissions','Production domain'],
  rights: 'No Class of 1996 private assets, people, records or school logos. Generated scenes labeled illustrative, not actual venue or attendees.',
  scope: 'Proofs only. No ticket sale, RSVP submission, attendee identity or private upload exists in the demonstrations.',
  reference: 'https://mbsh96reunion.com/',
  continuity: 'Keep selected page recipe, asset hashes and typography when implementing the chosen staging direction.'
};
const scaffold = createRepoScaffold({site_id,business_name:'Miami Beach Senior High Class of 2000',description:'Three reunion website directions for client review. Proofs do not take payments or collect personal information.',design_contract:design,target:'account_bound_proof_system',repository:{branch:'main'}});
const receipt = createGitDelivery().prepare({ repository_path:root,site_id,target_path:'/proof-artifacts/mbsh-class-of-2000',hosting_root:'/proof-artifacts',scaffold,message:'Initialize Class of 2000 independent proof source' });
const dir=path.join(root,'evidence/request-16');fs.mkdirSync(dir,{recursive:true});
const preflight={schema:'famtastic.capability-preflight.v1',routine:'website_proof.generate.v1',request_id:16,recorded_at:new Date().toISOString(),routes:{research:{provider:'web_research',status:'executed',evidence:'MBSH96 public reference read September 18, 2026'},construction:{provider:'codex-active-runtime',status:'available',model_status:'not_disclosed_by_runtime',evidence:'Active task tools and source editing executed'},art:{provider:'managed_image_generation',status:'available_not_yet_executed',model_status:'not_disclosed_by_runtime'},independent_review:{provider:'claude-cli',status:'preflight_pending'}},fallback:{preferred_antigravity:'No structured bridge receipt exposed to this task; use declared active Codex runtime, not desktop sign-in inference'},authorization:'Owner-approved request16 proof delivery; no payment or final-domain launch; main agent owns sending'};
fs.writeFileSync(path.join(dir,'provider-preflight.json'),JSON.stringify(preflight,null,2)+'\n');
fs.writeFileSync(path.join(dir,'build-dna.json'),JSON.stringify({schema:'famtastic.build-dna.v1',build_id:'class-2000-request16-20260918-v1',classification:'in_progress_client_proofs',created_at:new Date().toISOString(),repository:{name:'site-mbsh-class-of-2000',revision:receipt.commit,worktree_state:'in_progress'},recipe:{routine:'website_proof.generate.v1',version:'1.0.0',build_class:'custom'},correlation:{website_request_id:16,customer_id:14},stages:[{stage_id:'run-created',attempt:1,capability:'intake_validation',execution:{provider:{id:'codex-active-runtime'},model:{status:'not_disclosed_by_runtime'},timing:{status:'partial',reason:'Preflight actions precede run materialization; no invented duration'},cost:{status:'provider_did_not_report'}},result:{status:'in_progress',notes:'Original customer draft preserved. Staff brief and campaign binding pending.'}}],artifacts:[{role:'provider_preflight',path:'evidence/request-16/provider-preflight.json',sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(dir,'provider-preflight.json'))).digest('hex')}],retrieval:{filesystem:{path:'evidence/request-16/build-dna.json'},database:{status:'pending'},site_studio:{status:'pending_client_selection'}},integrity:{artifact_hash_algorithm:'sha256'}},null,2)+'\n');
console.log(JSON.stringify({root,receipt,build_dna:'evidence/request-16/build-dna.json'},null,2));
