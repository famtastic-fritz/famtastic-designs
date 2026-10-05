#!/usr/bin/env python3
"""Synthetic qualification gates; these fixtures never enter a real cohort."""
import datetime
import importlib.util
import random
import pathlib
import tempfile
import hashlib
import copy

spec = importlib.util.spec_from_file_location("cohort", pathlib.Path(__file__).with_name("acquisition-cohort.py"))
cohort = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cohort)
assert cohort.public_source("https://www.facebook.com/profile.php?id=100082843062469")
assert not cohort.public_source("https://www.facebook.com/profile.php?id=100&token=secret")
assert not cohort.public_source("https://www.facebook.com/profile.php?id=owner@example.test")
now = datetime.datetime(2026, 10, 5, 20, 0, tzinfo=datetime.timezone.utc)
row = {"Contact_Email": "controlled@example.test", "source_key": "synthetic-row", "previous_260": False, "niche": "mobile_detailing"}
receipt = {"source_key": "synthetic-row", "contact_email": "controlled@example.test", "business_identity": "fictional-controlled-company", "niche_confirmed": True, "confirmed_niche": "mobile_detailing"}
for key in cohort.EVIDENCE_KEYS:
    receipt[key] = {"status": "verified", "reviewer": "synthetic-test", "artifact_sha256": "a" * 64, "source_url": "https://example.test/controlled", "checked_at": "2026-10-05T19:00:00Z", "expires_at": "2026-10-06T19:00:00Z"}
receipt["history_suppression"].update(prior_contact="never_contacted", suppressed=False, human_reply=False, purchased=False, ledger_revision="synthetic-ledger")
receipt["jurisdiction"].update(sending_permitted=True, country="US", basis="synthetic-only")
receipt["provider_eligibility"].update(provider="mailforge", sending_permitted=True)
receipt["address_verification"]["result"] = "valid"
receipt["site_booking"] = {"status": "unknown"}
assert cohort.qualification_reasons(row, receipt, now) == []
# Neutral business/niche wording needs no website/booking/owner enrichment.
neutral = dict(receipt);neutral.pop("site_booking")
assert cohort.qualification_reasons(row, neutral, now) == []
neutral["bindings"] = {"recipient_name": "", "booking_url": ""}
assert cohort.qualification_reasons(row, neutral, now) == []
neutral["bindings"] = {"booking_url": "https://example.test/booking"}
assert "unverified_supplied_binding_booking_url" in cohort.qualification_reasons(row, neutral, now)
neutral["binding_evidence"] = {"booking_url": dict(receipt["business"])}
assert cohort.qualification_reasons(row, neutral, now) == []
# Published owned address+MX is not a valid-mailbox result. It can only use a
# reviewed policy allowing that basis; the current sender still requires opt-in.
address = dict(receipt["address_verification"],result="published_business_contact",syntax_valid=True,domain_mail_status="mx_present",deliverability="unknown")
provider = dict(receipt["provider_eligibility"],provider="existing_approved_smtp",provider_account="godaddy_cpanel",address_policy="published_owned_contact_with_domain",opt_in_verified=True,opt_in_evidence=dict(receipt["business"]))
published = dict(receipt,address_verification=address,provider_eligibility=provider)
assert cohort.qualification_reasons(row, published, now) == []
assert published["address_verification"]["deliverability"] == "unknown"
assert "godaddy_commercial_opt_in_missing" in cohort.qualification_reasons(row,dict(published,provider_eligibility=dict(provider,opt_in_verified=False)),now)
assert "existing_sender_account_unresolved" in cohort.qualification_reasons(row,dict(published,provider_eligibility=dict(provider,provider_account=None)),now)
assert "unverified_provider_opt_in" in cohort.qualification_reasons(row,dict(published,provider_eligibility=dict(provider,opt_in_evidence={})),now)
assert "address_basis_unresolved" in cohort.qualification_reasons(row,dict(published,address_verification=dict(address,domain_mail_status="unknown")),now)
assert "address_unverified" in cohort.qualification_reasons(row,dict(receipt,address_verification=address),now)
assert "unverified_contact_ownership" in cohort.qualification_reasons(row,dict(published,contact_ownership=dict(receipt["contact_ownership"],status="unknown")),now)
assert "niche_not_confirmed_or_out_of_scope" in cohort.qualification_reasons(row, dict(receipt, niche_confirmed=False), now)
assert "niche_not_confirmed_or_out_of_scope" in cohort.qualification_reasons(row, dict(receipt, confirmed_niche="auto_mechanics"), now)
assert "niche_not_confirmed_or_out_of_scope" in cohort.qualification_reasons(row, dict(receipt, confirmed_niche="tinting"), now)
assert "niche_not_confirmed_or_out_of_scope" in cohort.qualification_reasons(dict(row, niche="baking_catering"), dict(receipt, confirmed_niche="private_chef"), now)
assert "niche_not_confirmed_or_out_of_scope" in cohort.qualification_reasons(dict(row, niche="beauty_hair"), dict(receipt, confirmed_niche="beauty_products"), now)
receipt["niche"]["status"] = "unknown"
assert "unverified_niche" in cohort.qualification_reasons(row, receipt, now)
receipt["niche"]["status"] = "verified"
assert "missing_row_bound_receipt" in cohort.qualification_reasons(row, {}, now)
wrong = dict(receipt, contact_email="other@example.test")
assert "missing_row_bound_receipt" in cohort.qualification_reasons(row, wrong, now)
receipt["contact_ownership"]["expires_at"] = "2026-10-04T19:00:00Z"
assert "stale_or_invalid_contact_ownership" in cohort.qualification_reasons(row, receipt, now)
receipt["contact_ownership"]["expires_at"] = "2026-10-06T19:00:00Z"
receipt["contact_ownership"]["source_url"] = "https://example.test/?token=secret"
assert "unsafe_or_missing_source_contact_ownership" in cohort.qualification_reasons(row, receipt, now)
receipt["contact_ownership"]["source_url"] = "https://example.test/controlled"
receipt["history_suppression"]["suppressed"] = True
assert "history_or_suppression_unreconciled" in cohort.qualification_reasons(row, receipt, now)
receipt["history_suppression"]["suppressed"] = False
receipt["history_suppression"]["human_reply"] = True
assert "history_or_suppression_unreconciled" in cohort.qualification_reasons(row, receipt, now)
receipt["history_suppression"]["human_reply"] = False
assert "historical_260_requires_review" in cohort.qualification_reasons(dict(row, previous_260=True), receipt, now)
receipt["history_suppression"]["prior_contact"] = "reviewed_eligible"
assert cohort.qualification_reasons(dict(row, previous_260=True), receipt, now) == []
with tempfile.TemporaryDirectory() as directory:
    private = pathlib.Path(directory)
    artifact = private / "controlled-source.txt"
    artifact.write_text("synthetic source receipt; not an actual business")
    for key in cohort.EVIDENCE_KEYS:
        receipt[key]["artifact_path"] = "controlled-source.txt"
        receipt[key]["artifact_sha256"] = hashlib.sha256(artifact.read_bytes()).hexdigest()
    assert cohort.artifact_reasons(receipt, private) == []
    receipt["business"]["artifact_sha256"] = "b" * 64
    assert "artifact_hash_mismatch_business" in cohort.artifact_reasons(receipt, private)
    receipt["business"]["artifact_path"] = "../outside"
    assert "unsafe_or_missing_artifact_business" in cohort.artifact_reasons(receipt, private)
