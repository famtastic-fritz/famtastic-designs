#!/usr/bin/env python3
"""Deterministic generic preparation, never verified-business import or dispatch."""
import argparse
import collections
import datetime
import hashlib
import importlib.util
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("acquisition_cohort", pathlib.Path(__file__).with_name("acquisition-cohort.py"))
shared = importlib.util.module_from_spec(spec)
spec.loader.exec_module(shared)
POLICIES = {"existing_approved_smtp": ("godaddy_cpanel", "opt_in_only"), "mailforge": ("mailforge", "verified_address_lawful_use")}
BEAUTY_CATEGORY = "Beauty, Hair Styling & Braiding"


def usable_address(address):
    if len(address) > 254 or "@" not in address:
        return False
    local = address.split("@", 1)[0]
    return len(local) <= 64 and not local.startswith(".") and not local.endswith(".") and ".." not in local and re.fullmatch(r"[a-z0-9!#$%&'*+/=?^_`{|}~.-]+@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}", address) is not None


def contact_hash(email):
    return hashlib.sha256(email.encode()).hexdigest()


def history_index(documents):
    """Accept native hash-keyed rows or explicit contact-address history rows."""
    index = collections.defaultdict(list)
    for document in documents:
        rows = document.get("rows", []) if isinstance(document, dict) else document
        if not isinstance(rows, (dict, list)):
            raise ValueError("History rows must be an address/hash-keyed object or list.")
        for key, item in rows.items() if isinstance(rows, dict) else [(None, item) for item in rows]:
            if not isinstance(item, dict):
                raise ValueError("History entries must be objects.")
            email = shared.normalized_email(item) or str(item.get("contact_email", item.get("recipient", ""))).strip().lower()
            identity = contact_hash(email) if email else key
            if identity and usable_address(str(identity).strip().lower()):
                identity = contact_hash(str(identity).strip().lower())
            if not identity or not re.fullmatch(r"[a-f0-9]{64}", str(identity)):
                raise ValueError("History entry needs explicit contact email or normalized contact SHA-256.")
            index[str(identity)].append(item)
    return index


def positive(value):
    return isinstance(value, (int, float)) and not isinstance(value, bool) and value > 0


def suppression_reasons(entries):
    reasons = set()
    for entry in entries:
        for field, reason in [("opt_out", "opt_out"), ("suppressed", "opt_out"), ("bounced", "bounce"), ("replied", "reply"), ("human_reply", "reply"), ("purchased", "purchase"), ("prior_contact", "prior_contact")]:
            if entry.get(field) is True:
                reasons.add(reason)
        if entry.get("status") in ("unsubscribed", "suppressed", "complained") or positive(entry.get("negative_consent_rows")):
            reasons.add("negative_consent")
        if entry.get("status") == "bounced":
            reasons.add("bounce")
        if positive(entry.get("incoming_messages")) or positive(entry.get("reply_or_purchase_events")):
            reasons.add("reply_or_purchase_review")
        if positive(entry.get("sent_rows")) or entry.get("prior_contact") in ("contacted", "previously_contacted", "sent"):
            reasons.add("prior_contact")
        events = entry.get("event_types", {})
        types = {k for k, v in events.items() if positive(v)} if isinstance(events, dict) else set(events) if isinstance(events, list) else set()
        if types.intersection({"email.replied", "acquisition.human_reply", "acquisition.reply_received", "payment.fulfillment_started", "email.bounced", "email.complained"}):
            reasons.add("known_reply_purchase_or_negative_event")
    return sorted(reasons)


def artifact_receipt(item, email, provider, account, policy, private_root, now):
    """Validate reviewed receipt identity/currentness/bytes, not its factual truth."""
    if not isinstance(item, dict) or item.get("status") != "verified" or not item.get("reviewer"):
        return False
    if str(item.get("recipient", "")).strip().lower() != email or (item.get("provider"), item.get("account"), item.get("policy")) != (provider, account, policy):
        return False
    try:
        checked = datetime.datetime.fromisoformat(item["checked_at"].replace("Z", "+00:00"))
        expires = datetime.datetime.fromisoformat(item["expires_at"].replace("Z", "+00:00"))
        if checked.tzinfo is None or expires.tzinfo is None or checked > now or expires <= now or expires <= checked:
            return False
        path = (private_root / item["artifact_path"]).resolve()
        return path.is_relative_to(private_root.resolve()) and path.is_file() and hashlib.sha256(path.read_bytes()).hexdigest() == item.get("artifact_sha256")
    except (KeyError, ValueError, TypeError, OSError):
        return False


