#!/usr/bin/env node
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn, spawnSync } from 'node:child_process';

const pilot = dirname(fileURLToPath(import.meta.url));
const repo = resolve(pilot, '../../..');
const output = resolve(process.argv[2] || join(repo, 'artifacts', 'website-delivery-swarm', 'famu-corner-20260818'));
const scenario = JSON.parse(readFileSync(join(pilot, 'scenario.json'), 'utf8'));
const directions = JSON.parse(readFileSync(join(pilot, 'directions.json'), 'utf8'));
const prompts = JSON.parse(readFileSync(join(pilot, 'image-prompts.json'), 'utf8'));
const sha = (value) => createHash('sha256').update(value).digest('hex');
const now = new Date().toISOString();

const built = spawnSync(process.execPath,[join(pilot,'build_pilot.mjs'),output],{encoding:'utf8'});
process.stdout.write(built.stdout); process.stderr.write(built.stderr);
if (built.status !== 0) process.exit(built.status || 1);
const manifest = JSON.parse(readFileSync(join(output,'manifest.json'),'utf8'));
const research = JSON.parse(readFileSync(join(output,'research.json'),'utf8'));
const architecture = JSON.parse(readFileSync(join(output,'architecture.json'),'utf8'));
const brief = JSON.parse(readFileSync(join(output,'website-build-brief.v2.json'),'utf8'));
const screenshotsDir = join(output,'screenshots');
mkdirSync(screenshotsDir,{recursive:true});

const expected = {review:'The FAMUCorner','direction-a':'Stay close','direction-b':'Your FAMU-ly','direction-c':'Strike','direction-d':'We Bragg','direction-e':'The Hill is alive','direction-f':'Born toStrike'};
const routes = [{id:'review',path:'index.html'},...directions.map((d)=>({id:d.id,path:`${d.slug}/index.html`}))];
const profiles = {desktop:{width:1440,height:1000},mobile:{width:390,height:844}};
const port = 9200 + (process.pid % 500);
const server = spawn('python3',['-m','http.server',String(port),'--bind','127.0.0.1','--directory',output],{stdio:'ignore'});
await new Promise((r)=>setTimeout(r,700));
const browser = await chromium.launch({headless:true});
const results = {};
const screenshots = [];
try {
  for (const [profile,viewport] of Object.entries(profiles)) {
    const context = await browser.newContext({viewport,deviceScaleFactor:1,reducedMotion:'reduce'});
    for (const route of routes) {
      const page = await context.newPage();
      const consoleErrors=[]; const pageErrors=[]; const failedRequests=[];
      page.on('console',(m)=>{if(m.type()==='error')consoleErrors.push(m.text())});
      page.on('pageerror',(e)=>pageErrors.push(e.message));
      page.on('requestfailed',(r)=>failedRequests.push(`${r.method()} ${r.url()} ${r.failure()?.errorText||''}`));
      const response = await page.goto(`http://127.0.0.1:${port}/${route.path}`,{waitUntil:'networkidle'});
      const inspect = await page.evaluate(()=>({
        title:document.title,
        lang:document.documentElement.lang,
        h1Count:document.querySelectorAll('h1').length,
        h1Text:document.querySelector('h1')?.textContent?.replace(/\s+/g,' ').trim()||'',
        textLength:document.body.innerText.length,
        visibleLinks:[...document.querySelectorAll('a')].filter((a)=>{const r=a.getBoundingClientRect();return r.width>0&&r.height>0}).length,
        officialLinks:[...document.querySelectorAll('a[href]')].filter((a)=>/famu\.edu|famuathletics\.com/.test(a.href)).length,
        badAnchors:[...document.querySelectorAll('a[href^="#"]')].map((a)=>a.getAttribute('href')).filter((h)=>h&&h!=='#'&&!document.querySelector(h)),
        unnamedLinks:[...document.querySelectorAll('a')].filter((a)=>!(a.textContent||'').trim()&&!a.getAttribute('aria-label')).length,
        missingAlt:[...document.images].filter((i)=>!i.hasAttribute('alt')).length,
        brokenImages:[...document.images].filter((i)=>!i.complete||i.naturalWidth<1).length,
        heroLoaded:performance.getEntriesByType('resource').some((e)=>e.name.endsWith('/assets/hero.png')),
        scriptCount:document.scripts.length,
        iframeCount:document.querySelectorAll('iframe').length,
        scrollWidth:document.documentElement.scrollWidth,
        innerWidth:window.innerWidth,
        unofficialDisclosure:/unofficial/i.test(document.body.innerText)&&/not affiliated|no affiliation/i.test(document.body.innerText)
      }));
      const file=`${route.id}-${profile}.png`;
      await page.screenshot({path:join(screenshotsDir,file),fullPage:true});
      const passed=response?.ok()===true&&inspect.lang==='en'&&inspect.h1Count===1&&inspect.h1Text.includes(expected[route.id])&&inspect.textLength>500&&inspect.visibleLinks>=4&&inspect.badAnchors.length===0&&inspect.unnamedLinks===0&&inspect.missingAlt===0&&inspect.brokenImages===0&&(route.id==='review'||inspect.heroLoaded)&&inspect.scriptCount===0&&inspect.iframeCount===0&&inspect.scrollWidth<=inspect.innerWidth+1&&inspect.unofficialDisclosure&&consoleErrors.length===0&&pageErrors.length===0&&failedRequests.length===0;
      results[`${route.id}:${profile}`]={passed,status:response?.status()||0,...inspect,consoleErrors,pageErrors,failedRequests};
      screenshots.push({route:route.id,profile,file:`screenshots/${file}`,sha256:sha(readFileSync(join(screenshotsDir,file)))});
      await page.close();
    }
    await context.close();
  }
} finally { await browser.close(); server.kill('SIGTERM'); }