with tempfile.TemporaryDirectory() as directory:
    private = pathlib.Path(directory); artifact = private / "checked-evidence.txt"
    artifact.write_text("synthetic reviewed address/permission; no actual recipient")
    candidate = copy.deepcopy(published)
    for key in cohort.EVIDENCE_KEYS:
        candidate[key].update(artifact_path=artifact.name,artifact_sha256=hashlib.sha256(artifact.read_bytes()).hexdigest())
    candidate["provider_eligibility"]["opt_in_evidence"].update(artifact_path=artifact.name,artifact_sha256=hashlib.sha256(artifact.read_bytes()).hexdigest())
    assert cohort.artifact_reasons(candidate,private) == []
    candidate["provider_eligibility"]["opt_in_evidence"]["artifact_sha256"] = "b" * 64
    assert "artifact_hash_mismatch_provider_opt_in" in cohort.artifact_reasons(candidate,private)
    candidate["bindings"] = {"phone": "+1 555 010 1234"}
    candidate["binding_evidence"] = {"phone": {"status": "verified"}}
    assert "missing_artifact_binding_phone" in cohort.artifact_reasons(candidate,private)
rows = [{"niche": n, "source_key": f"{n}-{i}"} for n in cohort.NICHES.values() for i in range(50)]
cohort.assign(rows, random.Random(123))
for n in cohort.NICHES.values():
    assert sum(r["niche"] == n and r["experiment_arm"] == "generic" for r in rows) == 25
    assert sum(r["niche"] == n and r["experiment_arm"] == "tailored" for r in rows) == 25
print("PASS: 39 synthetic qualification/assignment checks; no prospect is qualified by these fixtures.")
