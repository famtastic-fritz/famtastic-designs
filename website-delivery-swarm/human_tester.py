#!/usr/bin/env python3
"""Reusable synthetic human tester with an optional disclosed numerology lens."""
from __future__ import annotations
import argparse, datetime as dt, hashlib, json, pathlib

ROOT = pathlib.Path(__file__).resolve().parent
MASTER = {11, 22, 33}

def reduce_number(value: int) -> int:
    while value > 9 and value not in MASTER:
        value = sum(int(d) for d in str(value))
    return value

def life_path_from_date(date_text: str) -> int:
    date = dt.date.fromisoformat(date_text)
    # Reduce month, day, and year separately, preserving master numbers, then reduce total.
    return reduce_number(reduce_number(date.month) + reduce_number(date.day) + reduce_number(date.year))

def build_persona(*, life_path=None, opted_in=False, name="Maya"):
    config=json.loads((ROOT/"config/numerology-lenses.json").read_text())
    base={"agent":"human-experience-tester","name":name,
      "personality":{"traits":["curious","warm","observant","constructively skeptical"],
        "behavior":"Think aloud like a real customer, notice emotional and practical friction, ask before assuming, and separate preference from defect."},
      "numerology":{"enabled":False,"disclosure":config["disclosure"]},
      "decision_boundaries":config["guardrails"]["never_affect"]}
    if opted_in and life_path is not None:
        key=str(int(life_path))
        if key not in config["lenses"]: raise ValueError("life_path must be 1-9, 11, 22, or 33")
        base["numerology"].update({"enabled":True,"life_path":int(life_path),"lens":config["lenses"][key]})
    return base

def commercial_snapshot(context):
    runs=context.get("runs",[]) if isinstance(context,dict) else []
    if runs:
        return [{"request_id":r.get("brief",{}).get("request_id"),
          "package":r.get("architecture",{}).get("package"),
          "addons":[{"sku":a.get("sku"),"category":a.get("category"),"declinable":a.get("declinable"),"price_status":a.get("price_status")} for a in r.get("addons",[])]} for r in runs]
    return {"request_id":context.get("request_id"),"package":context.get("architecture",{}).get("package"),
      "addons":[{"sku":a.get("sku"),"category":a.get("category"),"declinable":a.get("declinable"),"price_status":a.get("price_status")} for a in context.get("addons",[])]}

def stable_hash(value):
    return hashlib.sha256(json.dumps(value,sort_keys=True,separators=(",", ":")).encode()).hexdigest()

def evaluate_experience(context, *, life_path=None, opted_in=False):
    persona=build_persona(life_path=life_path,opted_in=opted_in)
    lens=persona["numerology"].get("lens",{})
    baseline=[
      {"area":"clarity","question":"Can I explain what happens next without rereading the page?"},
      {"area":"trust","question":"Do claims, prices, and recommendations show their source or reason?"},
      {"area":"control","question":"Can I decline an add-on or pause without losing my work?"},
      {"area":"accessibility","question":"Can I complete this with keyboard, mobile, zoom, and plain language?"},
      {"area":"continuity","question":"Will this remain the same request if I create an account?"}]
    creative=[]
    if lens:
        creative=[{"idea":idea,"why":f"Optional Life Path {life_path} lens: {lens['theme']}","authority":"creative_suggestion_only"} for idea in lens["promote"]]
    identity_seed={"request_id":context.get("request_id"),"run_id":context.get("run_id"),"generated_at":context.get("generated_at"),
      "nested_runs":[r.get("run_id") for r in context.get("runs",[])]}
    context_id=context.get("request_id") or context.get("run_id") or (f"evidence:{stable_hash(identity_seed)[:16]}" if any(identity_seed.values()) else context.get("schema")) or "unspecified"
    protected=commercial_snapshot(context)
    neutral_output={"commercial":json.loads(json.dumps(protected)),"presentation":{"voice":["clear","neutral"],"creative_prompts":[],"lens_tests":[]}}
    lens_output={"commercial":json.loads(json.dumps(protected)),"presentation":{"voice":lens.get("voice",["clear","neutral"]),"creative_prompts":creative,"lens_tests":lens.get("test_for",[])}}
    neutral_hash=stable_hash(neutral_output["commercial"]); lens_hash=stable_hash(lens_output["commercial"])
    assertions=context.get("assertions",{})
    observations=[
      {"area":"clarity","finding":"The routine exposes its classification and decision path.","evidence":bool(context.get("routine") or context.get("architecture"))},
      {"area":"trust","finding":"Evidence assertions are visible and machine-readable.","evidence":bool(assertions) and all(assertions.values())},
      {"area":"control","finding":"Add-ons retain category, decline boundary, and price-source status.","evidence":all(a.get("price_status") and a.get("declinable") is not None for item in (protected if isinstance(protected,list) else [protected]) for a in item.get("addons",[]))},
      {"area":"continuity","finding":"The protected snapshot retains request identity where provided.","evidence":all(item.get("request_id") for item in (protected if isinstance(protected,list) else [protected]))},
      {"area":"accessibility","finding":"No accessibility audit is present in this context.","evidence":bool(context.get("accessibility_audit"))},
      {"area":"emotional_response","finding":"The persona can state a reaction, but customer emotion requires observation or feedback.","evidence":bool(context.get("customer_feedback"))}]
    control={"neutral_output":neutral_output,"lens_output":lens_output,"neutral_commercial_hash":neutral_hash,
      "lens_commercial_hash":lens_hash,"same_commercial_result":neutral_hash==lens_hash,
      "lens_affects_only":["voice","creative_prompts","lens_tests"]}
    return {"persona":persona,"context_id":context_id,
      "baseline_tests":baseline,"lens_tests":lens.get("test_for",[]),"creative_prompts":creative,
      "observations":observations,"objections":[o["finding"] for o in observations if not o["evidence"]],
      "next_action_confidence":"high" if all(o["evidence"] for o in observations) else "needs_evidence",
      "required_control_comparison":bool(lens),"control_comparison":control,
      "commercial_decisions_unchanged":control["same_commercial_result"]}

def main():
    p=argparse.ArgumentParser(); p.add_argument("--input",required=True); p.add_argument("--output")
    p.add_argument("--life-path",type=int); p.add_argument("--opt-in",action="store_true"); p.add_argument("--birth-date")
    a=p.parse_args(); context=json.loads(pathlib.Path(a.input).read_text())
    life_path=a.life_path
    if a.birth_date:
        if not a.opt_in: p.error("--birth-date requires --opt-in")
        life_path=life_path_from_date(a.birth_date)
    result=evaluate_experience(context,life_path=life_path,opted_in=a.opt_in)
    payload=json.dumps(result,indent=2)+"\n"
    pathlib.Path(a.output).write_text(payload) if a.output else print(payload,end="")
if __name__=="__main__": main()
