import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { pathToFileURL, fileURLToPath } from 'node:url';
const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const cuaMode = process.argv.includes('--cua');
const deps = process.env.STAGING_REVIEW_TEST_DEPENDENCIES || path.join(repo, 'frontend/node_modules');
const { createServer } = await import(pathToFileURL(path.join(deps, 'vite/dist/node/index.js')));
const { default: reactPlugin } = await import(pathToFileURL(path.join(deps, '@vitejs/plugin-react/dist/index.js')));
const { chromium } = await import(pathToFileURL(path.join(deps, 'playwright/index.mjs')));
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'agency-review-ui-'));
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="root"></div><script type="module" src="/entry.jsx"></script></body></html>`;
fs.writeFileSync(path.join(root, 'index.html'), html);
fs.writeFileSync(path.join(root, 'entry.jsx'), `import React, {useState} from 'react';
import {createRoot} from 'react-dom/client';
import {StagingReview,customerNextStep} from '${repo}/frontend/src/components/portal/PortalProjectsView.jsx';
window.selectedBuildNextStep = customerNextStep;
import {acceptWebsiteStagingReview} from '${repo}/frontend/src/api/customer.js';
import {acceptDisplayedStagingReview} from '${repo}/frontend/src/api/stagingReview.js';
import '${repo}/frontend/src/index.css';
import '${repo}/frontend/src/portal.css';
function App(){ const [hash,setHash]=useState('a'.repeat(64)); const [error,setError]=useState(''); return <div className="portal-app"><main className="portal-main"><section className="portal-panel"><StagingReview request={{public_id:'synthetic-request',staging_review_status:'pending',staging_preview:{status:'deployed',url:'https://synthetic.famtasticinc.com/',receipt_hash:hash}}} busy={false} onAccept={async(id,h)=>{try{await acceptDisplayedStagingReview(id,h,{accept:acceptWebsiteStagingReview,refresh:async()=>setHash('b'.repeat(64))});}catch(e){setError(e.message)}}}/><p role="alert">{error}</p></section></main></div>;} createRoot(document.getElementById('root')).render(<App/>);`);
const fixtureRequests = [];
const localFixture = {
  name: 'local-staging-review-cua-fixture',
  configureServer(vite) {
    if (!cuaMode) return;
    vite.middlewares.use((req, res, next) => {
      if (req.url === '/session/token' && req.method === 'GET') {
        res.end('synthetic-csrf'); return;
      }
      if (req.url === '/api/customer/website-requests/synthetic-request/staging-review/accept' && req.method === 'POST') {
        let body = '';
        req.on('data', chunk => { body += chunk; });
        req.on('end', () => {
          fixtureRequests.push({ body: JSON.parse(body), csrf: req.headers['x-csrf-token'] === 'synthetic-csrf' });
          console.log(JSON.stringify({ fixture: 'local-only-no-provider', request: fixtureRequests.at(-1), count: fixtureRequests.length }));
          res.statusCode = 422; res.setHeader('Content-Type', 'application/json');
          res.end(JSON.stringify({ error: 'staging_review_not_ready', message: 'Review the updated website before accepting.' }));
        });
        return;
      }
      // Never proxy an unexpected API operation to a live environment.
      if (req.url.startsWith('/api/') || req.url.startsWith('/web/')) {
        res.statusCode = 404; res.end('Isolated fixture: unsupported request'); return;
      }
      next();
    });
  },
};
const server = await createServer({ root, configFile: false, plugins: [reactPlugin(), localFixture], resolve: { alias: { react: path.join(deps, 'react'), 'react-dom': path.join(deps, 'react-dom') } }, server: { host: '127.0.0.1', port: cuaMode ? 4219 : 0, strictPort: cuaMode, fs: { allow: [root, repo, deps] } } });
let browser;
try {
  await server.listen();
  if (cuaMode) {
    console.log(`CUA fixture: ${server.resolvedUrls.local[0]} (local synthetic API only; stop with SIGINT)`);
    await new Promise(resolve => { process.once('SIGINT', resolve); process.once('SIGTERM', resolve); });
  } else {
  browser = await chromium.launch({headless:true});
  const page = await browser.newPage({viewport:{width:390,height:844}});
  const sent = [];
  await page.route('**/session/token', route => route.fulfill({body:'synthetic-csrf'}));
  await page.route('**/api/customer/website-requests/**/staging-review/accept', route => { sent.push(JSON.parse(route.request().postData())); return route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({error:'staging_review_not_ready',message:'Review the updated website before accepting.'})}); });
  await page.goto(server.resolvedUrls.local[0]);
  const button = page.getByRole('button', {name:'Accept this website revision'});
  await button.waitFor(); assert.equal(await button.isDisabled(), true);
  for (const staging_status of ['failed', 'planning_failed', 'planning_blocked']) {
    const step = await page.evaluate(status => window.selectedBuildNextStep({proof_review_status:'selected',staging_status:status}), staging_status);
    assert.equal(step.tone, 'attention'); assert.equal(step.action, 'support');
    assert.equal(step.owner, 'famtastic');
  }
  await page.getByRole('checkbox').check(); assert.equal(await button.isEnabled(), true);
  await button.click(); await page.getByRole('alert').filter({hasText:'Review the updated website'}).waitFor();
  assert.deepEqual(sent,[{receipt_hash:'a'.repeat(64)}]);
  assert.equal(await page.getByRole('checkbox').isChecked(),false);
  assert.equal(await button.isDisabled(),true);
  for (const width of [320,390,768,1280]) {
    await page.setViewportSize({width,height:844});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true, `containment ${width}`);
    assert.ok((await button.boundingBox()).height>=44, `touch target ${width}`);
  }
  console.log('PASS: actual review component + API; blocked/failed selection is attention, stale hash refresh clears acceptance, one request only, 320/390/768/1280px containment and 44px button.');
  }
} finally { if(browser) await browser.close(); await server.close(); fs.rmSync(root,{recursive:true,force:true}); }
