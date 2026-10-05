#!/usr/bin/env python3
"""Local qualification workbench. Private rows stay in ignored .data; no sending."""
import argparse
import collections
import datetime
import hashlib
import json
import os
import pathlib
import re
import secrets
import urllib.parse
import xml.etree.ElementTree as ET
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
NICHES = {
    "Beauty, Hair Styling & Braiding": "beauty_hair",
    "Mobile Detailing, Auto Care & Tinting": "mobile_detailing",
    "Custom Baking, Catering & Private Chefs": "baking_catering",
}
EVIDENCE_KEYS = ("business", "niche", "source", "contact_ownership", "jurisdiction", "provider_eligibility", "address_verification", "history_suppression")
OPTIONAL_EVIDENCE_KEYS = ("site_booking",)
OPTIONAL_BINDINGS = ("recipient_name", "locality", "phone", "booking_url", "inquiry_url")


def xlsx_rows(path):
    """Read the first worksheet without installing or executing workbook code."""
    ns = {"s": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
    with zipfile.ZipFile(path) as archive:
        shared = []
        if "xl/sharedStrings.xml" in archive.namelist():
            shared = ["".join(n.itertext()) for n in ET.fromstring(archive.read("xl/sharedStrings.xml")).findall("s:si", ns)]
        # Supplied sheets use the first tab; preserve explicit sheet name in provenance.
        workbook = ET.fromstring(archive.read("xl/workbook.xml"))
        sheet = workbook.find("s:sheets/s:sheet", ns)
        relationship_id = sheet.attrib["{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id"]
        rels = ET.fromstring(archive.read("xl/_rels/workbook.xml.rels"))
        target = next(r.attrib["Target"] for r in rels if r.attrib["Id"] == relationship_id)
        member = target.lstrip("/") if target.startswith("/") else "xl/" + target
        data = ET.fromstring(archive.read(member))
        rows = []
        for row in data.findall("s:sheetData/s:row", ns):
            cells = {}
            for cell in row.findall("s:c", ns):
                column = re.match(r"[A-Z]+", cell.attrib["r"])[0]
                index = 0
                for char in column:
                    index = index * 26 + ord(char) - 64
                value = cell.find("s:v", ns)
                value = value.text if value is not None else ""
                if cell.attrib.get("t") == "s":
                    value = shared[int(value)]
                elif cell.attrib.get("t") == "inlineStr":
                    value = "".join(cell.find("s:is", ns).itertext())
                cells[index - 1] = value
            rows.append([cells.get(i, "") for i in range(max(cells, default=-1) + 1)])
        header = rows[0]
        return sheet.attrib["name"], [dict(zip(header, row)) for row in rows[1:]]


def normalized_email(row):
    return str(row.get("Contact_Email", row.get("Email", ""))).strip().lower()


def public_source(url):
    try:
        p = urllib.parse.urlsplit(url)
        public_profile_id = p.hostname in ("facebook.com", "www.facebook.com", "m.facebook.com") and p.path == "/profile.php" and re.fullmatch(r"id=[0-9]{1,25}", p.query) is not None
        return p.scheme == "https" and bool(p.hostname) and not p.username and not p.password and (not p.query or public_profile_id) and not p.fragment and p.hostname not in ("localhost", "127.0.0.1")
    except (ValueError, TypeError):
        return False


def qualification_reasons(row, evidence, now=None):
    """Receipts must bind to this exact private row and remain current."""
    now = now or datetime.datetime.now(datetime.timezone.utc)
    reasons = []
    email = normalized_email(row)
    if not re.fullmatch(r"[^\s@]+@[^\s@]+\.[^\s@]+", email):
        reasons.append("invalid_email_syntax")
    if evidence.get("source_key") != row.get("source_key") or evidence.get("contact_email", "").strip().lower() != email:
        return reasons + ["missing_row_bound_receipt"]
    required_items = [(key, evidence.get(key, {})) for key in EVIDENCE_KEYS]
    required_items += [("binding_" + key, evidence.get("binding_evidence", {}).get(key, {}))
                       for key, value in evidence.get("bindings", {}).items() if value]
    if evidence.get("provider_eligibility", {}).get("provider") == "existing_approved_smtp":
        required_items.append(("provider_opt_in", evidence.get("provider_eligibility", {}).get("opt_in_evidence", {})))
    for key, item in required_items:
        if item.get("status") != "verified" or not item.get("reviewer") or not item.get("artifact_sha256") or not re.fullmatch(r"[a-f0-9]{64}", item.get("artifact_sha256", "")):
            reasons.append("unverified_" + key)
            continue
        if key != "history_suppression" and not public_source(item.get("source_url")):
            reasons.append("unsafe_or_missing_source_" + key)
        try:
            checked = datetime.datetime.fromisoformat(item["checked_at"].replace("Z", "+00:00"))
            expires = datetime.datetime.fromisoformat(item["expires_at"].replace("Z", "+00:00"))
            if checked.tzinfo is None or expires.tzinfo is None or checked > now or expires <= now or expires <= checked:
                reasons.append("stale_or_invalid_" + key)
        except (KeyError, ValueError, TypeError):
            reasons.append("missing_receipt_time_" + key)
    # Workbook industry categories are deliberately broader than approved niches.
    if row.get("niche") not in NICHES.values() or evidence.get("niche_confirmed") is not True or evidence.get("confirmed_niche") != row.get("niche"):
        reasons.append("niche_not_confirmed_or_out_of_scope")
    if not evidence.get("business_identity"):
        reasons.append("missing_business_identity")
    history = evidence.get("history_suppression", {})
    if history.get("prior_contact") not in ("never_contacted", "reviewed_eligible") or history.get("suppressed") is not False or history.get("human_reply") is not False or history.get("purchased") is not False or not history.get("ledger_revision"):
        reasons.append("history_or_suppression_unreconciled")
    if evidence.get("jurisdiction", {}).get("sending_permitted") is not True or not evidence.get("jurisdiction", {}).get("country") or not evidence.get("jurisdiction", {}).get("basis"):
        reasons.append("jurisdiction_unresolved")
    if evidence.get("provider_eligibility", {}).get("provider") not in ("mailforge", "existing_approved_smtp") or evidence.get("provider_eligibility", {}).get("sending_permitted") is not True:
        reasons.append("provider_unresolved")
    address = evidence.get("address_verification", {})
    provider = evidence.get("provider_eligibility", {})
    # A mailbox verification result and a reviewed published/confirmed address
    # are different evidence levels. Do not call the latter deliverability proof.
    if provider.get("provider") == "mailforge" or provider.get("address_policy") != "published_owned_contact_with_domain":
        if address.get("result") != "valid":
            reasons.append("address_unverified")
    elif address.get("result") not in ("valid", "published_business_contact", "recipient_confirmed_address") or address.get("syntax_valid") is not True or address.get("domain_mail_status") not in ("mx_present", "implicit_mx"):
        reasons.append("address_basis_unresolved")
    # The currently configured GoDaddy cPanel sender is opt-in only. This is
    # provider-specific, not a claim that every commercial sender needs opt-in.
    if provider.get("provider") == "existing_approved_smtp":
        if provider.get("provider_account") != "godaddy_cpanel":
            reasons.append("existing_sender_account_unresolved")
        if provider.get("opt_in_verified") is not True:
            reasons.append("godaddy_commercial_opt_in_missing")
    for key, value in evidence.get("bindings", {}).items():
        if key not in OPTIONAL_BINDINGS:
            reasons.append("unsupported_optional_binding")
        elif value and evidence.get("binding_evidence", {}).get(key, {}).get("status") != "verified":
            reasons.append("unverified_supplied_binding_" + key)
    if row.get("previous_260") and history.get("prior_contact") != "reviewed_eligible":
        reasons.append("historical_260_requires_review")
    return sorted(set(reasons))


def assign(cohort, rng=None):
    """Randomize once within niche at business level; persist and reuse assignment."""
    rng = rng or secrets.SystemRandom()
    for niche in NICHES.values():
        group = [r for r in cohort if r["niche"] == niche]
        rng.shuffle(group)
        for i, row in enumerate(group):
            row["experiment_arm"] = "generic" if i % 2 == 0 else "tailored"
    return cohort


def artifact_reasons(receipt, private_root):
    """Integrity checks cannot certify factual truth; reviewed source receipts do."""
    reasons = []
    keys = list(EVIDENCE_KEYS)
    # Enrichment is optional; only fields actually supplied to a tailored room
    # require checked evidence. Unknown enrichment is never silently promoted.
    items = [(key, receipt.get(key, {})) for key in keys]
    items += [("binding_" + key, receipt.get("binding_evidence", {}).get(key, {}))
              for key, value in receipt.get("bindings", {}).items() if value]
    if receipt.get("provider_eligibility", {}).get("provider") == "existing_approved_smtp":
        items.append(("provider_opt_in", receipt["provider_eligibility"].get("opt_in_evidence", {})))
    for key, item in items:
        name = item.get("artifact_path")
        if not name:
            reasons.append("missing_artifact_" + key)
            continue
        path = (private_root / name).resolve()
        if not path.is_relative_to(private_root.resolve()) or not path.is_file():
            reasons.append("unsafe_or_missing_artifact_" + key)
            continue
        if hashlib.sha256(path.read_bytes()).hexdigest() != item.get("artifact_sha256"):
            reasons.append("artifact_hash_mismatch_" + key)
    return reasons


def private_write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600), "w") as handle:
        handle.write(data)
    os.chmod(path, 0o600)


