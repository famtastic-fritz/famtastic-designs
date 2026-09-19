const tl=gsap.timeline({paused:true});
const duration=30.041666666667;
const show=(selector,at,end,from={},to={})=>{
  tl.fromTo(selector,{opacity:0,...from},{opacity:1,...to,duration:.65,ease:'power3.out',immediateRender:false},at);
  if(end<duration)tl.to(selector,{opacity:0,duration:.28,ease:'power2.in'},end-.28);
};
gsap.set(['#opening','#home-intro','#benefits-heading','#site','#offer','#offer-heading','#daily','#renewal','#opportunity','#closing','.fragment','.caption'],{opacity:0});
gsap.set('#performer-wrap',{scale:.88,y:240,x:0,transformOrigin:'50% 100%'});
tl.to('#performer-wrap',{scale:.91,y:110,x:0,duration:3.8,ease:'power2.inOut'},2.2);
tl.to('#performer-wrap',{scale:.91,y:15,x:-110,duration:.8,ease:'power3.inOut'},6.25);
tl.to('#performer-wrap',{scale:.85,y:20,x:-105,duration:.75,ease:'power3.inOut'},12.7);
tl.to('#performer-wrap',{scale:.82,y:15,x:-160,duration:1.1,ease:'power2.inOut'},23.3);
tl.to('#performer-wrap',{scale:.90,y:80,x:0,duration:1.7,ease:'power2.inOut'},25.55);
tl.fromTo('.city',{x:-8,y:16},{x:18,y:-12,duration,ease:'none'},0);
tl.fromTo('.architecture',{scale:.95,y:30},{scale:1.04,y:145,duration,ease:'none'},0);
show('#opening',.12,3.8,{y:42},{y:0});
show('#home-intro',3.82,7.05,{x:-42},{x:0});
show('.fragment-a',.7,6.7,{x:-150,y:70,rotation:-9,rotationY:16},{x:0,y:0,rotation:-5,rotationY:8});
show('.fragment-b',1.15,6.8,{x:170,y:40,rotation:10,rotationY:-18},{x:0,y:0,rotation:6,rotationY:-8});
show('.fragment-c',1.65,6.95,{x:-150,y:90,rotation:-5},{x:0,y:0,rotation:-3});
for(const [i,p] of [...document.querySelectorAll('.house-light path')].entries()){
 const n=p.getTotalLength();gsap.set(p,{strokeDasharray:n,strokeDashoffset:n});
 tl.to(p,{strokeDashoffset:0,duration:1.75,ease:'power2.out'},4.05+i*.34);
}
show('#benefits-heading',7.1,12.9,{y:35},{y:0});
show('#site',7.0,13.3,{x:150,scale:.88,rotationY:-18,rotation:4},{x:0,scale:1,rotationY:-8,rotation:2});
tl.fromTo('#site-work',{backgroundColor:'#eeeee4'},{backgroundColor:'#dbe9ca',duration:.2,repeat:1,yoyo:true,repeatDelay:1.0},7.45);
tl.fromTo('#site-services',{backgroundColor:'#eeeee4'},{backgroundColor:'#dbe9ca',duration:.2,repeat:1,yoyo:true,repeatDelay:1.0},8.95);
tl.fromTo('#site-contact',{backgroundColor:'#eeeee4'},{backgroundColor:'#dbe9ca',duration:.2,repeat:1,yoyo:true,repeatDelay:1.0},10.1);
const benefit=document.getElementById('benefit-title');
const benefitWords=[{at:0,text:'Show your work.'},{at:8.4,text:'Explain your services.'},{at:10.1,text:'Collect inquiries.'}];
const clock={value:0};
tl.to(clock,{value:duration,duration,ease:'none',onUpdate:()=>{const t=clock.value;let text=benefitWords[0].text;for(const b of benefitWords)if(t>=b.at)text=b.text;benefit.textContent=text;}},0);
show('#offer-heading',13.2,23.65,{y:30},{y:0});
show('#offer',13.2,duration,{x:80,rotation:3},{x:0,rotation:0});
tl.set('#offer',{opacity:0},25.25);
tl.fromTo('.offer-divider',{scaleX:0},{scaleX:1,duration:.55,ease:'power2.out',immediateRender:false},14.0);
tl.fromTo('#daily',{opacity:0,y:20},{opacity:1,y:0,duration:.5,ease:'power3.out',immediateRender:false},17.75);
tl.fromTo('#renewal',{opacity:0,y:16},{opacity:1,y:0,duration:.55,ease:'power3.out',immediateRender:false},19.9);
show('#opportunity',23.55,26.2,{x:45},{x:0});
show('#closing',26.2,duration,{y:35},{y:0});
for(const node of document.querySelectorAll('.caption')){
 const start=Number(node.dataset.cueStart),end=Number(node.dataset.cueEnd);
 tl.set(node,{opacity:1},start);tl.set(node,{opacity:0},end);
}
window.__timelines=window.__timelines||{};window.__timelines['business-home']=tl;
