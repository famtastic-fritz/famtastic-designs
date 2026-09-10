import assert from "node:assert/strict";
import {readFile,stat} from "node:fs/promises";
import {fileURLToPath} from "node:url";
import path from "node:path";
const here=path.dirname(fileURLToPath(import.meta.url));
const root=path.resolve(here,"../public/showcase/booked-and-branded-pilot/noise-cuts-proof");
const [html,css,js,owner,concept02,concept02Css,concept03,concept03Css]=await Promise.all([
  readFile(path.join(root,"index.html"),"utf8"),readFile(path.join(root,"noise.css"),"utf8"),readFile(path.join(root,"noise.js"),"utf8"),readFile(path.join(root,"owner/index.html"),"utf8"),
  readFile(path.join(root,"concept-02/index.html"),"utf8"),readFile(path.join(root,"concept-02/concept.css"),"utf8"),
  readFile(path.join(root,"concept-03/index.html"),"utf8"),readFile(path.join(root,"concept-03/concept.css"),"utf8")
]);
assert.equal((html.match(/<section/g)||[]).length,4);
assert.match(html,/lang="es"/);assert.match(html,/TU CORTE/);assert.match(html,/@noise\.cuts/);assert.match(html,/1542 SE Floresta Dr/);
assert.match(html,/id="booking-form"/);assert.match(html,/name="consent"/);assert.match(js,/\/web\/api\/booking-request\/noise-cuts/);assert.match(js,/fetch\(API/);
assert.match(owner,/NOISE CONTROL/);assert.match(owner,/Vista de demostración/);assert.match(owner,/booking-request\/noise-cuts\/owner/);
assert.match(css,/@media\(max-width:760px\)/);assert.ok((await stat(path.join(root,"assets/concept-a.png"))).size>1000000);
for(const [concept,number,headline] of [[concept02,"02",/BUEN/],[concept03,"03",/DETALLE/]]){
  assert.match(concept,new RegExp(`Concepto ${number}`));assert.match(concept,/lang="es"/);assert.match(concept,headline);assert.match(concept,/1542 SE Floresta Dr/);
  assert.match(concept,/id="booking-form"/);assert.match(concept,/name="consent"/);assert.match(concept,/\.\.\/noise\.js/);assert.match(concept,/\.\.\/owner\//);
  assert.doesNotMatch(concept,/\$[0-9]/);assert.doesNotMatch(concept,/stripe|twilio|smtp/i);
}
assert.match(concept02Css,/@media\(max-width:760px\)/);assert.match(concept03Css,/@media\(max-width:760px\)/);
assert.ok((await stat(path.join(root,"assets/concept-b.png"))).size>1000000);assert.ok((await stat(path.join(root,"assets/concept-c.png"))).size>1000000);
assert.doesNotMatch(html,/price|\$[0-9]/i);assert.doesNotMatch(js,/stripe|twilio|smtp/i);
console.log("PASS Noise Cuts proof set: three distinct Spanish concepts, responsive owner portal, booking request API, shared location, no price or payment claims.");
