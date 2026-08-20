#!/usr/bin/env python3
"""Production-safe proof for Adobe-enabled marketing use cases.

This validates the reusable contracts and existing local evidence. It never
calls Adobe, consumes credits, publishes content, or writes provider state.
"""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = ROOT / "marketing/adobe-pipeline/use-cases.json"
REPORT = ROOT / "marketing/adobe-pipeline/evidence/latest.json"


def main() -> int:
    data = json.loads(SPEC.read_text())
    assertions = []
    ids = set()
    for case in data["use_cases"]:
        cid = case["id"]
        checks = {
            "unique_id": cid not in ids,
            "scenario_defined": bool(case.get("scenario")),
            "audience_defined": bool(case.get("audience")),
            "offer_defined": bool(case.get("source_offer")),
            "adobe_job_defined": bool(case.get("adobe_jobs")),
            "deliverables_defined": len(case.get("required_outputs", [])) >= 3,
            "distribution_defined": bool(case.get("channels")),
            "cta_defined": str(case.get("cta_path", "")).startswith("/"),
            "local_proof_inputs_exist": all((ROOT / p).is_file() for p in case.get("proof_inputs", [])),
        }
        ids.add(cid)
        status = "locally_proven" if all(checks.values()) and case.get("proof_inputs") else "contract_proven_assets_pending"
        assertions.append({"use_case": cid, "status": status, "checks": checks})

    passed = all(all(a["checks"].values()) for a in assertions)
    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "pipeline": data["pipeline"],
        "safe_mode": True,
        "external_calls": 0,
        "credits_consumed": 0,
        "publishing_performed": False,
        "passed": passed,
        "assertions": assertions,
        "classification_note": "Contract proof validates repeatability. Local proof additionally requires checked-in deliverable evidence; Adobe API proof requires approved credentials and a separate authorized run."
    }
    REPORT.parent.mkdir(parents=True, exist_ok=True)
    REPORT.write_text(json.dumps(report, indent=2) + "\n")
    for item in assertions:
        print(f"{item['use_case']}: {item['status']}")
    print(f"evidence: {REPORT.relative_to(ROOT)}")
    return 0 if passed else 1


if __name__ == "__main__":
    raise SystemExit(main())
