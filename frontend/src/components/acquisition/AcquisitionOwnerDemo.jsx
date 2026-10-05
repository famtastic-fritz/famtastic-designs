import {useState} from 'react';
const questions={beauty_hair:'Can I request a service consultation?',mobile_detailing:'Can I request a quote for my vehicle and location?',baking_catering:'Can I ask about a celebration order and guest count?'};

/** A deliberately local walkthrough of existing owner/request/content patterns. */
export default function AcquisitionOwnerDemo({niche,businessName}) {
 const [view,setView]=useState('requests'),[opened,setOpened]=useState(false),[status,setStatus]=useState('New'),[reply,setReply]=useState(''),[savedReply,setSavedReply]=useState(''),[content,setContent]=useState('Please include your service, location and preferred date.'),[savedContent,setSavedContent]=useState(''),[notice,setNotice]=useState('');
 return <section className="acquisition-owner" aria-labelledby="owner-demo-heading">
  <span className="acquisition-eyebrow">A website you can work from</span><h2 id="owner-demo-heading">Your phone. Your next customer. Your move.</h2>
  <p>Read a request, plan your response, and keep your website details current. Tell us which actions your starter website needs; we confirm the connected scope before payment.</p>
  <p className="acquisition-note">Illustrative owner walkthrough · fictional customer · changes stay in this page and reset when you reload. No message is sent and no website is published.</p>
  <div className="acquisition-owner-desk"><header><strong>{businessName} · owner view</strong><span>Practice only</span></header>
   <nav aria-label="Owner walkthrough"><button aria-pressed={view==='requests'} onClick={()=>{setView('requests');setNotice('');}}>Requests</button><button aria-pressed={view==='content'} onClick={()=>{setView('content');setNotice('');}}>Website details</button></nav>
   {view==='requests'?<div><h3>One sample inquiry</h3><p><strong>Example customer</strong> · <span aria-label="Sample request status">{status}</span></p><p>{questions[niche]||questions.beauty_hair}</p>
    {!opened?<button onClick={()=>setOpened(true)}>Open sample inquiry</button>:<><label>Request status<select aria-label="Request status" value={status} onChange={e=>{setStatus(e.target.value);setNotice('Sample request status updated locally.');}}><option>New</option><option>Reviewing</option><option>Responded</option></select></label><form onSubmit={e=>{e.preventDefault();setSavedReply(reply);setNotice('Sample reply saved locally. Nothing was sent.');}}><label>Your sample reply<textarea aria-label="Your sample reply" required value={reply} onChange={e=>setReply(e.target.value)} maxLength={1000}/></label><button>Save sample reply</button></form>{savedReply&&<p className="acquisition-owner-saved">Saved sample reply: {savedReply}</p>}</>}
   </div>:<form onSubmit={e=>{e.preventDefault();setSavedContent(content);setNotice('Sample website detail saved locally. Nothing was published.');}}><h3>Help the next customer know what to send</h3><label>Inquiry instructions<textarea aria-label="Inquiry instructions" required value={content} onChange={e=>setContent(e.target.value)} maxLength={1000}/></label><button>Save sample website detail</button>{savedContent&&<p className="acquisition-owner-saved">Saved sample detail: {savedContent}</p>}</form>}
   {notice&&<p role="status">{notice}</p>}
  </div>
  <p className="acquisition-note">The current workspace supports project requests, status review, conversations with FAMtastic and website content submissions. A linked Owner Desk can review and update supported customer requests. This practice view does not connect a customer inbox. Calendar sync, ecommerce, outside subscriptions and larger management tools need separate confirmed scope.</p>
 </section>;
}