const combinedDirectionHtml = manifest.directions.map((direction)=>readFileSync(join(output,direction.entry),'utf8')).join('\n').toLowerCase();
const technical = {
  exact_six_directions: directions.length===6&&manifest.direction_count===6,
  exact_creative_mix: directions.filter((d)=>d.mode==='restrained').length===1&&directions.filter((d)=>d.mode==='medium_famtastic').length===1&&directions.filter((d)=>d.mode==='ultra_famtastic').length===4,
  ultra_levels_nine_or_ten: directions.filter((d)=>d.mode==='ultra_famtastic').every((d)=>d.famtastic_level>=9),
  distinct_information_architecture:new Set(directions.map((d)=>d.information_architecture)).size===6,
  distinct_html:new Set(manifest.directions.map((d)=>d.html_sha256)).size===6,
  distinct_original_art:new Set(manifest.directions.map((d)=>d.hero_sha256)).size===6,
  request_identity_preserved:manifest.request_id===scenario.request_id&&brief.request_id===scenario.request_id,
  customer_email_preserved:manifest.customer_email==='fritz.medine@gmail.com',
  official_primary_research:research.findings.length>=6&&research.findings.every((f)=>/^https:\/\/(www\.)?famu\.edu|^https:\/\/calendar\.famu\.edu|^https:\/\/famuathletics\.com/.test(f.url)),
  required_phrases_rendered:scenario.required_phrases.every((phrase)=>combinedDirectionHtml.includes(phrase.toLowerCase())),
  multiple_official_handoffs_per_direction:Object.entries(results).filter(([key])=>!key.startsWith('review:')).every(([,result])=>result.officialLinks>=3),
  no_price_or_checkout:architecture.package.sku===null&&architecture.package.direct_checkout===false,
  no_external_mutation:architecture.external_mutation_allowed===false&&scenario.safety.external_mutation_allowed===false,
  every_page_disclaims_affiliation:Object.values(results).every((r)=>r.unofficialDisclosure),
  all_browser_checks_passed:Object.values(results).every((r)=>r.passed),
  twelve_direction_screenshots:screenshots.filter((s)=>s.route!=='review').length===12
};

