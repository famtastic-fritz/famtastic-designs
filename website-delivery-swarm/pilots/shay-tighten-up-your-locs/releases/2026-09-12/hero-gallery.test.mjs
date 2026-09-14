import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initHeroGallery, swipeDirection, GALLERY_INTERVAL } from './hero-gallery.js';
function setup(reduced = false) {
  const element = () => ({ hidden: false, textContent: '', attrs: {}, handlers: {}, addEventListener(name, fn) { this.handlers[name] = fn; }, setAttribute(name,value){this.attrs[name]=value;}, matches(){return true;}, closest(){return null;} });
  const controls = Object.fromEntries(['rotation', 'gallery-status', 'gallery-controls'].map(key => [`[data-${key}]`, element()]));
  const slides = Array.from({length:4}, (_, i) => ({ hidden:i !== 0, img:{complete:true,naturalWidth:100}, caption:{textContent:`Image ${i+1}`}, querySelector(key){ return key==='img'?this.img:this.caption; } }));
  const dots=Array.from({length:4},element);
  const root = {...element(), dataset:{}, contains:target=>target===root||dots.includes(target), querySelector:key => controls[key], querySelectorAll:key=>key==='[data-slide]'?slides:dots};
  const doc = {...element(), hidden:false, querySelector:() => root};
  const motion = {...element(), matches:reduced};
  let tick, interval;
  const win = {matchMedia:() => motion, clearTimeout:() => { tick = undefined; }, setTimeout:(fn,ms) => {tick = fn;interval=ms;return 1;}};
  initHeroGallery(doc, win);
  return {root,doc,motion,slides,dots,controls,click:key=>controls[`[data-${key}]`].handlers.click(),advance:()=>tick?.(),running:()=>!!tick,interval:()=>interval};
}
test('automatically rotates every6seconds and wraps all fourimages',()=>{const s=setup();assert.equal(s.interval(),GALLERY_INTERVAL);for(let i=1;i<=4;i++){s.advance();assert.equal(s.slides[i%4].hidden,false);}});
test('dots select images and reset a running timer',()=>{const s=setup();s.dots[3].handlers.click();assert.equal(s.slides[3].hidden,false);assert.equal(s.dots[3].attrs['aria-pressed'],'true');assert.equal(s.running(),true);s.advance();assert.equal(s.slides[0].hidden,false);});
test('keyboard focus pauses and departure resumes a fresh interval',()=>{const s=setup();s.root.handlers.focusin({target:s.root});assert.equal(s.running(),false);s.root.handlers.focusout({relatedTarget:null});assert.equal(s.running(),true);});
test('pointer focus does not permanently stop autoplay afterdotselection',()=>{const s=setup();s.root.handlers.focusin({target:{matches:()=>false}});s.dots[2].handlers.click();assert.equal(s.running(),true);});
test('explicit pause stays paused across focus and hover until resumed',()=>{const s=setup();s.click('rotation');s.root.handlers.mouseenter?.();s.root.handlers.mouseleave?.();s.root.handlers.focusout({relatedTarget:null});assert.equal(s.running(),false);s.click('rotation');assert.equal(s.running(),true);});
test('hover and mobile-emulated hover keep looping while hidden documents pause',()=>{const s=setup();assert.equal(s.root.handlers.mouseenter,undefined);assert.equal(s.root.handlers.mouseleave,undefined);s.root.handlers.mouseenter?.();assert.equal(s.running(),true);s.advance();assert.equal(s.slides[1].hidden,false);s.root.handlers.focusin({target:{matches:()=>false}});s.dots[3].handlers.click();s.root.handlers.mouseenter?.();assert.equal(s.running(),true);s.advance();assert.equal(s.slides[0].hidden,false);s.doc.hidden=true;s.doc.handlers.visibilitychange();assert.equal(s.running(),false);s.doc.hidden=false;s.doc.handlers.visibilitychange();assert.equal(s.running(),true);});
test('reduced motion starts paused and preference changes stopplayback',()=>{const s=setup(true);assert.equal(s.running(),false);s.click('rotation');assert.equal(s.running(),true);s.motion.handlers.change();assert.equal(s.running(),false);});
test('unloaded or broken images are skipped',()=>{const s=setup();s.slides[1].img.naturalWidth=0;s.slides[2].img.complete=false;s.advance();assert.equal(s.slides[3].hidden,false);});
test('horizontal swipechangesimage whilevertical movementandshortdragsdonot',()=>{assert.equal(swipeDirection({x:100,y:100},{x:20,y:110}),1);assert.equal(swipeDirection({x:100,y:100},{x:180,y:90}),-1);assert.equal(swipeDirection({x:100,y:100},{x:90,y:200}),0);assert.equal(swipeDirection({x:100,y:100},{x:130,y:100}),0);const s=setup();s.root.handlers.pointerdown({pointerId:1,clientX:180,clientY:100,target:s.root});assert.equal(s.running(),false);s.root.handlers.pointerup({pointerId:1,clientX:90,clientY:110});assert.equal(s.slides[1].hidden,false);assert.equal(s.running(),true);});
test('keyboard arrows wrap and Space toggles persistentpause',()=>{const s=setup();const event=key=>({key,target:s.root,preventDefault(){}});s.root.handlers.keydown(event('ArrowLeft'));assert.equal(s.slides[3].hidden,false);s.root.handlers.keydown(event(' '));assert.equal(s.running(),false);s.root.handlers.keydown(event('p'));assert.equal(s.running(),true);});
