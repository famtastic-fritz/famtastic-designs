import {execFileSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import assert from 'node:assert/strict';
const nonce=randomBytes(16).toString('hex'),password=randomBytes(32).toString('hex');
const ssh='nineoo@p3plzcpnl506112.prod.phx3.secureserver.net';
const owner=action=>execFileSync('ssh',['-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',ssh,'php /home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app/hosted-auth-owner.php'],{input:JSON.stringify({action,nonce,password}),encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
const booking=enabled=>execFileSync('ssh',['-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',ssh,'php /home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app/activate.php '+(enabled?'--enable-booking':'--disable-booking')],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const cookies=new Map();const checks=[];
async function request(path,options={}) {
 const r=await fetch('https://tightenupyourlocs.com'+path,{redirect:'manual',signal:AbortSignal.timeout(25000),...options,headers:{Accept:'text/html',Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; '),...options.headers}});
 for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}
 return r;
}
try {
 assert.equal(owner('create'),'temporary_owner_created');
 let r=await request('/admin/login');assert.equal(r.status,200);let html=await r.text();let csrf=/name="_token" value="([^"]+)"/.exec(html)?.[1];assert.ok(csrf);
 const cookie=r.headers.getSetCookie().find(c=>c.startsWith('__Host-locs_session='));assert.ok(cookie&&/secure/i.test(cookie)&&/httponly/i.test(cookie)&&/samesite=lax/i.test(cookie));checks.push('host_only_secure_http_only_session');
 r=await request('/admin/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({email:'release-check-'+nonce+'@example.invalid',password})});assert.equal(r.status,419);checks.push('missing_csrf_rejected');
 r=await request('/admin/login');html=await r.text();csrf=/name="_token" value="([^"]+)"/.exec(html)?.[1];
 r=await request('/admin/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:csrf,email:'release-check-'+nonce+'@example.invalid',password})});assert.equal(r.status,302);assert.equal(r.headers.get('location'),'https://tightenupyourlocs.com/admin');checks.push('independent_password_login');
 r=await request('/admin');assert.equal(r.status,301);assert.equal(r.headers.get('location'),'https://tightenupyourlocs.com/admin/');
 r=await request('/admin/');assert.equal(r.status,200);html=await r.text();assert.match(html,/Tighten Up Your Locs/);csrf=/name="csrf-token" content="([^"]+)"/.exec(html)?.[1];assert.ok(csrf);checks.push('authenticated_admin_rendered');
 for(const section of ['requests','appointments','openings']){r=await request('/admin/api/'+section,{headers:{Accept:'application/json'}});assert.equal(r.status,200);const d=await r.json();assert.ok(Array.isArray(d[section]));assert.equal(d[section].length,0);}checks.push('own_empty_database_authenticated_reads');
 booking(true);
 const publicPost=key=>request('/api/booking-request/site-dffd4cb9c3aa47fd',{method:'POST',headers:{Cookie:'',Accept:'application/json','Content-Type':'application/json',Origin:'https://tightenupyourlocs.com'},body:JSON.stringify({name:'Release verification (temporary)',email:'release-check-'+nonce+'@example.invalid',service_key:'question',requested_window:'Controlled release verification',consent:'on',idempotency_key:key})});
 const key=randomBytes(16).toString('hex');r=await publicPost(key);assert.equal(r.status,200);const receipt=await r.json();assert.equal(receipt.status,'received');assert.match(receipt.reference,/^[a-f0-9-]{36}$/);r=await publicPost(key);assert.equal((await r.json()).reference,receipt.reference);checks.push('public_http_request_committed_and_replay_safe');
 const post=body=>request('/admin/api/appointments',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});
 const start=Math.floor(Date.now()/1000)+86400;const command={action:'confirm',request_id:receipt.reference,expected_version:1,starts_at:start,ends_at:start+3600,idempotency_key:randomBytes(16).toString('hex')};
 r=await post(command);assert.equal(r.status,200);const appointment=(await r.json()).appointment;assert.equal(appointment.status,'confirmed');
 r=await request('/admin/api/appointments',{headers:{Accept:'application/json'}});assert.equal((await r.json()).appointments[0].id,appointment.id);r=await post(command);assert.equal((await r.json()).appointment.id,appointment.id);checks.push('owner_confirmation_persists_across_requests_and_replay');
 r=await publicPost(randomBytes(16).toString('hex'));const second=await r.json();r=await post({...command,request_id:second.reference,idempotency_key:randomBytes(16).toString('hex')});assert.equal(r.status,409);checks.push('hosted_mysql_conflict_rejected');
 r=await post({action:'cancel',appointment_id:appointment.id,expected_version:99,idempotency_key:randomBytes(16).toString('hex')});assert.equal(r.status,409);checks.push('hosted_stale_edit_rejected');
 booking(false);
 r=await request('/admin/logout',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({_token:csrf})});assert.equal(r.status,302);
 r=await request('/admin/api/requests',{headers:{Accept:'application/json'}});assert.equal(r.status,401);checks.push('logout_revokes_access');
 console.log(JSON.stringify({status:'passed',checks,agency_requests:0,customer_messages:0}));
} finally {booking(false);console.log(JSON.stringify({cleanup:owner('delete')}));}
