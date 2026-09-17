// Local component presentation only: no customer records, credentials or writes.
const { chromium } = require('../frontend/node_modules/@playwright/test');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const root = 'http://127.0.0.1:4187';
(async () => {
  const master = fs.readFileSync('frontend/public/brand/famtastic-designs-logo-v1.png');
  assert.equal(crypto.createHash('sha256').update(master).digest('hex'), 'ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950');
  assert.deepEqual(master, fs.readFileSync('backend/web/themes/custom/famtastic_admin/famtastic-designs-logo-v1.png'));
  const b = await chromium.launch();
  const entry = await (await fetch(root+'/src/main.jsx')).text();
  const dependency = name => entry.match(new RegExp('"([^" ]*/'+name+'\\.js\\?[^" ]+)"'))[1];
  try {
    for (const width of [320,390,768,1280]) {
      const p = await b.newPage({viewport:{width,height:900}});
      const errors=[]; p.on('pageerror',e=>{errors.push(e.message);console.error(e.message);});
      await p.route('**/logo-component-check',r=>r.fulfill({contentType:'text/html',body:`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div id="root"></div><script type="module">
      import RefreshRuntime from '/@react-refresh';
      RefreshRuntime.injectIntoGlobalHook(window);window.$RefreshReg$=()=>{};window.$RefreshSig$=()=>type=>type;window.__vite_plugin_react_preamble_installed__=true;
      const React=(await import('${dependency('react')}')).default;
      const {createRoot}=(await import('${dependency('react-dom_client')}')).default;
      const {MemoryRouter}=await import('${dependency('react-router')}');
      const {default:Nav}=await import('/src/components/portal/PortalNav.jsx');
      await import('/src/index.css');await import('/src/portal.css');
      function Demo(){const [menu,setMenu]=React.useState(false);return React.createElement('div',{className:'portal-app'+(menu?' menu-open':'')},React.createElement(Nav,{section:'home',menu,setMenu,go:()=>{},onSignOut:()=>{}}),React.createElement('main',{className:'portal-main'},'Local layout fixture — no account data'));}
      createRoot(document.getElementById('root')).render(React.createElement(MemoryRouter,null,React.createElement(Demo)));
      </script></body></html>`}));
      await p.goto(root+'/logo-component-check');
      await p.locator('.portal-logo img').evaluate(i=>i.decode());
      if(width<=900){await p.locator('.portal-menu-toggle').click();await p.waitForFunction(()=>document.querySelector('.portal-app').classList.contains('menu-open'));}
      const logo=await p.locator('.portal-logo img').boundingBox();assert.ok(Math.abs(logo.width/logo.height-3)<.02);
      assert.equal(await p.locator('.portal-logo').getAttribute('href'),'/');
      assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
      if(width<=900){
        const close=await p.locator('.portal-nav-head>button').boundingBox();assert.ok(close.x>=logo.x+logo.width,'Close button must not overlap logo');
        await p.locator('.portal-nav-head>button').click();await p.locator('.portal-menu-toggle').click();await p.keyboard.press('Escape');
        assert.equal(await p.locator('.portal-menu-toggle').getAttribute('aria-expanded'),'false');
      }
      await p.screenshot({path:`.local-email-preview/portal-brand-fixture-${width}.png`});
      assert.deepEqual(errors,[]);await p.close();
    }
    console.log('PASS: identical PNG, portal 320/390/768/1280, 3:1 logo, home link, mobile close/Escape, no overlap or overflow. Component fixtures only.');
  } finally {await b.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
