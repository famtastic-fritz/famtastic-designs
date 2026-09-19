import React, {useState, useEffect} from 'react';
import {createRoot} from 'react-dom/client';
import {BrowserRouter} from 'react-router';
import SiteFooter from '../src/components/v1/SiteFooter.jsx';
import SocialBadge from '../src/components/v1/SocialBadge.jsx';
import {SOCIAL_PROFILES} from '../src/lib/socialProfiles.js';
import badgeCss from '../src/components/v1/social-footer.css?raw';
import '../src/index.css';
import '../src/brand-dna.css';
const services=[['websites','Websites'],['ai-systems','AI Systems'],['automation','Automation'],['branding','Branding & Design']].map(([slug,title])=>({slug,title}));
const packages=[['199-quick-start','Web Basics'],['business','Business Website'],['ecommerce','E-Commerce']].map(([slug,title])=>({slug,title}));
const stateCss=(badgeCss.match(/\.fam-social-badge(?::focus-visible|\[href\]:hover|\[href\]:active)[^{]*\{[^}]*\}/g)||[]).join('\n');
const gallery=new URLSearchParams(location.search).has('gallery');
function Review(){
 const [reduce,setReduce]=useState(false),[zoom,setZoom]=useState(false),[empty,setEmpty]=useState(false),[mode,setMode]=useState('record'),[events,setEvents]=useState([]);
 useEffect(()=>{
  const observer=e=>setEvents(rows=>[...rows,`${e.type}: ${e.detail.platform}`]);
  window.addEventListener('famtastic:social-click',observer);
  if(mode==='missing')delete window.gtag;
  else window.gtag=(event,name,fields)=>{setEvents(rows=>[...rows,`${name}: ${fields.social_platform}`]);if(mode==='throw')throw Error('Local QA analytics failure');};
  return()=>{window.removeEventListener('famtastic:social-click',observer);delete window.gtag;};
 },[mode]);
 return <div className="fam-owned" style={zoom?{zoom:2}:undefined}>
  <p style={{padding:'12px 24px',margin:0,color:'#bbc2b6'}}>Local review · actual SiteFooter · sample CMS link data</p>
  {gallery&&<aside style={{padding:24,display:'flex',flexWrap:'wrap',gap:16}}>
   <label><input type="checkbox" checked={reduce} onChange={e=>setReduce(e.target.checked)}/> Apply reduced-motion rules (local simulation)</label>
   <label><input type="checkbox" checked={zoom} onChange={e=>setZoom(e.target.checked)}/> 200% CSS zoom</label>
   <label><input type="checkbox" checked={empty} onChange={e=>setEmpty(e.target.checked)}/> Empty CMS data</label>
   <label>Analytics <select value={mode} onChange={e=>setMode(e.target.value)}><option value="record">Record locally</option><option value="missing">Missing</option><option value="throw">Throw</option></select></label>
  </aside>}
  {reduce&&<style>{badgeCss.slice(badgeCss.lastIndexOf('@media (prefers-reduced-motion: reduce)')).replace('@media (prefers-reduced-motion: reduce)','@media all')}</style>}
  <SiteFooter services={empty?[]:services} packages={empty?[]:packages}/>
  {gallery&&<section style={{padding:24}}><h1>Badge review · preview only</h1><p>All ten supported symbols. Preview badges do not navigate. Use the integrated footer above for real keyboard and pointer interaction.</p>
   {[48,56,64,128].map(size=><section key={size}><h2>{size}px artwork</h2><div style={{display:'flex',flexWrap:'wrap',gap:24}}>{SOCIAL_PROFILES.map(profile=><div key={profile.id} style={{minWidth:Math.max(90,size)}}><SocialBadge profile={profile} size={size} preview/></div>)}</div></section>)}
   <h2>Material state references</h2><p>Static QA references only. Real hover and keyboard focus are tested on the footer above.</p>
   <style>{stateCss.replaceAll('.fam-social-badge[href]:hover','.qa-hover .fam-social-badge').replaceAll('.fam-social-badge:focus-visible','.qa-focus .fam-social-badge').replaceAll('.fam-social-badge[href]:active','.qa-pressed .fam-social-badge')}</style>
   {['hover','focus','pressed'].map(state=><section key={state}><h3>{state}</h3><div className={`qa-${state}`} style={{display:'flex',flexWrap:'wrap',gap:24}}>{SOCIAL_PROFILES.map(profile=><SocialBadge key={profile.id} profile={profile} size={64} preview/>)}</div></section>)}
   <h2>Local activation log</h2><output aria-label="Activation log">{events.join(' | ')||'No activations'}</output>
  </section>}
 </div>;
}
createRoot(document.getElementById('root')).render(<BrowserRouter><Review/></BrowserRouter>);