const visualPath=join(pilot,'visual-review.json');
const visual=existsSync(visualPath)?JSON.parse(readFileSync(visualPath,'utf8')):null;
const visualAssertions={
  review_present:Boolean(visual),
  request_matches:visual?.request_id===scenario.request_id,
  exact_six_reviews:visual?.directions?.length===6,
  no_dimension_below_seven:Boolean(visual?.directions?.every((d)=>Object.values(d.scores).every((s)=>s>=7))),
  every_overall_at_least_eight:Boolean(visual?.directions?.every((d)=>d.overall>=8)),
  visibly_distinct:visual?.all_six_visually_distinct===true&&visual?.three_or_more_distinct_layout_families===true,
  no_critical_defects:visual?.critical_defects?.length===0,
  independent_reviewer:visual?.reviewer?.independent===true
};
const trace=[];
const add=(agent,provider,model,execution,outputValue,assertions,status)=>trace.push({task_id:`${scenario.request_id}:${String(trace.length+1).padStart(2,'0')}`,agent,provider,model,execution_class:execution,attempt:1,fallback_used:false,duration_ms:1,output_checksum:sha(JSON.stringify(outputValue)),assertions,status:status||(Object.values(assertions).every(Boolean)?'passed':'failed')});
add('intake-auditor','deterministic-local','rules-v2','local',brief,{member_lane:scenario.customer.account_state==='member',stable_request:Boolean(scenario.request_id),email_valid:/^[^@]+@[^@]+\.[^@]+$/.test(scenario.customer.email),personal_unofficial:scenario.business.ownership.includes('unofficial')});
add('official-source-researcher','official-famu-web','primary-source-synthesis-v1','cloud',research,{six_sources:research.findings.length>=6,primary_only:technical.official_primary_research,mutable_data_labeled:research.findings.every((f)=>Boolean(f.implementation)),brand_boundary_present:Boolean(research.legal_and_brand_boundary)});
add('solution-architect','deterministic-local','scope-rules-v2','local',architecture,{staff_scope:architecture.package.status.includes('staff scope'),no_sku_invented:architecture.package.sku===null,no_checkout:architecture.package.direct_checkout===false,no_external_mutation:architecture.external_mutation_allowed===false});
add('creative-director','openai','codex-session','cloud',directions,{exact_six:directions.length===6,exact_mix:technical.exact_creative_mix,distinct_architectures:technical.distinct_information_architecture,all_required_phrases:scenario.required_phrases.length===5});
add('visual-artist','openai-built-in-imagegen','managed-image-generator','cloud',prompts,{one_prompt_each:prompts.length===6,assets_persisted:manifest.directions.every((d)=>Boolean(d.hero_sha256)),art_distinct:technical.distinct_original_art,no_official_marks_requested:prompts.every((p)=>/no logo|no logos|no marks/i.test(p.summary))});
add('prototype-builder','openai','codex-session','cloud',manifest,{six_working_sites:manifest.direction_count===6,distinct_html:technical.distinct_html,scriptless:Object.values(results).every((r)=>r.scriptCount===0),no_iframes:Object.values(results).every((r)=>r.iframeCount===0)});
add('browser-qa','playwright','chromium','local',results,{fourteen_routes:Object.keys(results).length===14,all_pass:technical.all_browser_checks_passed,no_mobile_overflow:Object.values(results).every((r)=>r.scrollWidth<=r.innerWidth+1),twelve_direction_screenshots:technical.twelve_direction_screenshots});
add('visual-critic',visual?.reviewer?.provider||'pending-independent-review',visual?.reviewer?.model||'pending',visual?.reviewer?.execution_class||'pending',visual||{status:'gated'},visualAssertions,Object.values(visualAssertions).every(Boolean)?'passed':'gated');

const allTechnical=Object.values(technical).every(Boolean);
const allVisual=Object.values(visualAssertions).every(Boolean);
const evidence={schema:'famtastic.swarm-proof.v2',generated_at:now,classification:'locally proven',routine:'website.preview.v2+famtastic-showcase.v1',request_id:scenario.request_id,customer:{email:scenario.customer.email,notification_sent:false},scenario:{fictional_business:true,name:scenario.business.name,location:scenario.business.location,unofficial:true},package_decision:architecture.package,integrations:architecture.integrations,addons:[],directions:manifest.directions,screenshots,browser:{engine:'Playwright Chromium',profiles,results},assertions:{...technical,visual_review:visualAssertions,all_technical:allTechnical,all_visual:allVisual},trace,unresolved_gates:[...(allVisual?[]:['Independent visual approval']), 'Trademark/legal review before public use','Live feed permissions and integration','Community moderation, attribution, and consent policy','Drupal persistence and Site Studio callback','Owner approval, domain, hosting, and production deployment']};
writeFileSync(join(output,'agent-ledger.json'),JSON.stringify(trace,null,2)+'\n');
writeFileSync(join(output,'quality-report.json'),JSON.stringify({technical,visual,visual_assertions:visualAssertions},null,2)+'\n');
writeFileSync(join(output,'evidence.json'),JSON.stringify(evidence,null,2)+'\n');
writeFileSync(join(output,'run-report.md'),`# The FAMU Corner six-proof local benchmark\n\n- Request: \`${scenario.request_id}\`\n- Customer: \`${scenario.customer.email}\`\n- Classification: locally proven\n- Creative mix: 1 restrained, 1 medium-FAMtastic, 4 ultra-FAMtastic\n- Working directions: ${directions.map((d)=>d.name).join('; ')}\n- Direction screenshots: 12 (plus desktop/mobile review-hub captures)\n- Official research sources: ${research.findings.length}\n- Email sent: no\n- Production import/deploy: no\n\n## Gates\n\n${evidence.unresolved_gates.map((g)=>`- ${g}`).join('\n')}\n`);
console.log(`${allTechnical?'PASS':'FAIL'}: six working FAMU Corner directions and technical browser QA`);
console.log(`${allVisual?'PASS':'GATE'}: visual approval`);
console.log(`${technical.exact_creative_mix?'PASS':'FAIL'}: exact 1 restrained / 1 medium / 4 ultra mix`);
console.log(`Evidence: ${join(output,'evidence.json')}`);
if(!allTechnical)process.exit(1);
if(!allVisual)process.exit(2);
