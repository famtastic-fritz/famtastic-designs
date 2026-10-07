import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import BrandLogo from '../components/BrandLogo.jsx';
import {getAcquisitionSample,saveSamplePreference,validSampleToken,privatePreviewPath} from '../api/acquisition.js';
import AcquisitionOwnerDemo from '../components/acquisition/AcquisitionOwnerDemo.jsx';
import '../acquisition.css';

export default function AcquisitionSamplePage() {
  const {token=''}=useParams();
  const [state,setState]=useState({status:'loading',sample:null});
  const [busy,setBusy]=useState(false);
  const [message,setMessage]=useState('');
  const [attempt,setAttempt]=useState(0);
  useEffect(()=>{
    const controller=new AbortController();
    if (!validSampleToken(token)) {setState({status:'error',sample:null});return;}
    setState({status:'loading',sample:null});
    getAcquisitionSample(token,controller.signal).then(({sample})=>{
      if (!sample || sample.illustrative!==true || !Array.isArray(sample.recipes) || sample.recipes.length<1 || sample.recipes.length>2 || sample.recipes.some(r=>!privatePreviewPath(r.preview_path,token))) throw new Error('Unavailable');
      setState({status:'ready',sample});
    }).catch(e=>{if(e.name!=='AbortError')setState({status:'error',sample:null});});
    return ()=>controller.abort();
  },[token,attempt]);
  async function choose(recipe_id){
    setBusy(true);setMessage('');
    try {await saveSamplePreference(token,recipe_id);setState(s=>({...s,sample:{...s.sample,preference:recipe_id}}));setMessage('Preference saved. Your interview will shape your own directions.');}
    catch(e){setMessage(e.message);}finally{setBusy(false);}
  }
  const sample=state.sample;
  const single=sample?.recipes?.length===1;
  const supplied=sample?.context_classification==='supplied_generic_preparation';
  const continuePath=`/login?mode=register&sample_continuation=${token}`;
  return <main className={`acquisition-page${single ? ' acquisition-single' : ''}`}>
    <header className="acquisition-header"><Link to="/" aria-label="FAMtastic Designs home"><BrandLogo /></Link><span>FAMtastic Sample Lab</span></header>
    {state.status==='loading' ? <p role="status">Opening your website preview…</p> : state.status==='error' ? <section className="acquisition-state"><h1>This invitation is unavailable.</h1><p>It may have expired or been replaced. Ask us for your current invitation.</p>{validSampleToken(token) && <button className="acquisition-button" onClick={()=>setAttempt(n=>n+1)}>Try again</button>}<a className="acquisition-button" href="mailto:support@famtasticdesigns.com">Contact FAMtastic Designs</a></section> : <>
      <section className="acquisition-intro"><span className="acquisition-eyebrow">{single ? 'Your website, taking shape.' : 'Two possibilities. Your business.'}</span><h1>{sample.business_name}<br/><em>Run your next move from your phone.</em></h1><p>{single ? 'See a finished illustrative direction, try the phone owner view, and start your interview when you’re ready.' : 'Explore your directions, try the phone owner view, then tell us which daily work matters most.'}</p><p className="acquisition-note">{supplied ? 'Illustrative preview using supplied business and industry information; services and imagery are examples.' : 'Illustrative samples using verified business details.'} Your own three design directions follow your completed interview and review.</p><Link className="acquisition-button" to={continuePath}>Start my website interview</Link></section>
      <section className="acquisition-directions" aria-label={single ? 'Your illustrative website preview' : 'Your two illustrative directions'}>{sample.recipes.map((recipe,i)=><article key={recipe.id} className="acquisition-direction"><div className="acquisition-direction-heading"><span>{single ? 'Website preview' : `Direction ${String(i+1).padStart(2,'0')}`}</span><h2>{recipe.title}</h2><p>{recipe.summary}</p></div><iframe title={`${recipe.title} illustrative sample for ${sample.business_name}`} src={privatePreviewPath(recipe.preview_path,token)} sandbox={recipe.interactive===true ? (recipe.id==='coastal_current_hvac_v1' ? "allow-scripts allow-top-navigation-by-user-activation" : "allow-scripts") : ""} referrerPolicy="no-referrer" loading="lazy"/><div className="acquisition-direction-actions"><a href={privatePreviewPath(recipe.preview_path,token)} target="_blank" rel="noreferrer">Open full sample ↗</a><button disabled={busy} aria-pressed={sample.preference===recipe.id} onClick={()=>choose(recipe.id)}>{sample.preference===recipe.id?'Preference saved':'I like this direction'}</button></div></article>)}</section>
      <AcquisitionOwnerDemo niche={sample.niche} businessName={sample.business_name} />
      {message && <p role="status" className="acquisition-message">{message}</p>}
      <section className="acquisition-next"><div><span className="acquisition-eyebrow">Bring your details. Keep your momentum.</span><h2>Let’s make it yours.</h2><p>Create your free workspace with the email that received this invitation. Your preference continues into your interview. We confirm the scope before payment; a focused starter website is $199 upfront.</p><p className="acquisition-note">First-year hosting included. After that, $9.99/month requires separate authorization. Domain renewal is separate. A larger project receives its own scope recommendation.</p></div><Link className="acquisition-button" to={continuePath}>Create my workspace</Link></section>
      <footer className="acquisition-footer"><p>A preference helps us understand your taste; it is not final design approval.</p><a href="https://famtasticdesigns.com/connect" rel="noreferrer">Digital business card</a><a href="https://famtasticdesigns.com/connect/commercial.mp4" rel="noreferrer">Watch our commercial</a></footer>
    </>}
  </main>;
}
