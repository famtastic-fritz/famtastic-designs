import fs from 'node:fs';
import path from 'node:path';
import { createRepoScaffold, preflightRepository } from '/Users/famtastic-fritz/Development/FAMtastic-Repos/site-studio-next/server/kernel/repo-scaffold.js';
import { createGitDelivery } from '/Users/famtastic-fritz/Development/FAMtastic-Repos/site-studio-next/server/kernel/git-delivery.js';

const root = '/Users/famtastic-fritz/Development/FAMtastic/sites/site-travel-addicts-courier-express';
const site_id = 'site-travel-addicts-courier-express';
const registry = JSON.parse(fs.readFileSync('/Users/famtastic-fritz/Development/worktrees/ecosystem-travel-addicts/config/repositories/catalog.v1.json')).entries;
const design_contract = {
  schema_version: 1, version: '1.0.0', source: 'customer-supplied-flyer-2026-09-21',
  approval: 'owner_authorized_build_from_flyer_customer_acceptance_pending',
  direction: 'Miami after hours, precise by daylight',
  tokens: { night: '#06121C', navy: '#0B2335', paper: '#F4F6F2', white: '#F4F8FB', cyan: '#10CAE3', gold: '#F6C445', orange: '#EB7229', muted_dark: '#ADC0CA', muted_light: '#425E70' },
  typography: { headings: 'Barlow Condensed 700/800, 800 true italic for hero emphasis', body: 'Barlow 400/600', licensing: 'SIL OFL 1.1, self-hosted' },
  texture: ['midnight diagonal finish', 'restrained harbor light', 'delivery-manifest ruling'],
  responsive: { viewports: [320,390,768,1280,1440], min_target_px:48, body_min_px:16, reduced_motion:true },
  assets: { logo: 'Preserve exact supplied artwork; never redraw.', hero: 'Original decorative Miami city illustration, no invented real fleet or person.', credit: 'Exact mandatory FAMtastic Designs PNG' },
  claim_policy: 'Flyer is customer-supplied evidence; credentials and SLA performance are not independently verified. Dispatch confirms scope, price and timing before acceptance.',
  workflow: 'Call or review a prepared request before handing it to the business through email or WhatsApp. No fake tracking, submission, booking or payment state.',
  page_recipe: ['home','services','legal-courier','medical-courier','luxury-courier','event-courier','how-it-works','service-areas','pricing','about','request-delivery','faq','privacy','terms'],
};
const checked = preflightRepository({ repository_path:root,site_id,allow_uninitialized:true,registry });
if (checked.initialized) throw new Error('Bootstrap is new-site only; preserve the existing source.');
const scaffold = createRepoScaffold({ site_id, business_name:'Travel Addicts Courier Express', business_owner:{name:'Travel Addicts Courier Express'}, description:'South Florida B2B courier website, based on the supplied flyer and source-linked research.', design_contract, repository:{branch:'main'}, target:'protected_review_not_business_launch' });
const receipt = createGitDelivery().prepare({ repository_path:root,site_id,hosting_root:'/home/nineoo/public_html/famtasticinc-landing',target_path:'/home/nineoo/public_html/famtasticinc-landing/travel-addicts-courier-express',registry,scaffold,author:{name:'Fritz Medine',email:'fritz.medine@gmail.com'},message:'Initialize Travel Addicts independent source foundation' });
fs.mkdirSync(path.join(root,'docs/evidence'),{recursive:true});
fs.writeFileSync(path.join(root,'docs/evidence/foundation.json'),JSON.stringify({...receipt,studio_revision:'bf1ef9ca09276d08c6555690737eafb3d4b6e109',foundation_source_revision:'2937a3bf58c52f146734b8779375ec884c6417ae'},null,2)+'\n');
console.log(JSON.stringify(receipt,null,2));
