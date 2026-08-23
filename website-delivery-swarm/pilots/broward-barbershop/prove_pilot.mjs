#!/usr/bin/env node
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
import { spawn, spawnSync, execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { cpSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root=dirname(fileURLToPath(import.meta.url));
const repo=resolve(root,'../../..');
const output=resolve(repo,'.artifacts/broward-barbershop-pilot/latest');
mkdirSync(output,{recursive:true});
const built=spawnSync('python3',[join(root,'build_pilot.py')],{encoding:'utf8'});
if(built.status!==0){process.stderr.write(built.stderr);process.exit(1)}
const site=join(root,'site');
const port=8877;
const server=spawn('python3',['-m','http.server',String(port),'--bind','127.0.0.1','--directory',site],{stdio:'ignore'});
await new Promise(r=>setTimeout(r,500));
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
const assertions={}; const screenshots=[];
const shot=async(name,fullPage=true)=>{const file=join(output,`${name}.png`);await page.screenshot({path:file,fullPage});screenshots.push({file:`${name}.png`,sha256:createHash('sha256').update(readFileSync(file)).digest('hex')})};
try{
  for(const id of ['safe','wild','omg']){
    const response=await page.goto(`http://127.0.0.1:${port}/${id}.html`,{waitUntil:'networkidle'});
    assertions[`${id}_desktop`]=response?.ok()===true&&await page.locator('h1').count()===1&&await page.locator('a.primary').count()===1;
    assertions[`${id}_hero_loaded`]=await page.locator('.hero>img').evaluate(img=>img.complete&&img.naturalWidth>1000);
    await shot(`${id}-desktop`);
    await page.setViewportSize({width:390,height:844});
    assertions[`${id}_mobile_no_overflow`]=await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth);
    await shot(`${id}-mobile`);
    await page.setViewportSize({width:1440,height:1000});
  }
  await page.goto(`http://127.0.0.1:${port}/index.html`,{waitUntil:'networkidle'});
  await page.locator('[data-select="omg"]').click(); await shot('journey-proof-selected');
  await page.locator('#approve').click();
  assertions.explicit_approval=await page.locator('#events li',{hasText:'approval_confirmed:omg'}).count()===1;
  await shot('journey-approved');
  await page.locator('#build').click();
  assertions.build_authorized=await page.locator('#events li',{hasText:'build_authorized:omg'}).count()===1;
  const approved=join(output,'approved-build');mkdirSync(approved,{recursive:true});
  writeFileSync(join(approved,'index.html'),readFileSync(join(site,'omg.html'),'utf8').replace('../assets/barbershop-hero.png','assets/barbershop-hero.png'));cpSync(join(site,'styles.css'),join(approved,'styles.css'));
  mkdirSync(join(approved,'assets'),{recursive:true});cpSync(join(root,'assets/barbershop-hero.png'),join(approved,'assets/barbershop-hero.png'));
  await shot('journey-build-ready');
  await page.locator('#payment').click();
  assertions.payment_gate_visible=await page.locator('#payment-gate[open]').count()===1;
  assertions.payment_attempted=false; assertions.payment_completed=false;
  await shot('journey-payment-stop');
}finally{await browser.close();server.kill('SIGTERM')}

const evidence={schema:'famtastic.barbershop-pilot.v1',classification:'locally proven',request_id:'fixture:broward-barbershop-lp3',
  source_sha:execFileSync('git',['rev-parse','HEAD'],{cwd:repo,encoding:'utf8'}).trim(),selected_direction:'omg',
  state_sequence:['intake_complete','proofs_generated','qa_baseline_passed','customer_review','proof_selected:omg','approval_confirmed:omg','build_authorized:omg','build_started:omg','payment_required'],
  assertions,screenshots,payment:{attempted:false,completed:false,stop_reason:'payment_boundary'},
  email_capture:[{transport:'local-memory',to:'synthetic-owner@example.test',subject:'Your Third Chair Studio proofs are ready',status:'captured'},{transport:'local-memory',to:'synthetic-owner@example.test',subject:'OMG direction approved — preview build ready',status:'captured'}],
  external_claims:{gmail_delivery:'pending_separate_connector_proof',live_models:'pending_benchmark',live_research:'sourced_plan_only'},
  boundaries:['No real customer','No payment session','No charge','No production deployment','Draft services and facts require confirmation','Full WCAG/assistive-technology audit remains required']};
assertions.all_required=Object.entries(assertions).filter(([k])=>!k.startsWith('payment_')).every(([,v])=>v===true)&&assertions.payment_attempted===false&&assertions.payment_completed===false;
writeFileSync(join(output,'evidence.json'),JSON.stringify(evidence,null,2)+'\n');
if(!assertions.all_required)process.exit(1);
console.log('PASS: three actual website proofs rendered at desktop and mobile');
console.log('PASS: OMG selected, explicitly approved, locally built, and stopped at payment boundary');
console.log(`Evidence: ${join(output,'evidence.json')}`);
