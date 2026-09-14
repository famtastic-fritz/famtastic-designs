import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initHeroGallery } from './hero-gallery.js';
function setup(reduced = false) {
  const element = () => ({ hidden: false, textContent: '', handlers: {}, addEventListener(name, fn) { this.handlers[name] = fn; } });
  const controls = Object.fromEntries(['rotation', 'slide-count', 'previous', 'next', 'gallery-controls'].map(key => [`[data-${key}]`, element()]));
  const slides = Array.from({length:4}, (_, i) => ({ hidden:i !== 0, img:{complete:true,naturalWidth:100}, querySelector(){ return this.img; } }));
  const root = {...element(), dataset:{}, querySelector:key => controls[key], querySelectorAll:() => slides};
  const doc = {...element(), hidden:false, querySelector:() => root};
  const motion = {...element(), matches:reduced};
  let tick;
  const win = {matchMedia:() => motion, clearTimeout:() => { tick = undefined; }, setTimeout:fn => {tick = fn; return 1;}};
  initHeroGallery(doc, win);
  return {root,doc,motion,slides,controls,click:key => controls[`[data-${key}]`].handlers.click(), advance:() => tick?.(), running:() => !!tick};
}
test('automatically rotates and wraps all four images', () => { const s=setup(); for(let i=1;i<=4;i++){s.advance(); assert.equal(s.slides[i%4].hidden,false);} });
test('manual navigation pauses, previous wraps, play resumes', () => {const s=setup();s.click('previous');assert.equal(s.slides[3].hidden,false);assert.equal(s.running(),false);s.click('rotation');assert.equal(s.running(),true);});
test('pointer pause survives focus event before click', () => {const s=setup();s.controls['[data-rotation]'].handlers.pointerdown();s.root.handlers.focusin();s.click('rotation');assert.equal(s.running(),false);});
test('keyboard focus pauses permanently until explicit play', () => {const s=setup();s.root.handlers.focusin();s.root.handlers.mouseleave();assert.equal(s.running(),false);});
test('hover and document visibility suspend automatic rotation', () => {const s=setup();s.root.handlers.mouseenter();assert.equal(s.running(),false);s.root.handlers.mouseleave();assert.equal(s.running(),true);s.doc.hidden=true;s.doc.handlers.visibilitychange();assert.equal(s.running(),false);});
test('reduced motion starts paused and changes stop playback', () => {const s=setup(true);assert.equal(s.running(),false);s.click('rotation');assert.equal(s.running(),true);s.motion.handlers.change();assert.equal(s.running(),false);});
test('unloaded or broken images are skipped', () => {const s=setup();s.slides[1].img.naturalWidth=0;s.slides[2].img.complete=false;s.advance();assert.equal(s.slides[3].hidden,false);});