def sender_state(email, sender, evidence, private_root, now):
    provider, account, policy = (sender.get(k, "unknown") for k in ("provider", "account", "policy"))
    result = {"provider": provider, "account": account, "policy": policy, "ready": False, "dispatch_authorized": False, "deliverability": "unknown", "reason": "sender_policy_unknown"}
    if POLICIES.get(provider) != (account, policy):
        return result
    if not evidence:
        result["reason"] = "godaddy_unsolicited_blocked_missing_written_opt_in" if provider == "existing_approved_smtp" else "mailforge_address_or_permitted_use_missing"
        return result
    if not isinstance(evidence, dict) or (evidence.get("provider"), evidence.get("account"), evidence.get("policy")) != (provider, account, policy):
        result["reason"] = "sender_receipt_transport_mismatch"
        return result
    receipts = evidence.get("receipts", {})
    item = receipts.get(contact_hash(email), receipts.get(email, {})) if isinstance(receipts, dict) else {}
    item = item if isinstance(item, dict) else {}
    if provider == "existing_approved_smtp":
        opt_in = item.get("opt_in", {})
        result["ready"] = artifact_receipt(opt_in, email, provider, account, policy, private_root, now) and opt_in.get("opt_in") is True and opt_in.get("basis") == "written_opt_in"
        result["reason"] = "reviewed_written_opt_in" if result["ready"] else "godaddy_unsolicited_blocked_missing_written_opt_in"
    else:
        address, permission = item.get("address", {}), item.get("permitted_use", {})
        valid = artifact_receipt(address, email, provider, account, policy, private_root, now) and address.get("result") == "valid"
        allowed = artifact_receipt(permission, email, provider, account, policy, private_root, now) and permission.get("sending_permitted") is True and bool(permission.get("basis"))
        result["ready"] = valid and allowed
        result["deliverability"] = "valid_mailbox_receipt; inbox_delivery_unknown" if valid else "unknown"
        result["reason"] = "reviewed_mailbox_and_permitted_use" if result["ready"] else "mailforge_address_or_permitted_use_missing"
    return result


def template_plan(row, template_root):
    """New review candidate only; retired six are never proposed send packages."""
    category = str(row.get("Industry", ""))
    base = {"confirmed_niche": None, "send_package": False, "owner_approved": False, "wording": "A business page presenting an offer and a way for customers to send a request; details need your review."}
    if category == BEAUTY_CATEGORY and (template_root / "generic-review/beauty-lab.html").is_file():
        return {**base, "template_id": "polished_barber_lab", "reference_path": "marketing/campaigns/acquisition-199/generic-review/beauty-lab.html", "classification": "illustrative_review_candidate", "creative_status": "owner_review_pending", "mapping_basis": "supplied unverified category; illustrative concept requires services review"}
    return {**base, "template_id": "general_service_business", "reference_path": None, "classification": "general_wording_only", "creative_status": "adaptation_pending", "mapping_basis": "No approved category-specific creative established"}


