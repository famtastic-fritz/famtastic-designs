import {test} from 'node:test';
import assert from 'node:assert/strict';
import {initNewsletter,newsletterPayload,isPendingSubscription} from './newsletter.js';
function setup(response={ok:true,status:200,json:async()=>({status:'pending'})}){
  const form={dataset:{},elements:{email:{value:' test@example.test '},consent:{checked:true},website:{value:''}},reportValidity:()=>true,addEventListener(name,fn){this.submit=fn;},reset(){this.resetCalled=true;}};
  const button={disabled:true},status={dataset:{}},calls=[];
  const doc={getElementById:id=>({'newsletter-form':form,'newsletter-submit':button,'newsletter-status':status})[id]};
  const win={fetch:async(...args)=>{calls.push(args);if(response instanceof Error)throw response;return response;}};
  initNewsletter(doc,win);return{form,button,status,calls,submit:()=>form.submit({preventDefault(){}})};
}
test('onlyemailplusseparatemarketingconsentandhoneypotleavetheform',()=>{const s=setup();s.form.elements.name={value:'Private name'};assert.deepEqual(newsletterPayload(s.form),{email:'test@example.test',consent:true,website:''});});
test('uncheckedmarketingconsentmakesnosignuprequest',async()=>{const s=setup();s.form.elements.consent.checked=false;await s.submit();assert.equal(s.calls.length,0);});
test('pendingreceiptisnotanactivesubscription',async()=>{const s=setup();await s.submit();assert.equal(s.calls[0][0],'/api/newsletter/signup');assert.equal(s.calls[0][1].credentials,'omit');assert.equal(s.status.dataset.state,'success');assert.match(s.status.textContent,/not subscribed until you confirm/);assert.equal(s.form.resetCalled,true);assert.equal(s.button.disabled,false);});
test('invalidresponsecannotdisplayasuccess',async()=>{assert.equal(isPendingSubscription({status:'active'}),false);const s=setup({ok:true,status:200,json:async()=>({ok:true})});await s.submit();assert.equal(s.status.dataset.state,'error');assert.equal(s.form.resetCalled,undefined);});
test('429and503arehonestactionablefailures',async()=>{for(const code of[429,503]){const s=setup({ok:false,status:code});await s.submit();assert.equal(s.status.dataset.state,'error');assert.equal(s.form.resetCalled,undefined);assert.match(s.status.textContent,code===429?/wait a moment/:/temporarily unavailable/);}});
test('uncertainnetworkresultdoesnotclaimfailedpersistenceorsubscription',async()=>{const s=setup(new Error('offline'));await s.submit();assert.match(s.status.textContent,/could not verify/);assert.match(s.status.textContent,/Check your inbox/);});
