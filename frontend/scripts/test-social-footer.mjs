import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createServer } from 'vite';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router';
import { SOCIAL_PROFILES, enabledSocialProfiles, isEnabledSocialProfile, trackSocialClick } from '../src/lib/socialProfiles.js';

const fb = SOCIAL_PROFILES[0];
test('only six configured destinations; known dormant platforms remain hidden', () => {
  assert.deepEqual(enabledSocialProfiles().map(p=>p.id), ['facebook','instagram','youtube','x','tiktok','email']);
  assert.equal(enabledSocialProfiles([fb,fb]).length,1);
  for (const href of [null,'','#','javascript:alert(1)','data:text/html,test','http://facebook.com/me','https://evil.test/me','https://facebook.com.evil.test/me','https://user:pass@facebook.com/me','https://facebook.com/me?token=private','https://facebook.com/#','https://facebook.com/placeholder',' https://facebook.com/me','https://facebook.com/']) {
    assert.equal(isEnabledSocialProfile({...fb,href}),false,String(href));
  }
  assert.equal(isEnabledSocialProfile({...fb,id:'unknown'}),false);
  assert.equal(isEnabledSocialProfile({...fb,enabled:false}),false);
});
test('exact existing profiles, documented YouTube successor and personal X remain explicit', () => {
  assert.equal(fb.href,'https://www.facebook.com/people/FAMTastic-Designs/100038380452647/');
  assert.equal(SOCIAL_PROFILES[1].href,'https://www.instagram.com/famtasticdesigns/');
  assert.equal(SOCIAL_PROFILES[2].href,'https://youtube.com/@FAMtastic-Designs');
  assert.match(SOCIAL_PROFILES[3].accountLabel,/personal/);
  assert.equal(SOCIAL_PROFILES[5].href,'mailto:hello@famtasticdesigns.com');
});
test('one event of each kind, absent or throwing analytics never blocks activation', () => {
  const calls=[];globalThis.window={dispatchEvent:e=>calls.push([e.type,e.detail]),gtag:(...args)=>calls.push(args)};
  trackSocialClick(fb);
  assert.deepEqual(calls,[['famtastic:social-click',{platform:fb.id,destination:fb.href}],['event','social_profile_click',{social_platform:fb.id,social_destination:fb.href}]]);
  delete window.gtag;assert.doesNotThrow(()=>trackSocialClick(fb));
  window.gtag=()=>{throw Error('provider unavailable')};assert.doesNotThrow(()=>trackSocialClick(fb));
  window.dispatchEvent=()=>{throw Error('observer unavailable')};assert.doesNotThrow(()=>trackSocialClick(fb));
  delete globalThis.window;assert.doesNotThrow(()=>trackSocialClick(fb));
});
test('rendered React footer retains CMS limits, empty fallbacks, safe labels and canonical identity', async () => {
  const vite=await createServer({root:new URL('..',import.meta.url).pathname,server:{middlewareMode:true},appType:'custom'});
  try {
    const {default:SiteFooter}=await vite.ssrLoadModule('/src/components/v1/SiteFooter.jsx');
    const {default:SocialBadge}=await vite.ssrLoadModule('/src/components/v1/SocialBadge.jsx');
    const render=(props={})=>renderToStaticMarkup(React.createElement(MemoryRouter,null,React.createElement(SiteFooter,props)));
    const items=Array.from({length:10},(_,i)=>({slug:`item-${i}`,title:`CMS & Title ${i}`}));
    const html=render({services:items,packages:items});
    assert.equal((html.match(/href="\/services\/item-/g)||[]).length,6);
    assert.equal((html.match(/href="\/packages\/item-/g)||[]).length,7);
    for(const href of ['/services','/packages','/blog','/about','/work','/faq','/contact','/privacy-policy','/terms-of-service']) assert.ok(html.includes(`href="${href}"`));
    for(const href of ['/services','/packages'])assert.ok(render().includes(`href="${href}"`));
    assert.match(html,/CMS &amp; Title/);assert.match(html,/famtastic-designs-logo-v1.png/);
    assert.match(html,/class="v1-footer__atmosphere" aria-hidden="true"/);
    assert.match(html,/aria-pressed="false"[^>]*>Pause background motion/);
    assert.match(html,/v1-footer__company/);
    assert.ok(html.includes(String(new Date().getFullYear())));
    assert.equal((html.match(/class="v1-social-signal"/g)||[]).length,1);
    assert.equal((html.match(/class="fam-social-badge"/g)||[]).length,6);
    assert.equal((html.match(/rel="noopener noreferrer"/g)||[]).length,5);
    assert.equal((html.match(/opens in a new tab/g)||[]).length,5);
    for(const p of enabledSocialProfiles())assert.ok(html.includes(`>${p.label}</span>`));
    assert.doesNotMatch(html,/orbit|LIVE|Followed|Connected|Success|href="#"/);
    for(const profile of SOCIAL_PROFILES) {
      const preview=renderToStaticMarkup(React.createElement(SocialBadge,{profile,preview:true,size:48}));
      assert.match(preview,/Preview only/);assert.doesNotMatch(preview,/<a\b|tabindex=/);
      if(!profile.enabled)assert.equal(renderToStaticMarkup(React.createElement(SocialBadge,{profile})), '');
    }
  } finally {await vite.close();}
});
test('social badges retain static idle marks and safe navigation',()=>{
  const read=p=>readFileSync(new URL(p,import.meta.url),'utf8');
  const code=read('../src/components/v1/SocialBadge.jsx');
  assert.doesNotMatch(code,/preventDefault|setTimeout|setInterval|fetch\(|onMouse|onPointer|useState/);
  const css=read('../src/components/v1/social-footer.css');
  assert.match(css,/prefers-reduced-motion/);assert.match(css,/:focus-visible/);assert.match(css,/:active/);
  assert.doesNotMatch(css,/infinite|@keyframes/);
  assert.doesNotMatch(read('../src/index.css'),/v1-social-orbit|v1-social-node-float|v1-social-signal__orbit/);
});
test('background-only slow fade is pausable, reduced-motion safe, and adds no image guess',()=>{
  const css=readFileSync(new URL('../src/components/v1/footer-atmosphere.css',import.meta.url),'utf8');
  assert.match(css,/24s ease-in-out/);
  assert.match(css,/32s ease-in-out/);
  assert.match(css,/radial-gradient/);
  assert.match(css,/::before \{ animation-play-state: paused/);
  assert.match(css,/::before \{ animation: none !important/);
  assert.match(css,/pointer-events: none/);
  assert.match(css,/animation-play-state: paused/);
  assert.match(css,/prefers-reduced-motion: reduce/);
  assert.match(css,/animation: none !important/);
  assert.doesNotMatch(css,/url\(|filter:|setInterval/);
});
