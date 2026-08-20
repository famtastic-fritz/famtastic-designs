#!/usr/bin/env node
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root=dirname(fileURLToPath(import.meta.url));
const repo=resolve(root,'../../..');
const bench=join(repo,'.artifacts/real-prototype-benchmark');
const server=spawn('python3',['-m','http.server','8899','--bind','127.0.0.1','--directory',bench],{stdio:'ignore'});
await new Promise(r=>setTimeout(r,500));
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
const evidence={schema:'famtastic.real-prototype-benchmark.v1',builders:{},gated:{gemini:'CLI installed but no headless authentication configured'}};
try{
  for(const builder of ['codex','claude']){
    const errors=[];page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
    const response=await page.goto(`http://127.0.0.1:8899/${builder}/index.html`,{waitUntil:'networkidle'});
    const desktop=join(bench,`${builder}-desktop.png`);await page.screenshot({path:desktop,fullPage:true});
    const desktopHash=createHash('sha256').update(readFileSync(desktop)).digest('hex');
    const heroLoaded=await page.locator('img').first().evaluate(img=>img.complete&&img.naturalWidth>1000);
    const noOverflow=await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth);
    await page.setViewportSize({width:390,height:844});
    const mobile=join(bench,`${builder}-mobile.png`);await page.screenshot({path:mobile,fullPage:true});
    const mobileHash=createHash('sha256').update(readFileSync(mobile)).digest('hex');
    const mobileNoOverflow=await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth);
    evidence.builders[builder]={http_ok:response?.ok()===true,hero_loaded:heroLoaded,desktop_no_overflow:noOverflow,mobile_no_overflow:mobileNoOverflow,
      h1_count:await page.locator('h1').count(),button_count:await page.locator('button').count(),console_errors:errors,
      screenshots:[{file:`${builder}-desktop.png`,sha256:desktopHash},{file:`${builder}-mobile.png`,sha256:mobileHash}]};
    await page.setViewportSize({width:1440,height:1000});
  }
}finally{await browser.close();server.kill('SIGTERM')}
evidence.passed=Object.values(evidence.builders).every(b=>b.http_ok&&b.hero_loaded&&b.desktop_no_overflow&&b.mobile_no_overflow&&b.h1_count===1&&b.button_count>0&&b.console_errors.length===0);
writeFileSync(join(bench,'evidence.json'),JSON.stringify(evidence,null,2)+'\n');
if(!evidence.passed)process.exit(1);
console.log('PASS: independent Codex and Claude sites rendered at desktop and mobile');
console.log(`Evidence: ${join(bench,'evidence.json')}`);
