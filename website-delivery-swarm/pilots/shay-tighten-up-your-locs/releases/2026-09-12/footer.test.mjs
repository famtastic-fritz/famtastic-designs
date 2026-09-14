import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const html = readFileSync(new URL('./index.html', import.meta.url), 'utf8');
test('public footer includes customer copyright and linked agency credit', () => {
  const footer = html.match(/<footer[\s\S]*?<\/footer>/)[0];
  assert.match(footer, /© 2026 Tighten Up Your Locs\. All rights reserved\./);
  assert.match(footer, /href="https:\/\/famtasticdesigns\.com\/">Website by FAMtastic Designs<\/a>/);
  assert.match(footer, /href="\/privacy\/">Privacy Policy<\/a>/);
  assert.match(footer, /href="\/terms\/">Website Terms<\/a>/);
});
test('homepage contains policy links, not a privacy explainer or analytics panel', () => {
  assert.doesNotMatch(html, /off until you choose Allow/);
  assert.doesNotMatch(html, /id="analytics-(?:status|allow|decline)"/);
  assert.doesNotMatch(html, /<h2>Your privacy<\/h2>|<section id="privacy"/);
  assert.match(html, /href="#booking">Booking/);
  assert.match(html, /id="booking"/);
  assert.match(html, /id="request"/);
  assert.match(html, /Request your appointment/);
});
for (const [page,title] of [['privacy','Privacy Policy'],['terms','Website Terms']]) {
  test(page+' is a branded standalone policy with home and booking navigation',()=>{
    const policy=readFileSync(new URL('./'+page+'/index.html',import.meta.url),'utf8');
    assert.ok(policy.includes('<h1>'+title+'</h1>'));
    assert.match(policy,/href="\/#booking">Booking/);
    assert.match(policy,/Website by FAMtastic Designs/);
    assert.match(policy,/hello@tightenupyourlocs\.com/);
    assert.doesNotMatch(policy,/src=".*site\.js|analytics-allow/);
  });
}