def run(args):
    private = pathlib.Path(args.private_dir).resolve()
    private_root = (ROOT / ".data").resolve()
    if not private.is_relative_to(private_root):
        raise ValueError("Private row output must stay in this repository's ignored .data directory.")
    sheet, source = xlsx_rows(args.workbook)
    history_sheet, history = xlsx_rows(args.history_workbook)
    history_emails = {normalized_email(r) for r in history}
    source_hash = hashlib.sha256(pathlib.Path(args.workbook).read_bytes()).hexdigest()
    history_hash = hashlib.sha256(pathlib.Path(args.history_workbook).read_bytes()).hexdigest()
    candidates = []
    for index, row in enumerate(source, start=2):
        if row.get("Industry") not in NICHES:
            continue
        email = normalized_email(row)
        # This pseudonymous key is private as well; never publish contact hashes.
        row = dict(row, source_key=hashlib.sha256((source_hash + ":" + str(index)).encode()).hexdigest(), source_row=index, niche=NICHES[row["Industry"]], previous_260=email in history_emails)
        candidates.append(row)
    receipts = json.loads(pathlib.Path(args.evidence).read_text()) if args.evidence else []
    if not isinstance(receipts, list):
        raise ValueError("Evidence must be a list of row-bound receipts.")
    evidence = {}
    for receipt in receipts:
        key = receipt.get("source_key")
        if key in evidence:
            raise ValueError("Duplicate row-bound receipt; reconcile rather than overwrite.")
        evidence[key] = receipt
    eligible = []
    reasons = collections.Counter()
    seen_businesses = set()
    seen_emails = set()
    for row in candidates:
        receipt = evidence.get(row["source_key"], {})
        held = qualification_reasons(row, receipt)
        if receipt:
            held.extend(artifact_reasons(receipt, private))
        email = normalized_email(row)
        identity = receipt.get("business_identity")
        if email in seen_emails or (identity and identity in seen_businesses):
            held.append("duplicate_business_or_contact")
        seen_emails.add(email)
        if identity:
            seen_businesses.add(identity)
        row["held_reasons"] = held
        reasons.update(held)
        if not held:
            row["qualification_ref"] = hashlib.sha256(json.dumps(receipt, sort_keys=True, separators=(",", ":")).encode()).hexdigest()
            row["business_identity"] = identity
            row["verified_optional_bindings"] = {k: v for k, v in receipt.get("bindings", {}).items() if v and k in OPTIONAL_BINDINGS}
            row["deliverability_evidence"] = "verified_mailbox_receipt" if receipt.get("address_verification", {}).get("result") == "valid" else "unknown; published/recipient-confirmed address and domain only"
            # This is a server-side staff handoff only, never an outbound approval.
            row["qualification"] = {"business_verified": True, "contact_owned": True, "jurisdiction_eligible": True, "provider_eligible": True, "history_reconciled": True, "bindings_verified": True, "niche_confirmed": True, "confirmed_niche": row["niche"], "receipt": "cohort:" + row["qualification_ref"], "sha256": row["qualification_ref"]}
            eligible.append(row)
    selected = []
    for niche in NICHES.values():
        selected.extend([r for r in eligible if r["niche"] == niche][:args.per_niche])
    assignment_path = private / "assignment.json"
    if assignment_path.exists():
        old = json.loads(assignment_path.read_text())
        previous = {r["source_key"]: r["experiment_arm"] for r in old}
        if set(previous) != {r["source_key"] for r in selected}:
            raise ValueError("Cohort changed; explicitly archive previous assignment before randomizing a new cohort.")
        for row in selected:
            row["experiment_arm"] = previous[row["source_key"]]
    else:
        assign(selected)
        private_write(assignment_path, json.dumps(selected, indent=2) + "\n")
    private_write(private / "candidates.jsonl", "".join(json.dumps(r) + "\n" for r in candidates))
    private_write(private / "cohort.jsonl", "".join(json.dumps(r) + "\n" for r in selected))
    template = []
    for row in candidates:
        template.append({"source_key": row["source_key"], "contact_email": normalized_email(row), "business_identity": None, "niche_confirmed": False, "confirmed_niche": None, **{key: {"status": "unknown", "reviewer": None, "source_url": None, "artifact_path": None, "artifact_sha256": None, "checked_at": None, "expires_at": None} for key in EVIDENCE_KEYS + OPTIONAL_EVIDENCE_KEYS}})
    private_write(private / "receipt-template.json", json.dumps(template, indent=2) + "\n")
    summary = {
        "schema": "famtastic.acquisition-cohort-aggregate.v1", "generated_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "sources": [{"file": pathlib.Path(args.workbook).name, "sha256": source_hash, "sheet": sheet, "rows": len(source)}, {"file": pathlib.Path(args.history_workbook).name, "sha256": history_hash, "sheet": history_sheet, "rows": len(history), "status": "previously_contacted_history_requires_reconciliation"}],
        "candidate_count": len(candidates), "verified_eligible_count": len(eligible), "selected_count": len(selected), "target_count": args.per_niche * 3,
        "niches": [{"niche": n, "candidates": sum(r["niche"] == n for r in candidates), "verified_eligible": sum(r["niche"] == n for r in eligible), "selected": sum(r["niche"] == n for r in selected), "shortfall": max(0, args.per_niche - sum(r["niche"] == n for r in selected))} for n in NICHES.values()],
        "all_source_categories": [{"category": k, "candidate_rows": v, "qualification_status": "not independently qualified"} for k, v in collections.Counter(r.get("Industry", "") for r in source).items()],
        "scope_gate": "Row-bound current source evidence must confirm exact approved niche; broad industry labels do not qualify.",
        "source_email_overlap_with_historical_260": sum(r["previous_260"] for r in candidates), "hold_reason_counts": dict(reasons),
        "sending_status": "held_no_dispatch", "assignment": "random_business_within_niche_once_then_persisted", "private_rows": "ignored .data only; excluded from this aggregate",
        "claims": {"syntax_is_business_proof": False, "no_site_inference_from_missing_column": False, "statistical_winner_established": False},
    }
    text = json.dumps(summary, indent=2) + "\n"
    if args.aggregate:
        pathlib.Path(args.aggregate).write_text(text)
    print(text, end="")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbook", required=True)
    parser.add_argument("--history-workbook", required=True)
    parser.add_argument("--evidence", help="Private row-bound current qualification receipts JSON")
    parser.add_argument("--private-dir", default=str(ROOT / ".data/acquisition-199"))
    parser.add_argument("--per-niche", type=int, default=50)
    parser.add_argument("--aggregate", help="Safe aggregate JSON path; no private lead rows")
    args = parser.parse_args()
    if args.per_niche < 1 or args.per_niche > 50:
        parser.error("Initial cohort permits 1..50 per niche.")
    run(args)
