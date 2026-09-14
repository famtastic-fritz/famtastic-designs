import test from 'node:test';
import assert from 'node:assert/strict';
import { initAnalytics } from './site.js';
function fixture(choice, navigator = {}) {
  const scripts = [], cookies = [];
  const win = {location:{origin:'https://tightenupyourlocs.com',hostname:'tightenupyourlocs.com'},navigator,localStorage:{getItem:()=>choice}};
  const doc = {getElementById:()=>scripts.find(s=>s.id==='locs-analytics'),createElement:()=>({}),head:{append:s=>scripts.push(s)},get cookie(){return '_ga=old; session=untouched';},set cookie(v){cookies.push(v);}};
  return {win,doc,scripts,cookies};
}
test('basic measurement starts without a click but never grants storage or ads', () => {
  const f=fixture(null), event=initAnalytics(f.win,f.doc,'G-V8M437DWV0');
  assert.equal(f.scripts.length,1);
  const queue=f.win.dataLayer.map(a=>Array.from(a));
  assert.deepEqual(queue[0],['consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'}]);
  assert.equal(queue.filter(a=>a[0]==='event'&&a[1]==='page_view').length,1);
  event('request_saved'); event('arbitrary_private_value');
  assert.equal(f.win.dataLayer.at(-1)[1],'request_saved');
  assert.ok(f.cookies.every(c=>c.startsWith('_ga=')));
  assert.equal(f.win.dataLayer.at(-1)[2].page_location,'https://tightenupyourlocs.com/');
  initAnalytics(f.win,f.doc,'G-V8M437DWV0');
  assert.equal(f.scripts.length,1);
});
for (const [label,choice,nav] of [['old refusal','deny',{}],['GPC',null,{globalPrivacyControl:true}],['DNT',null,{doNotTrack:'1'}]]) {
  test(label+' suppresses Google loading and events',()=>{
    const f=fixture(choice,nav); initAnalytics(f.win,f.doc,'G-V8M437DWV0')('request_saved');
    assert.equal(f.scripts.length,0); assert.equal(f.win.dataLayer,undefined);
    assert.equal(f.win['ga-disable-G-V8M437DWV0'],true);
  });
}
test('old allowance is not upgraded to cookie consent in the new mode',()=>{
  const f=fixture('allow');initAnalytics(f.win,f.doc,'G-V8M437DWV0');
  assert.equal(f.win.dataLayer[0][2].analytics_storage,'denied');
});
test('missing configuration is inert and blocked local storage does not crash booking',()=>{
  const f=fixture();initAnalytics(f.win,f.doc,'')('request_saved'); assert.equal(f.scripts.length,0);
  f.win.localStorage.getItem=()=>{throw Error('storage unavailable');};
  assert.doesNotThrow(()=>initAnalytics(f.win,f.doc,'G-V8M437DWV0')); assert.equal(f.scripts.length,1);
});
