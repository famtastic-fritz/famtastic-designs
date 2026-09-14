import test from "node:test";
import assert from "node:assert/strict";
import { canonicalEndpoint, settings, isDurableReceipt, safeAnalyticsParameters, publicWindows, SITE_KEY } from "./site.js";
test("only exact bound-site canonical HTTPS endpoints are accepted", () => {
  const path = "/web/api/booking-request/" + SITE_KEY;
  assert.equal(canonicalEndpoint("https://famtasticdesigns.com" + path, "request", "https://tightenupyourlocs.com"), "https://famtasticdesigns.com" + path);
  for (const value of ["https://evil.test"+path,"https://famtasticdesigns.com"+path+"?email=secret","https://famtasticdesigns.com"+path+"#secret","https://famtasticdesigns.com/api/booking-request/tighten-up-your-locs","http://famtasticdesigns.com"+path]) assert.equal(canonicalEndpoint(value,"request","https://tightenupyourlocs.com"), "");
});
test("explicit enable required and invalid analytics rejected", () => {
 assert.equal(settings({requestEndpoint:"/api/booking-request/"+SITE_KEY}, "https://tightenupyourlocs.com").request, "");
 assert.equal(settings({gaMeasurementId:"G-123456<script>"}, "https://tightenupyourlocs.com").measurement, "");
 assert.equal(settings({gaMeasurementId:"G-V8M437DWV0"}, "http://127.0.0.1:8767").measurement, "");
 assert.equal(settings({gaMeasurementId:"G-V8M437DWV0"}, "https://www.tightenupyourlocs.com").measurement, "G-V8M437DWV0");
});
test("only real backend reference response counts as saved", () => {
 assert.equal(isDurableReceipt({ok:true,status:"received"}), false);
 assert.equal(isDurableReceipt({ok:true,status:"received",reference:"123e4567-e89b-12d3-a456-426614174000"}), true);
 assert.equal(isDurableReceipt({ok:false,status:"received",reference:"123e4567-e89b-12d3-a456-426614174000"}), false);
});
test("analytics location drops query/path/hash and sends no auto pageview", () => {
 const p = safeAnalyticsParameters("https://tightenupyourlocs.com/private?email=secret#token");
 assert.equal(p.page_location, "https://tightenupyourlocs.com/");
 assert.equal(p.page_referrer, ""); assert.equal(p.send_page_view, false);
});
test("unavailable, wrong site, malformed and zero windows remain distinct", () => {
 assert.throws(()=>publicWindows({ok:false,windows:[]}));
 assert.throws(()=>publicWindows({ok:true,site_key:"another-site",windows:[]}));
 assert.throws(()=>publicWindows({ok:true,site_key:SITE_KEY,windows:[{starts_at:"bad"}]}));
 assert.deepEqual(publicWindows({ok:true,site_key:SITE_KEY,windows:[]}),[]);
 const current={label:"Open",starts_at:1,ends_at:3};
 assert.deepEqual(publicWindows({ok:true,site_key:SITE_KEY,windows:[current]},2000),[current]);
});
