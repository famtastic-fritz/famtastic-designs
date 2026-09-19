const tl=gsap.timeline({paused:true});
const duration=35.583333333333336;
const show=(selector,at,end,from={y:22},to={y:0})=>{
  tl.fromTo(selector,{opacity:0,...from},{opacity:1,...to,duration:.52,ease:'power3.out',immediateRender:false},at);
  if(end<duration){tl.to(selector,{opacity:0,duration:.24,ease:'power2.in'},end-.24);tl.set(selector,{opacity:0},end);}
};
gsap.set(['#title-opening','#title-vision','#title-home','#title-grow','.fragment','#website-panel','#plan-panel','#voiceover-marker','.caption'],{opacity:0});
tl.fromTo('#city',{x:-13,y:13},{x:16,y:-10,duration,ease:'none'},0);
tl.fromTo('.ground-grid',{y:0},{y:-56,duration,ease:'none'},0);
for(const [i,path] of [...document.querySelectorAll('.home-line path,.door')].entries()){
  const length=path.getTotalLength();gsap.set(path,{strokeDasharray:length,strokeDashoffset:length});
  tl.to(path,{strokeDashoffset:0,duration:1.2,ease:'power2.out'},5.95+i*.31);
}
show('#title-opening',.16,5.78,{y:32},{y:0});
show('.fragment-vision',6.1,9.1,{x:-60,y:25},{x:0,y:0});
show('#title-vision',6.05,10.3,{y:23},{y:0});
show('.fragment-plan',9.0,12.7,{x:60,y:14},{x:0,y:0});
show('#website-panel',11.95,16.0,{x:58,rotation:1.2},{x:0,rotation:0});
show('.fragment-tools',12.35,16.0,{x:-48,y:12},{x:0,y:0});
show('#website-panel',16.136,21.20,{x:42,rotation:1.1},{x:0,rotation:0});
show('#plan-panel',21.25,27.55,{x:54,rotation:1.2},{x:0,rotation:0});
show('#title-home',26.95,28.10,{y:26},{y:0});
show('#title-grow',28.2,31.32,{y:20},{y:0});
show('#voiceover-marker',5.92,16.136,{y:-10},{y:0});
show('#voiceover-marker',21.256,31.344,{y:-10},{y:0});
show('#title-home',31.344,duration,{y:18},{y:0});
for(const node of document.querySelectorAll('.caption')){
  const start=Number(node.dataset.cueStart),end=Number(node.dataset.cueEnd);
  tl.set(node,{opacity:1},start);tl.set(node,{opacity:0},end);
}
window.__timelines=window.__timelines||{};
window.__timelines['walking-continuation']=tl;
