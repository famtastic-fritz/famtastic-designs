const {chromium}=require('../frontend/node_modules/@playwright/test');
const assert=require('node:assert/strict');
const luminance=hex=>hex.match(/\w\w/g).map(h=>parseInt(h,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);
const contrast=(a,b)=>{const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);};
for(const [foreground,background] of [['7cfc00','070907'],['f7f7f4','101310'],['070907','7cfc00']])assert.ok(contrast(foreground,background)>=4.5);
(async()=>{const b=await chromium.launch();try{
 const p=await b.newPage({viewport:{width:390,height:900},reducedMotion:'reduce'});
 await p.goto('http://127.0.0.1:4187/');await p.waitForLoadState('networkidle');
 const button=p.locator('.v1-hero .v1-btn--primary');await button.focus();await p.keyboard.press('Tab');await p.keyboard.press('Shift+Tab');
 const styles=await button.evaluate(e=>{const s=getComputedStyle(e);return {outline:s.outlineStyle,width:s.outlineWidth,transition:s.transitionDuration,focus:e.matches(':focus-visible'),height:e.getBoundingClientRect().height};});
 assert.ok(styles.focus);assert.equal(styles.outline,'solid');assert.equal(styles.width,'2px');assert.equal(styles.transition,'0s');assert.ok(styles.height>=44);
 const crowns=await p.locator('.fam-crown').all();for(const c of crowns){assert.equal(await c.getAttribute('alt'),'');assert.equal(await c.getAttribute('aria-hidden'),'true');}
 console.log('PASS: three core contrast pairs >=4.5:1, real keyboard-focus outline, reduced-motion transition disabled, CTA >=44px, decorative crown semantics. Not a full WCAG audit.');
 }finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1});
