import {execFileSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import assert from 'node:assert/strict';
const nonce=randomBytes(16).toString('hex'), host='nineoo@p3plzcpnl506112.prod.phx3.secureserver.net';
const probe=action=>JSON.parse(execFileSync('ssh',[host,'php /home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app/newsletter-check.php'],{input:JSON.stringify({action,nonce}),encoding:'utf8',stdio:['pipe','pipe','pipe']}));
const cookies=new Map();let begun=false;
async function request(path,options={}){
 const r=await fetch('https://tightenupyourlocs.com'+path,{redirect:'manual',signal:AbortSignal.timeout(20000),...options,
  headers:{Accept:'text/html',Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; '),...options.headers}});
 if(options.headers?.Cookie!=='')for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');cookies.set(p.slice(0,i),p.slice(i+1));}return r;
}
try{
 assert.equal(probe('begin').status,'ready');begun=true;
 const post=()=>request('/api/newsletter/signup',{method:'POST',headers:{Cookie:'',Accept:'application/json','Content-Type':'application/json',Origin:'https://tightenupyourlocs.com'},body:JSON.stringify({email:'hello@tightenupyourlocs.com',consent:true,website:''})});
 let r=await post();assert.equal(r.status,200);assert.equal((await r.json()).status,'pending');let data=probe('read');assert.equal(data.status,'pending');assert.equal(data.outbox_count,1);
 r=await post();assert.equal(r.status,200);assert.equal(probe('read').outbox_count,1);
 probe('dispatch');
 data=probe('read');
 for(let i=0;data.outbox_status==='sending'&&i<12;i++){await new Promise(resolve=>setTimeout(resolve,1500));data=probe('read');}
 assert.equal(data.outbox_status,'sent');assert.ok(data.provider_message_id);
 for(const url of Object.values(data.links))assert.equal(new URL(url).origin,'https://tightenupyourlocs.com');
 const confirm=new URL(data.links.confirmation_url).pathname,leave=new URL(data.links.unsubscribe_url).pathname;
 r=await request(confirm);assert.equal(r.status,200);let html=await r.text();assert.equal(probe('read').status,'pending');let csrf=/name="_token" value="([^"]+)"/.exec(html)?.[1];assert.ok(csrf);
 r=await request(confirm,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:''});assert.equal(r.status,419);
 r=await request(confirm,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:csrf})});assert.equal(r.status,200);assert.equal(probe('read').status,'subscribed');
 r=await request(confirm);assert.equal(r.status,404);
 r=await request(leave);assert.equal(r.status,200);html=await r.text();assert.equal(probe('read').status,'subscribed');csrf=/name="_token" value="([^"]+)"/.exec(html)?.[1];
 r=await request(leave,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:csrf})});assert.equal(r.status,200);assert.equal(probe('read').status,'unsubscribed');
 r=await request('/admin/api/newsletter/subscribers',{headers:{Accept:'application/json'}});assert.equal(r.status,401);
 console.log(JSON.stringify({status:'passed',checks:['durable_signup','duplicate_queues_once','own_smtp_accepted','get_does_not_subscribe','csrf_required','confirmation_persists','confirmation_single_use','unsubscribe_persists','private_subscribers_denied'],provider_message_id:data.provider_message_id,test_recipient:'own_business_mailbox',customer_campaigns:0}));
}finally{if(begun)console.log(JSON.stringify({cleanup:probe('cleanup')}));}
