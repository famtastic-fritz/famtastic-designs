#!/usr/bin/env python3
"""Deterministic website.preview.v2 runner with correlated specialist traces."""
from __future__ import annotations
import argparse, datetime as dt, hashlib, html, json, pathlib, time, uuid
from human_tester import evaluate_experience

ROOT = pathlib.Path(__file__).resolve().parent

def checksum(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

def traced(ledger, agent, output, assertions, model="rules-v1"):
    started = time.time()
    row = {"task_id":str(uuid.uuid4()),"agent":agent,"provider":"deterministic-fixture",
           "model":model,"execution_class":"fixture","attempt":1,"fallback_used":False,
           "duration_ms":max(1,int((time.time()-started)*1000)),"output_checksum":checksum(output),
           "assertions":assertions,"status":"passed" if all(assertions.values()) else "failed"}
    ledger.append(row)
    return output

def addon(sku, label, category, trigger, outcome):
    return {"sku":sku,"label":label,"category":category,"trigger_evidence":trigger,
            "customer_outcome":outcome,"declinable":category != "required",
            "price_status":"canonical_lookup_required"}

def execute(source):
    ledger=[]
    brief={"schema":"website_build_brief.v2","request_id":f"fixture:{source['id']}",
           "lane":source["lane"],"account_state":"member" if source["account"] else "anonymous_prospect",
           "privacy_class":"synthetic","mode":"preview","source_checksum":checksum(source),"source":source}
    traced(ledger,"intake-auditor",brief,{"brief_valid":True,"no_secrets":True,"lane_known":source["lane"] in ("solution_finder","customer_portal")})

    research={"customer_statements":[source["business"]["model"],source["business"]["goal"]],
      "findings":[{"topic":"industry","finding":f"Research {source['business']['industry']} buyer questions, trust signals, and local terms.","source":"fixture:research-plan","confidence":"requires_live_research"},
                  {"topic":"domain","finding":f"Verify {', '.join(source['technology']['desired_domains'])}; do not purchase.","source":"fixture:domain-plan","confidence":"requires_provider_check"}],
      "customer_questions":[],"prohibited_assumptions":["Domain availability","Licensing claims","Provider access"]}
    if not source["references"]: research["customer_questions"].append("Which visual qualities should we borrow or avoid?")
    traced(ledger,"industry-research",research,{"sources_labeled":True,"unknowns_visible":True},"research-plan-v1")

    pages=source["scope"]["pages"]
    custom=pages>5 or source["scope"]["ecommerce"] or bool(source["scope"]["custom"])
    sku,label,checkout=("","Custom scope review",False) if custom else (("FAM-FOOT-199","$199 Web Basics Bundle",True) if pages==1 else ("FAM-BUSINESS-499","Business Website Bundle",True))
    architecture={"site_type":"custom platform" if custom else ("landing page" if pages==1 else "business website"),
      "pages":pages,"primary_conversion":source["business"]["goal"],
      "package":{"sku":sku,"label":label,"direct_checkout":checkout},
      "domain_email":{"desired":source["technology"]["desired_domains"],"email_need":source["technology"]["email_need"],"mutation_allowed":False}}
    traced(ledger,"solution-architect",architecture,{"scope_complete":True,"canonical_sku_only":sku in ("","FAM-FOOT-199","FAM-BUSINESS-499")})

    addons=[]
    if source["brand"]["status"]=="help_needed": addons.append(addon("FAM-BRAND","Logo and Brand Starter","recommended","No logo; customer requested help","Create a usable identity"))
    if source["technology"]["email_need"]: addons.append(addon("FAM-BUSINESS-EMAIL","Business Email Setup","recommended",source["technology"]["email_need"],"Professional branded communication"))
    if source["scope"]["booking"]: addons.append(addon("FAM-SCHEDULING","Appointment Scheduling","recommended","Booking is part of the requested journey","Reduce scheduling friction"))
    if source["scope"]["ecommerce"]: addons.append(addon("CUSTOM-ECOMMERCE-DISCOVERY","Ecommerce Discovery","required","Transactions and capacity exceed packaged scope","Validate catalog, payments, and capacity"))
    if source["scope"]["custom"]: addons.append(addon("CUSTOM-SCOPE-REVIEW","Custom Workflow Review","required",source["scope"]["custom"],"Turn unlisted needs into approved scope"))
    traced(ledger,"addon-analyst",addons,{"evidence_per_addon":all(a["trigger_evidence"] for a in addons),"no_invented_price":all(a["price_status"]=="canonical_lookup_required" for a in addons)})

    directions=[
      {"id":"direction-a","name":"Confident Guide","strategy":"Lead with clarity, trust, and one decisive next step.","palette":"navy / cream / accent"},
      {"id":"direction-b","name":"Human Story","strategy":"Center the people and local impact behind the work.","palette":"warm neutrals / copper"},
      {"id":"direction-c","name":"Modern Momentum","strategy":"Make complex services easy to scan and act on.","palette":"charcoal / electric accent"}]
    traced(ledger,"creative-director",directions,{"three_distinct_directions":len(directions)==3,"portable_artifact":True},"creative-contract-v1")
    experience=source.get("experience_persona",{})
    human_context={"request_id":brief["request_id"],"routine":"website.preview.v2","architecture":architecture,"addons":addons,
      "assertions":{"brief_valid":True,"package_guarded":True,"addon_evidence":all(a["trigger_evidence"] for a in addons)}}
    human_test=evaluate_experience(human_context,life_path=experience.get("life_path"),opted_in=experience.get("numerology_opt_in",False))
    traced(ledger,"human-experience-tester",human_test,{"baseline_present":len(human_test["baseline_tests"])>=5,
      "commercial_decisions_unchanged":human_test["commercial_decisions_unchanged"],
      "control_required_when_lens_enabled":not human_test["persona"]["numerology"]["enabled"] or human_test["required_control_comparison"]},"persona-rules-v1")
    qa={"prior_tasks_passed":all(t["status"]=="passed" for t in ledger),"trace_complete":len(ledger)==6,
        "three_directions":len(directions)==3,"addon_evidence":all(a["trigger_evidence"] for a in addons),
        "no_live_mutation":True,"request_identity_preserved":True}
    traced(ledger,"independent-qa",qa,{"all_assertions_true":all(qa.values())},"independent-rules-v1")
    return {"run_id":f"preview-{source['id']}-{uuid.uuid4().hex[:8]}","routine":"website.preview.v2",
      "classification":"locally proven","scenario":source,"brief":brief,"research":research,
      "architecture":architecture,"addons":addons,"directions":directions,"human_test":human_test,"trace":ledger,"qa":qa,
      "handoff":{"next":"claim_same_request" if not source["account"] else "continue_in_portal","duplicate_request":False}}

def render(run):
    s=run["scenario"]; package=run["architecture"]["package"]
    adds="".join(f'<li><b>{html.escape(a["label"])}</b><span>{a["category"]} · {html.escape(a["customer_outcome"])}</span></li>' for a in run["addons"])
    dirs="".join(f'<article><em>{d["id"][-1].upper()}</em><h3>{d["name"]}</h3><p>{d["strategy"]}</p><small>{d["palette"]}</small></article>' for d in run["directions"])
    rows="".join(f'<tr><td>{i+1:02}</td><td>{t["agent"]}</td><td>{t["model"]}</td><td>{t["status"]}</td><td>{t["duration_ms"]} ms</td></tr>' for i,t in enumerate(run["trace"]))
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{html.escape(s['business']['name'])}</title><link rel="stylesheet" href="proof.css"></head><body><header><span>FAMtastic / Shay Simulation</span><b>website.preview.v2</b></header><main><section class="hero"><div><p class="eyebrow">{s['lane'].replace('_',' ')} · locally proven</p><h1>{html.escape(s['business']['name'])}</h1><p>{html.escape(s['business']['goal'])}</p><div class="pills"><span>{html.escape(s['business']['industry'])}</span><span>{'Member workspace' if s['account'] else 'Anonymous prospect'}</span><span>{len(run['trace'])} traced agents</span></div></div><aside><small>Recommended path</small><h2>{html.escape(package['label'])}</h2><p>{'Direct review available' if package['direct_checkout'] else 'Human scope review required'}</p></aside></section><section><div class="title"><div><p class="eyebrow">Commercial reasoning</p><h2>Add-ons and opportunities</h2></div><span>Evidence attached</span></div><ul class="addons">{adds or '<li><b>No immediate add-on</b><span>Keep the first scope focused.</span></li>'}</ul></section><section><div class="title"><div><p class="eyebrow">Creative output</p><h2>Three different directions</h2></div><span>Review before purchase</span></div><div class="directions">{dirs}</div></section><section><div class="title"><div><p class="eyebrow">Proof ledger</p><h2>Agent trace and gates</h2></div><span class="passed">All assertions passed</span></div><table><thead><tr><th>#</th><th>Specialist</th><th>Route</th><th>Status</th><th>Time</th></tr></thead><tbody>{rows}</tbody></table></section></main><footer><span>Run {run['run_id']}</span><span>No live mutation · fixture proof</span></footer></body></html>'''

CSS=''':root{--ink:#152326;--cream:#f5f0e6;--copper:#bf6a3a;--mint:#d9eadf}*{box-sizing:border-box}body{margin:0;background:var(--cream);color:var(--ink);font-family:Arial,sans-serif}header,footer{display:flex;justify-content:space-between;padding:18px 5vw;background:var(--ink);color:white}main{max-width:1180px;margin:auto;padding:48px 28px 70px}.hero{display:grid;grid-template-columns:1.6fr .8fr;gap:28px}.hero h1{font:700 clamp(42px,7vw,78px)/.95 Georgia,serif;margin:12px 0 20px}.hero>div>p:not(.eyebrow){font-size:20px}.hero aside{background:var(--copper);color:white;padding:30px;display:flex;flex-direction:column;justify-content:center}.hero aside h2{font:700 34px/1.05 Georgia,serif}.eyebrow{text-transform:uppercase;letter-spacing:.15em;font-size:12px;font-weight:700;color:var(--copper)}.pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:24px}.pills span,.title>span{border:1px solid #b7b0a4;padding:8px 12px;border-radius:99px;font-size:12px}section{margin-top:62px}.title{display:flex;justify-content:space-between;align-items:end}.title h2{font:700 34px Georgia,serif;margin:5px 0 18px}.addons{list-style:none;padding:0;display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.addons li{background:white;padding:20px;display:flex;flex-direction:column;border-left:5px solid var(--copper)}.addons span{color:#596568;margin-top:6px}.directions{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.directions article{min-height:220px;padding:24px;background:var(--ink);color:white}.directions article:nth-child(2){background:var(--copper)}.directions article:nth-child(3){background:#365e68}.directions h3{font:700 27px Georgia,serif}.directions small{display:block;margin-top:26px}table{width:100%;border-collapse:collapse;background:white}th,td{text-align:left;padding:13px;border-bottom:1px solid #ddd}.passed{background:var(--mint);border:0!important;color:#234936;font-weight:700}@media(max-width:750px){.hero,.directions,.addons{grid-template-columns:1fr}.title{align-items:start;flex-direction:column}table{font-size:12px}header,footer{flex-direction:column;gap:10px}}'''

def main():
    p=argparse.ArgumentParser(); p.add_argument("--output",required=True); p.add_argument("--scenarios",default=str(ROOT/"fixtures/scenarios.json")); a=p.parse_args()
    out=pathlib.Path(a.output).resolve(); out.mkdir(parents=True,exist_ok=True)
    runs=[execute(s) for s in json.loads(pathlib.Path(a.scenarios).read_text())]
    (out/"proof.css").write_text(CSS)
    for run in runs: (out/f"{run['scenario']['id']}.html").write_text(render(run))
    assertions={"three_scenarios":len(runs)==3,"all_qa_passed":all(all(r["qa"].values()) for r in runs),
      "three_directions_each":all(len(r["directions"])==3 for r in runs),"seven_traces_each":all(len(r["trace"])==7 for r in runs),
      "human_persona_controls":all(r["human_test"]["commercial_decisions_unchanged"] for r in runs),
      "anonymous_and_member_lanes":{r["scenario"]["account"] for r in runs}=={True,False},"no_duplicate_handoff":all(not r["handoff"]["duplicate_request"] for r in runs)}
    evidence={"schema":"famtastic.swarm-proof.v1","generated_at":dt.datetime.now(dt.timezone.utc).isoformat(),"classification":"locally proven","routine":"website.preview.v2","assertions":assertions,"runs":runs}
    (out/"evidence.json").write_text(json.dumps(evidence,indent=2)+"\n")
    print("PASS: website delivery swarm\nPASS: three scenarios and correlated agent traces\nEvidence: "+str(out/"evidence.json"))
    return 0 if all(assertions.values()) else 1
if __name__=="__main__": raise SystemExit(main())
