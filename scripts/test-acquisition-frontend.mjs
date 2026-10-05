import test from 'node:test';
import assert from 'node:assert/strict';
import {validSampleToken,privatePreviewPath,continuationReturn} from '../frontend/src/api/acquisition.js';
import {safeLocation,safeEventParams} from '../frontend/src/lib/googleAnalytics.js';
const token='ab'.repeat(32);
globalThis.window={location:{href:'https://famtasticdesigns.com/'}};
test('sample tokens and preview URLs reject traversal, external hosts and other recipients',()=>{
 assert.equal(validSampleToken(token),true);
 for(const bad of ['',token+'a','AB'.repeat(32),'../'+token])assert.equal(validSampleToken(bad),false);
 const safe=`/web/api/acquisition/samples/${token}/preview/beauty_editorial`;
 assert.equal(privatePreviewPath(safe,token),safe);
 for(const bad of [safe+'?email=private',safe+'/../x',safe.replace(token,'cd'.repeat(32)),`https://external.test${safe}`])assert.equal(privatePreviewPath(bad,token),null);
});
test('only exact server continuation returns to interview',()=>{
 const c={kind:'acquisition_sample',return_path:'/portal?start=website&section=projects'};
 assert.equal(continuationReturn(c),c.return_path);
 for(const bad of [null,{...c,return_path:'https://evil.test'},{...c,kind:'proof'}])assert.equal(continuationReturn(bad),null);
});
test('sample credential and private business identity never enter analytics locations/events',()=>{
 const safe=safeLocation(`/samples/${token}?email=private@example.test&business=Private&sample_continuation=${token}`);
 assert.equal(safe.path,'/samples/private');
 const params=safeEventParams({page_path:`/samples/${token}`,link_url:`https://famtasticdesigns.com/samples/${token}`,sample_continuation:token,description:`/samples/${token}`,page_location:`/login?sample_continuation=${token}&email=private@example.test`});
 assert.equal(params.link_url,'https://famtasticdesigns.com/samples/private');
 assert.equal('description' in params,false);
 assert.ok(!JSON.stringify(params).includes(token));assert.ok(!JSON.stringify(params).includes('private@example.test'));
});
