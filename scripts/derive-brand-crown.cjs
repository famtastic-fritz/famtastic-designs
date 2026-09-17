// Deterministic source-pixel extraction. No AI, tracing or invented crown paths.
const fs=require('node:fs');const path=require('node:path');const crypto=require('node:crypto');
const {chromium}=require('../frontend/node_modules/@playwright/test');
const source='frontend/public/brand/famtastic-designs-logo-v1.png';
const sourceBytes=fs.readFileSync(source);const sha=crypto.createHash('sha256').update(sourceBytes).digest('hex');
if(sha!=='ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950')throw Error('Unapproved master');
(async()=>{const browser=await chromium.launch();try{const page=await browser.newPage();const result=await page.evaluate(async data=>{
 const source=new Image();source.src=data;await source.decode();
 const canvas=document.createElement('canvas');canvas.width=source.width;canvas.height=source.height;const ctx=canvas.getContext('2d');ctx.drawImage(source,0,0);
 const pixels=ctx.getImageData(0,0,canvas.width,canvas.height);let left=2172,top=724,right=0,bottom=0;
 // Crown-only region, above the signature; select green chroma, not white text.
 for(let y=0;y<300;y++)for(let x=1700;x<2172;x++){const i=(y*2172+x)*4;const [r,g,b,a]=pixels.data.slice(i,i+4);if(a>0&&g>140&&g-r>60&&g-b>60){left=Math.min(left,x);right=Math.max(right,x);top=Math.min(top,y);bottom=Math.max(bottom,y);}}
 const w=right-left+1,h=bottom-top+1;const crown=document.createElement('canvas');crown.width=w;crown.height=h;const cc=crown.getContext('2d');const out=cc.createImageData(w,h);
 for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=((y+top)*2172+x+left)*4,j=(y*w+x)*4;const [r,g,b,a]=pixels.data.slice(i,i+4);const strength=Math.max(0,Math.min(1,(Math.min(g-r,g-b)-35)/70));out.data.set([r,g,b,Math.round(a*strength)],j);}
 cc.putImageData(out,0,0);
 const flat=document.createElement('canvas');flat.width=w;flat.height=h;const fc=flat.getContext('2d');const mask=cc.getImageData(0,0,w,h);
 // Preserve the partially transparent source stroke; remove disconnected glow
 // specks by retaining substantial connected source-pixel components only.
 const seen=new Uint8Array(w*h);const keep=new Uint8Array(w*h);
 for(let seed=0;seed<w*h;seed++){if(seen[seed]||mask.data[seed*4+3]<64)continue;const component=[seed];seen[seed]=1;
  for(let n=0;n<component.length;n++){const p=component[n],x=p%w,y=Math.floor(p/w);for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++){const xx=x+dx,yy=y+dy,q=yy*w+xx;if(xx>=0&&xx<w&&yy>=0&&yy<h&&!seen[q]&&mask.data[q*4+3]>=64){seen[q]=1;component.push(q);}}}
  if(component.length>=100)for(const p of component)keep[p]=1;
 }
 for(let i=0;i<mask.data.length;i+=4){mask.data[i]=124;mask.data[i+1]=252;mask.data[i+2]=0;mask.data[i+3]=keep[i/4]?255:0;}fc.putImageData(mask,0,0);
 const icons={};for(const size of [16,32,48,180,192,512]){const c=document.createElement('canvas');c.width=c.height=size;const g=c.getContext('2d');g.fillStyle='#070907';g.fillRect(0,0,size,size);const scale=size*.82/Math.max(w,h);g.imageSmoothingEnabled=true;g.imageSmoothingQuality='high';const x=(size-w*scale)/2,y=(size-h*scale)/2;
 // Optical weight compensation, same source silhouette, no new paths.
 if(size<=32){const weight=size===16?.38:.2;for(const [dx,dy] of [[-weight,0],[weight,0],[0,-weight],[0,weight]])g.drawImage(flat,x+dx,y+dy,w*scale,h*scale);}
 if(size>=180){g.shadowColor='rgba(124,252,0,.16)';g.shadowBlur=size*.025;}
 g.drawImage(flat,x,y,w*scale,h*scale);icons[size]=c.toDataURL('image/png').split(',')[1];}
 return {bounds:{left,top,width:w,height:h},master:crown.toDataURL('image/png').split(',')[1],flat:flat.toDataURL('image/png').split(',')[1],icons};
 },'data:image/png;base64,'+sourceBytes.toString('base64'));
 const pub='frontend/public';const write=(f,bytes)=>{fs.mkdirSync(path.dirname(f),{recursive:true});fs.writeFileSync(f,bytes);};
 write(pub+'/brand/famtastic-crown-master.png',Buffer.from(result.master,'base64'));
 write(pub+'/brand/famtastic-crown-flat.png',Buffer.from(result.flat,'base64'));
 const names={16:'favicon-16x16.png',32:'favicon-32x32.png',48:'favicon-48x48.png',180:'apple-touch-icon.png',192:'android-chrome-192x192.png',512:'android-chrome-512x512.png'};
 for(const [size,name] of Object.entries(names))write(pub+'/'+name,Buffer.from(result.icons[size],'base64'));
 // PNG-backed ICO directory: supported modern browsers, three real resolutions.
 const sizes=[16,32,48];const entries=sizes.map(s=>Buffer.from(result.icons[s],'base64'));const header=Buffer.alloc(6+16*sizes.length);header.writeUInt16LE(1,2);header.writeUInt16LE(sizes.length,4);let offset=header.length;
 sizes.forEach((s,n)=>{const i=6+n*16;header[i]=header[i+1]=s;header.writeUInt16LE(1,i+4);header.writeUInt16LE(32,i+6);header.writeUInt32LE(entries[n].length,i+8);header.writeUInt32LE(offset,i+12);offset+=entries[n].length;});write(pub+'/favicon.ico',Buffer.concat([header,...entries]));
 // Honest SVG container for raster-derived geometry, NOT a claimed vector master.
 write(pub+'/favicon.svg',`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><title>FAMtastic crown</title><image width="32" height="32" href="data:image/png;base64,${result.icons[32]}"/></svg>\n`);
 const theme='backend/web/themes/custom/famtastic_admin/brand';for(const name of [...Object.values(names),'favicon.ico','favicon.svg'])write(theme+'/'+name,fs.readFileSync(pub+'/'+name));
 write(theme+'/famtastic-crown-master.png',Buffer.from(result.master,'base64'));
 const receipt={source,sha256:sha,method:'deterministic green-chroma source-pixel extraction within crown ROI; no AI or vector trace',bounds:result.bounds,smallIcons:'same extracted alpha silhouette, threshold 64; retain 8-connected source-pixel components >=100 pixels to discard detached glow; flat #7cfc00 on #070907; optical 4-direction expansion .38px at16 and .2px at32; no glow',svg:'embedded PNG, not reviewed vector artwork',files:Object.values(names).map(name=>({path:pub+'/'+name,bytes:fs.statSync(pub+'/'+name).size,sha256:crypto.createHash('sha256').update(fs.readFileSync(pub+'/'+name)).digest('hex')}))};write('docs/design/crown-derivation.json',JSON.stringify(receipt,null,2)+'\n');console.log(JSON.stringify(receipt,null,2));
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
