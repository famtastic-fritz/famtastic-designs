const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require('../frontend/node_modules/@playwright/test');
const dir='.local-email-preview/social-profile-kit';
(async()=>{const b=await chromium.launch();try{const p=await b.newPage({viewport:{width:1200,height:1050}});
 await p.goto('http://127.0.0.1:8765/social-profile-kit/');
 await p.evaluate(()=>Promise.all([...document.images].map(i=>i.decode())));
 await p.screenshot({path:dir+'/preview.png',fullPage:true});
 await p.locator('.grid').screenshot({path:dir+'/comparison.png'});
 const receipt=JSON.parse(fs.readFileSync(dir+'/provenance.json'));
 assert.equal(receipt.files.length,22);for(const file of receipt.files){const buf=fs.readFileSync(dir+'/'+file.name);const size=Number(file.name.match(/-(\d+)\.png$/)[1]);assert.equal(buf.readUInt32BE(16),size);assert.equal(buf.readUInt32BE(20),size);assert.ok(buf.length<(file.name.startsWith('masters/')?4_000_000:2_000_000));}
 const crops=await p.evaluate(async()=>{const results=[];for(const variant of ['full-logo','fam-crown']){const i=new Image();i.src=`masters/${variant}-1080.png`;await i.decode();const c=document.createElement('canvas');c.width=c.height=1080;const g=c.getContext('2d');g.drawImage(i,0,0);const data=g.getImageData(0,0,1080,1080).data;let outside=0,transparent=0,maxRadius=0;for(let y=0;y<1080;y++)for(let x=0;x<1080;x++){const n=(y*1080+x)*4;transparent+=data[n+3]<255?1:0;if(Math.max(data[n],data[n+1],data[n+2])>60){const radius=Math.hypot(x-540,y-540);maxRadius=Math.max(maxRadius,radius);if(radius>529)outside++;}}results.push({variant,outside,transparent,maxRadius});}return results;});
 for(const row of crops){assert.equal(row.outside,0);assert.equal(row.transparent,0);}
 for(const a of await p.locator('a[download]').all()){const href=await a.getAttribute('href');assert.equal((await p.request.get('http://127.0.0.1:8765/social-profile-kit/'+href)).status(),200);}
 await p.setViewportSize({width:390,height:1000});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),390);await p.screenshot({path:dir+'/preview-mobile.png',fullPage:true});
 fs.writeFileSync(dir+'/qa.json',JSON.stringify({files:22,platformFilesUnder2MB:true,mastersUnder4MB:true,dimensions:'pass',opaque:'pass',circleSafe:'all pixels brighter than60 within98-percent-diameter circle',crops,mobileOverflow:false,publishing:'not performed'},null,2));
 console.log(JSON.stringify({status:'PASS',files:22,crops,mobileOverflow:false}));
 }finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1});
