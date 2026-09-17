// Local-only presentation fixtures, never customer data or a sending transport.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('../frontend/node_modules/@playwright/test');
const out = '.local-email-preview/brand-dna';
const root = 'http://127.0.0.1:4187';
const baseline = '1566d531652fcf2ac0bde0cbc9120ba6f2db9c84';
const read = f => fs.readFileSync(f, 'utf8');
const old = f => execFileSync('git', ['show', `${baseline}:${f}`], { encoding: 'utf8' });
const theme = 'backend/web/themes/custom/famtastic_admin/';
const sizes = {512:'android-chrome-512x512.png',192:'android-chrome-192x192.png',180:'apple-touch-icon.png',48:'favicon-48x48.png',32:'favicon-32x32.png',16:'favicon-16x16.png'};
fs.mkdirSync(out, { recursive: true });
const head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
const tokens = read('frontend/public/brand/famtastic-dna.css');
const crown = `<img class="fam-crown fam-crown--subtle" src="${root}/brand/famtastic-crown-flat.png" alt="">`;
const logo = `<img src="${root}/brand/famtastic-designs-logo-v1.png" width="180" height="60" alt="FAMtastic Designs">`;
const fixtureLabel = '<p style="padding:12px;color:#fff;background:#303630;font:14px system-ui">LOCAL PRESENTATION FIXTURE — not an account, customer record or completed launch.</p>';
const results = [];
(async () => {
  // Read-only base-theme styles from the public login, because local Drupal
  // vendor/core is absent. Never fetch private pages or submit forms.
  const login = await (await fetch('https://famtasticdesigns.com/web/user/login')).text();
  const coreLinks = [...login.matchAll(/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"[^>]*>/g)]
    .map(m=>m[1]).filter(p=>p.startsWith('/web/core/'))
    .map(p=>`<link rel="stylesheet" href="https://famtasticdesigns.com${p}">`).join('');
  const browser = await chromium.launch();
  try {
    for (const phase of ['before','after']) {
      for (const surface of ['portal-dashboard','portal-proof','portal-success','admin-dashboard','admin-dense','admin-success']) {
        const admin = surface.startsWith('admin');
        const get = phase === 'before' ? old : read;
        let css = admin ? get(theme+'css/famtastic-admin.css') : get('frontend/src/index.css') + get('frontend/src/portal.css');
        // Fixture supplies the surrounding layout spacing normally supplied by
        // Drupal's page template; identical in both phases.
        if (admin) css += '.famtastic-admin-shell__main{padding:24px;box-sizing:border-box}';
        if (phase === 'after') css += tokens + read(admin ? theme+'css/brand-dna.css' : 'frontend/src/brand-dna.css');
        let content;
        if (admin) {
          const dense = read(theme+'tests/fixtures/future-module-primitives.html').match(/<main[\s\S]*?<\/main>/)[0];
          content = `<body class="path-admin famtastic-admin-shell">${fixtureLabel}<div class="famtastic-admin-shell__frame"><header class="famtastic-admin-shell__header"><nav class="famtastic-admin-nav"><a href="#review">${logo}</a><a class="famtastic-admin-nav__link" href="#review" aria-current="page">Operations</a><a class="famtastic-admin-nav__link" href="#review">Messages</a></nav></header>${surface==='admin-dense' ? dense : `<main class="famtastic-admin-shell__main"><div class="famtastic-hub__hero"><h1>${surface==='admin-success'?'Recorded completion — fixture':'Operations — fixture'}</h1><p>Existing native information hierarchy. No operational state has changed.</p></div>${surface==='admin-success'?'<div class="messages messages--status" role="status">Fixture: saved successfully. No real save or launch occurred.</div>':'<div class="famtastic-ops__table-scroll"><table><tr><th>Record</th><th>Next step</th></tr><tr><td>Illustrative record</td><td>Needs review</td></tr></table></div>'}</main>`}</div></body>`;
        } else {
          const panel = surface === 'portal-proof' ? '<article class="portal-panel"><span>Project review fixture</span><h2>Review your direction</h2><p>Proof content retains the customer’s own visual identity.</p><div style="height:200px;background:#efe2cd;color:#35251d;padding:24px">Customer-world placeholder — intentionally NOT agency themed</div><button type="button">Review project</button></article>' : surface === 'portal-success' ? '<article class="portal-panel lime"><span>Presentation state fixture</span><h2>Feedback saved — fixture</h2><p role="status">Illustrative existing status treatment. No feedback was submitted.</p><button type="button">Return to project</button></article>' : `<section class="portal-next-action"><div><span class="portal-eyebrow">${phase==='after'?crown:''}Your next decision</span><h2>Start your website brief</h2><p>Start with a guided brief. Your saved answers become the project record.</p></div><button type="button">Open Projects</button></section><section class="portal-command-grid"><article class="portal-panel"><span>Website Strategy</span><h2>Know what to do after launch</h2><p>Existing project information stays readable and operational.</p><button type="button">Open growth guidance</button></article></section>`;
          content = `<body><div class="portal-app"><aside class="portal-nav"><a class="portal-logo" href="#review">${logo}</a><nav><button class="active">Home</button><button>My Projects</button><button>Messages</button><button>Billing &amp; Orders</button></nav></aside><main class="portal-main">${fixtureLabel}${panel}</main></div></body>`;
        }
        const html = `<!doctype html><html lang="en"><head>${head}<title>${surface} ${phase} — fixture</title>${admin?coreLinks:''}<style>${css}</style></head>${content}</html>`;
        fs.writeFileSync(`${out}/${phase}-${surface}.html`,html);
        const page = await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
        await page.goto(`http://127.0.0.1:8765/brand-dna/${phase}-${surface}.html`);
        await page.evaluate(()=>Promise.all([...document.images].map(i=>i.decode())));
        await page.screenshot({path:`${out}/${phase}-${surface}.png`,fullPage:true});
        const overflow = await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
        assert.equal(overflow,false,surface);
        results.push({phase,surface,overflow,scope:'source-CSS presentation fixture, not authenticated workflow proof'});
        await page.close();
      }
    }
    const sheet = `<!doctype html><html lang="en"><head>${head}<title>Canonical crown — actual-size QA</title><link rel="icon" href="${root}/favicon-32x32.png"><style>body{margin:32px;background:#070907;color:#f7f7f4;font:16px system-ui}h1{font-size:28px}.row{display:flex;gap:28px;align-items:center;flex-wrap:wrap}figure{margin:0}figcaption{margin:8px 0 24px;color:#bfc8ba}.light{background:#f7f7f4;color:#070907;padding:24px}.light img{margin-right:24px}a{color:#7cfc00}</style></head><body><h1>Canonical crown / source-derived, not redrawn</h1><p>Actual CSS pixel sizes. Larger icons have subtle glow; 16/32 are flat with optical stroke compensation.</p><div class="row">${Object.entries(sizes).reverse().map(([s,f])=>`<figure><img src="${root}/${f}" width="${s}" height="${s}" alt="Crown at ${s} pixels"><figcaption>${s} × ${s}</figcaption></figure>`).join('')}</div><div class="light">${[48,32,16].map(s=>`<img src="${root}/${sizes[s]}" width="${s}" height="${s}" alt="${s}px on light surround">`).join('')}Light-surround check</div><p>Original logo untouched. SVG favicon contains the 32px raster derivative; no vector master is claimed.</p></body></html>`;
    fs.writeFileSync(`${out}/favicon-qa.html`,sheet);
    const p = await browser.newPage({viewport:{width:1440,height:1050}});
    await p.goto('http://127.0.0.1:8765/brand-dna/favicon-qa.html');
    await p.evaluate(()=>Promise.all([...document.images].map(i=>i.decode())));
    await p.screenshot({path:`${out}/favicon-qa.png`,fullPage:true});
    await p.goto(root);
    for (const [size,file] of Object.entries(sizes)) {
      const bytes=fs.readFileSync('frontend/public/'+file);
      assert.equal(bytes.readUInt32BE(16),Number(size));assert.equal(bytes.readUInt32BE(20),Number(size));
      assert.deepEqual(bytes,fs.readFileSync(theme+'brand/'+file));
      assert.equal((await p.request.get(root+'/'+file)).status(),200);
    }
    const ico=fs.readFileSync('frontend/public/favicon.ico');assert.equal(ico.readUInt16LE(2),1);assert.equal(ico.readUInt16LE(4),3);
    const links=await p.locator('link[rel="icon"],link[rel="apple-touch-icon"],link[rel="manifest"]').evaluateAll(es=>es.map(e=>e.href));
    assert.ok(links.length>=5);for(const url of links)assert.equal((await p.request.get(url)).status(),200);
    const manifest=await (await p.request.get(root+'/site.webmanifest')).json();assert.equal(manifest.theme_color,'#070907');assert.equal(manifest.icons.length,2);
    await p.close();
    const screens=['home-desktop','home-mobile','work','offer','portal-dashboard','portal-proof','portal-success','admin-dashboard','admin-dense','admin-success'];
    fs.writeFileSync(`${out}/index.html`,`<!doctype html><html lang="en"><head>${head}<title>FAMtastic DNA — review only</title><link rel="icon" href="${root}/favicon-32x32.png"><style>body{margin:32px;background:#070907;color:#f7f7f4;font:16px system-ui}a{color:#7cfc00}.pair{display:grid;grid-template-columns:1fr 1fr;gap:20px}img{width:100%;border:1px solid #35402e}figure{margin:0}h2{margin-top:48px}@media(max-width:700px){.pair{grid-template-columns:1fr}}</style></head><body><h1>FAMtastic DNA / preserve before enhancing</h1><p>Local review branch only. Public: actual Vite pages before/after. Portal/admin: labelled presentation fixtures using baseline/current source CSS, not authenticated workflow tests.</p><p><a href="favicon-qa.html">Canonical crown size sheet</a> · <a href="../index.html">Existing email preview</a> · <a href="${root}">Working public preview</a></p>${screens.map(s=>`<h2>${s}</h2><div class="pair"><figure><figcaption>Before</figcaption><img src="before-${s}.png" alt="Before ${s}" loading="lazy"></figure><figure><figcaption>After</figcaption><img src="after-${s}.png" alt="After ${s}" loading="lazy"></figure></div>`).join('')}</body></html>`);
    fs.writeFileSync(`${out}/fixture-results.json`,JSON.stringify(results,null,2));
    console.log('PASS: 12 before/after CSS fixtures, no horizontal overflow; six icon dimensions/copies/HTTP, ICO directory, HTML icon links and manifest. No authenticated or production tests.');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1});
