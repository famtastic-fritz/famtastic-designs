// Deterministic identity derivatives, no generative logo redraw or remote upload.
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {chromium}=require('../frontend/node_modules/@playwright/test');
const source='frontend/public/brand/famtastic-designs-logo-v1.png';
const crownSource='frontend/public/brand/famtastic-crown-flat.png';
const output=path.resolve(process.argv[2]||'.local-email-preview/social-profile-kit');
const logo=fs.readFileSync(source),crown=fs.readFileSync(crownSource);
const sha=crypto.createHash('sha256').update(logo).digest('hex');
if(sha!=='ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950')throw Error('Unexpected logo master');
const platforms={facebook:1080,instagram:1080,threads:1080,linkedin:400,x:400,youtube:800,tiktok:1080,pinterest:1000,'whatsapp-business':640};
(async()=>{const b=await chromium.launch();try{const p=await b.newPage();const result=await p.evaluate(async({logo,crown,platforms})=>{
 const load=async src=>{let i=new Image();i.src=src;await i.decode();return i;};
 const original=await load(logo),crownImage=await load(crown);
 const fam=document.createElement('canvas');fam.width=1250;fam.height=700;const f=fam.getContext('2d');f.drawImage(original,0,0);
 const pixels=f.getImageData(0,0,fam.width,fam.height);
 // Isolate the existing coloured F/A/M pixels. The right/lower regions also
 // contain white tastic/DESIGNS/tagline pixels, which are omitted, not repainted.
 for(let y=0;y<fam.height;y++)for(let x=0;x<fam.width;x++){
  const n=(y*fam.width+x)*4;const r=pixels.data[n],g=pixels.data[n+1],blue=pixels.data[n+2];
  if(y>645 || (x>790&&y>490) || x>1135){
   const chroma=Math.max(r,g,blue)-Math.min(r,g,blue);
   const isBrandColor=((r>g*1.28&&r>blue*1.28)||(g>blue*1.45&&r>blue*1.45)||(blue>r*1.45)) && !(g>r*1.1&&g>blue*1.1);
   pixels.data[n+3]=isBrandColor?Math.round(pixels.data[n+3]*Math.min(1,Math.max(0,(chroma-15)/45))):0;
  }
 }
 f.putImageData(pixels,0,0);
 // Layout at master coordinates. Full lockup is never cropped or re-lettered.
 const render=(variant,size)=>{const c=document.createElement('canvas');c.width=c.height=size;const g=c.getContext('2d');g.fillStyle='#070907';g.fillRect(0,0,size,size);g.imageSmoothingEnabled=true;g.imageSmoothingQuality='high';
  g.save();g.scale(size/1080,size/1080);
  // Optical fit measured against the circular crop, not only the square edges.
  g.translate(540,540);g.scale(variant==='full-logo'?1.06:1.22,variant==='full-logo'?1.06:1.22);g.translate(-540,-540);
  if(variant==='full-logo'){g.drawImage(original,60,380,960,320);}
  else {g.drawImage(fam,136,365,810,453.6);g.drawImage(crownImage,638,246,180,182.15);}
  g.restore();return c.toDataURL('image/png').split(',')[1];
 };
 const images={};for(const variant of ['full-logo','fam-crown']){
  images[`masters/${variant}-1080.png`]=render(variant,1080);
  images[`masters/${variant}-2048.png`]=render(variant,2048);
  for(const [platform,size] of Object.entries(platforms))images[`${variant}/${platform}-${size}.png`]=render(variant,size);
 }
 return {images,fam:fam.toDataURL('image/png').split(',')[1]};
 },{logo:'data:image/png;base64,'+logo.toString('base64'),crown:'data:image/png;base64,'+crown.toString('base64'),platforms});
 fs.mkdirSync(output,{recursive:true});for(const [name,data] of Object.entries(result.images)){fs.mkdirSync(path.dirname(path.join(output,name)),{recursive:true});fs.writeFileSync(path.join(output,name),Buffer.from(data,'base64'));}
 fs.mkdirSync(path.join(output,'source-derivatives'),{recursive:true});fs.writeFileSync(path.join(output,'source-derivatives/fam-extracted.png'),Buffer.from(result.fam,'base64'));
 const receipt={source,sourceSha256:sha,crownSource,method:'source-pixel crop/chroma mask for compact FAM plus existing source-derived crown; no AI, relettering or recoloring; complete unmodified full lockup in full-logo variant',outputDimensions:platforms,platformSizes:'Prepared output sizes, not claims of required platform dimensions. Crop UI varies; upload without zoom.',files:Object.keys(result.images).map(name=>({name,bytes:fs.statSync(path.join(output,name)).size,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(output,name))).digest('hex')}))};
 fs.writeFileSync(path.join(output,'provenance.json'),JSON.stringify(receipt,null,2)+'\n');
 fs.copyFileSync('scripts/social-profile-kit.html',path.join(output,'index.html'));
 fs.copyFileSync('docs/brand/SOCIAL-PROFILE-KIT.md',path.join(output,'README.md'));
 console.log(JSON.stringify({output,files:receipt.files.length,maxBytes:Math.max(...receipt.files.map(x=>x.bytes))}));
 }finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1});
