const tl=gsap.timeline({paused:true});
SCENES.forEach((s,index)=>{
 const root=document.getElementById(s.id),inner=root.querySelector('.scene-content');
 const content=root.querySelector('.inside');
 tl.fromTo(content,{opacity:0,y:20},{opacity:1,y:0,duration:.42,ease:'power3.out'},s.start);
 if(index<SCENES.length-1)tl.to(content,{opacity:0,y:-8,duration:.2,ease:'power2.in'},s.end-.2);
 root.querySelectorAll('[data-pop]').forEach((el,i)=>tl.fromTo(el,{opacity:0,y:26},{opacity:1,y:0,duration:.72,ease:'power3.out'},s.start+.05+i*.10));
 root.querySelectorAll('[data-line]').forEach(el=>{const when=CUES[Number(el.dataset.line)].start;tl.fromTo(el,{opacity:0,y:26},{opacity:1,y:0,duration:.64,ease:'power3.out'},when)});
 root.querySelectorAll('[data-fraction]').forEach(el=>{const cue=CUES[s.first_line],when=cue.start+Number(el.dataset.fraction)*(cue.end-cue.start);tl.fromTo(el,{opacity:0,y:25},{opacity:1,y:0,duration:.62,ease:'power3.out'},when)});
});
const at=(n,f=0)=>SCENES[n].start+SCENES[n].duration*f;
tl.fromTo('#scene-00 .ticket',{rotation:5},{rotation:-3,duration:1.1,ease:'power3.out'},at(0,.03));
tl.fromTo('#scene-02 .foundation-rule',{scaleX:0},{scaleX:1,duration:SCENES[2].duration*.7,ease:'power2.out'},at(2,.12));
tl.fromTo('#scene-04 .build-block',{opacity:0,y:70},{opacity:1,y:0,duration:.85,stagger:SCENES[4].duration*.17,ease:'power3.out'},at(4,.06));
tl.fromTo('#scene-06 .confidence-line',{scaleX:0},{scaleX:1,duration:.85,ease:'power3.out'},CUES[13].start+.5);
tl.fromTo('#scene-07 .value-step',{opacity:0,y:55},{opacity:1,y:0,duration:.75,stagger:SCENES[7].duration*.075,ease:'power3.out'},at(7,.08));
tl.fromTo('#scene-07 .value-path',{opacity:0},{opacity:1,duration:1.2,ease:'power2.out'},at(7,.48));
tl.fromTo('#scene-07 .value-point',{opacity:0,scale:0},{opacity:1,scale:1,duration:.5,ease:'back.out(1.1)',transformOrigin:'center'},at(7,.64));
tl.fromTo('#scene-08 .growth-level',{opacity:0,y:40},{opacity:1,y:0,duration:.58,stagger:SCENES[8].duration*.055,ease:'power3.out'},at(8,.09));
tl.fromTo('#scene-09 .build-block',{opacity:0,y:50},{opacity:1,y:0,duration:.7,stagger:SCENES[9].duration*.15,ease:'power3.out'},at(9,.09));
tl.fromTo('#scene-10 .you',{x:-35},{x:0,duration:.9,ease:'power3.out'},CUES[18].start);
tl.fromTo('#scene-10 .we',{x:35},{x:0,duration:.9,ease:'power3.out'},CUES[18].start+.35);
document.querySelectorAll('.caption').forEach(el=>{
 const start=Number(el.dataset.cueStart),end=Number(el.dataset.cueEnd);
 tl.set(el,{opacity:0},0);tl.set(el,{opacity:1},start);tl.set(el,{opacity:0},end);
});
window.__timelines=window.__timelines||{};
window.__timelines['no-catch']=tl;