def prepare(rows, workbook_hash, histories, sender, evidence, private_root, template_root, now=None):
    now = now or datetime.datetime.now(datetime.timezone.utc)
    known_history = history_index(histories)
    seen = set()
    output = []
    counts = collections.Counter(input=len(rows), syntax_usable=0, duplicates=0, suppressed=0, enrichment_known=0, prepared=0, sender_ready=0, history_unknown=0)
    categories = collections.defaultdict(lambda: collections.Counter(input=0, prepared=0, sender_ready=0))
    for index, row in enumerate(rows, start=2):
        email = shared.normalized_email(row)
        identity = contact_hash(email)
        source_key = hashlib.sha256((workbook_hash + ":" + str(index)).encode()).hexdigest()
        category = str(row.get("Industry", ""))
        categories[category]["input"] += 1
        valid = usable_address(email)
        duplicate = valid and email in seen
        if valid:
            counts["syntax_usable"] += 1
            seen.add(email)
        entries = known_history.get(identity, [])
        reasons = suppression_reasons(entries)
        if not entries:
            counts["history_unknown"] += 1
        status = "invalid_syntax" if not valid else "duplicate" if duplicate else "suppressed" if reasons else "prepared"
        if status in ("duplicate", "suppressed"):
            counts["duplicates" if status == "duplicate" else "suppressed"] += 1
        sender_result = sender_state(email, sender, evidence, private_root, now)
        sender_result["ready"] = status == "prepared" and sender_result["ready"]
        if status == "prepared":
            counts["prepared"] += 1
            categories[category]["prepared"] += 1
        if sender_result["ready"]:
            counts["sender_ready"] += 1
            categories[category]["sender_ready"] += 1
        output.append({"source_row": index, "source_key": source_key, "contact_hash": identity, "contact_email": email, "source_row_data": row, "supplied_segment": category, "segment_verification": "unverified_workbook_label", "business_identity": "supplied_unverified", "contact_ownership": "unknown", "enrichment": "unknown", "preparation_status": status, "known_suppression_reasons": reasons, "history_state": "no_known_flags_in_supplied_history" if entries and not reasons else "known_flags" if reasons else "unknown", "sample_plan": template_plan(row, template_root), "sender": sender_result, "native_verified_business_import": False, "dispatch_authorized": False})
    aggregate = {"schema": "famtastic.acquisition-generic-preparation.v1", "counts": dict(counts), "categories": [{"supplied_segment": k, **dict(v)} for k, v in categories.items()], "sender": sender, "preparation_only": True, "native_verified_business_import": False, "dispatch_authorized": False, "claims": {"workbook_segment_verified": False, "ownership_verified": False, "missing_history_is_clearance": False, "sender_readiness_is_dispatch_authorization": False, "inbox_delivery_proven": False}, "enrichment_known_definition": "No external enrichment is imported by this lean generic tool; workbook labels are supplied, unverified."}
    return output, aggregate


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workbook", required=True)
    parser.add_argument("--history", action="append", default=[], help="Private explicit native/address history JSON; may repeat")
    parser.add_argument("--sender-evidence", help="Private account-bound per-recipient current policy receipts")
    parser.add_argument("--provider", choices=["unknown", *POLICIES], default="unknown")
    parser.add_argument("--account", choices=["unknown", "godaddy_cpanel", "mailforge"], default="unknown")
    parser.add_argument("--policy", choices=["unknown", "opt_in_only", "verified_address_lawful_use"], default="unknown")
    parser.add_argument("--private-dir", default=str(ROOT / ".data/acquisition-199/generic"))
    parser.add_argument("--aggregate", help="Public-safe aggregate destination")
    args = parser.parse_args()
    private = pathlib.Path(args.private_dir).resolve()
    if not private.is_relative_to((ROOT / ".data").resolve()):
        parser.error("Private rows must remain under ignored .data.")
    sheet, rows = shared.xlsx_rows(args.workbook)
    workbook_hash = hashlib.sha256(pathlib.Path(args.workbook).read_bytes()).hexdigest()
    histories = [json.loads(pathlib.Path(p).read_text()) for p in args.history]
    evidence = json.loads(pathlib.Path(args.sender_evidence).read_text()) if args.sender_evidence else {}
    sender = {"provider": args.provider, "account": args.account, "policy": args.policy}
    records, aggregate = prepare(rows, workbook_hash, histories, sender, evidence, private, ROOT / "marketing/campaigns/acquisition-199")
    aggregate["source"] = {"file": pathlib.Path(args.workbook).name, "sheet": sheet, "sha256": workbook_hash}
    shared.private_write(private / "generic-prepared.jsonl", "".join(json.dumps(r, sort_keys=True) + "\n" for r in records))
    text = json.dumps(aggregate, indent=2) + "\n"
    if args.aggregate:
        pathlib.Path(args.aggregate).write_text(text)
    print(text, end="")


if __name__ == "__main__":
    main()
