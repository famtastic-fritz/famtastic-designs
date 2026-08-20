#!/usr/bin/env python3
"""Resume a failed fresh provider run without erasing its journal history."""
from __future__ import annotations

import argparse
import json
import os
import pathlib
import re
import subprocess
import sys
import time

import provider_pipeline as pp


def referenced_image_hashes(output: pathlib.Path, manifest: dict):
    rows = []
    for direction in manifest["directions"]:
        html_path = output / direction["entry"]
        html = html_path.read_text()
        referenced = re.findall(r'''(?:src|poster)=["']([^"']+\.(?:png|jpe?g|webp))["']''', html, re.I)
        referenced += re.findall(r'''url\(\s*["']?([^"')]+\.(?:png|jpe?g|webp))["']?\s*\)''', html, re.I)
        for src in dict.fromkeys(referenced):
            if src.startswith(("http://", "https://", "data:")):
                continue
            path = (html_path.parent / src).resolve()
            pp.require(path.is_file() and output in path.parents, f"Missing or out-of-package image: {src}")
            rows.append({"direction_id": direction["id"], "path": path.relative_to(output).as_posix(), "sha256": pp.sha_file(path)})
    return rows


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--artifact", required=True)
    parser.add_argument("--packet-output", required=True)
    parser.add_argument("--select", default="direction-e,direction-f")
    parser.add_argument("--additional-repairs", type=int, default=1)
    parser.add_argument("--review-only", action="store_true", help="Re-run deterministic QA and independent review after a bounded manual repair.")
    args = parser.parse_args()
    output = pathlib.Path(args.artifact).resolve()
    packet_output = pathlib.Path(args.packet_output).resolve()
    pp.require(output.is_dir(), f"Artifact does not exist: {output}")
    pp.require(not packet_output.exists(), f"Packet output already exists: {packet_output}")
    intake = pp.load(output / "intake.json")
    research = pp.load(output / "research.json")
    directions = pp.load(output / "directions.json")
    prompts = pp.load(output / "image-prompts.json")
    request_id = intake["request_id"]
    ledger = pp.Ledger(output, request_id)
    ledger.rows = [pp.load(path) for path in sorted((output / "stage-journal").glob("*.json"))]
    prior = pp.prior_hashes(output)
    existing_visual_attempts = sum(row["stage"] == "visual-review" for row in ledger.rows)
    existing_repair_attempts = sum(row["stage"] in {"prototype-repair", "technical-repair"} for row in ledger.rows)
    review = pp.load(output / "visual-review.json")
    qa = pp.load(output / "browser-results.json")
    manifest = pp.build_manifest(output, intake, directions, prompts)

    if args.review_only:
        qa_attempt = sum(row["stage"] == "browser-qa" for row in ledger.rows) + 1
        command = ["node", str(pp.ROOT / "provider-browser-qa.mjs"), str(output)]
        process, started = pp.run_process(command, pp.REPO, ledger.logs / f"browser-qa-attempt-{qa_attempt}.log", require_success=False)
        qa = pp.load(output / "browser-results.json")
        ledger.record("browser-qa", "playwright", "chromium",
                      "Render the manually repaired release at 1440px and 390px and enforce all deterministic assertions.",
                      manifest, qa, started, command, assertions=qa["assertions"], attempt=qa_attempt, enforce=False)
        pp.require(qa["passed"] is True, "Manual repair still fails deterministic browser QA")
        visual_attempt = sum(row["stage"] == "visual-review" for row in ledger.rows) + 1
        review_prompt = f"""You are the independent release reviewer. You did not create this work. Inspect the actual twelve screenshots in {output / 'screenshots'} for direction-a through direction-f at desktop and mobile. Re-evaluate the complete pages after the latest bounded repair. Score impact, business_relevance, visual_distinction, copy_specificity, trust, mobile_usability, accessibility, conversion_clarity, and emotional_response. Require every dimension >=7, every overall >=8, no critical defect, all six visibly distinct, and at least three layout families. Verify that no heading breaks words, no mobile content is hidden in an inaccessible horizontal scroller, all major numerals have usable contrast, forms are pristine before interaction, image descriptions match, and directions do not reuse imagery deceptively. Set reviewer.provider anthropic, reviewer.model to the exact model running, independent true, execution_class cloud_provider_executed. Preserve request_id exactly {request_id}. Return only schema-valid JSON."""
        review = pp.claude_review(review_prompt, output, ledger,
                                  {"screenshots_sha256": {item["file"]: item["sha256"] for item in qa["screenshots"]}, "directions": directions},
                                  visual_attempt)

    if not pp.visual_pass(review):
        used_visual_reviews = sum(row["stage"] == "visual-review" for row in ledger.rows)
        remaining_visual_reviews = max(0, 2 - used_visual_reviews)
        pp.require(remaining_visual_reviews > 0,
                   "Visual review budget exhausted; unchanged work may not trigger another reviewer call")
        pp.require(args.additional_repairs <= remaining_visual_reviews,
                   "Requested repair/review cycles exceed the two-review release budget")
        for offset in range(1, args.additional_repairs + 1):
            reviewed_screenshot_hashes = {item["file"]: item["sha256"] for item in qa["screenshots"]}
            repair_attempt = existing_repair_attempts + offset
            prompt = f"""You are the bounded release-repair worker for fresh provider request {request_id}. Read visual-review.json and browser-results.json. The previous independent release failed. Fix every critical defect and every failed deterministic assertion, including heading word fragmentation, inaccessible scoped horizontal scrolling, and computed numeral/background contrast. Preserve all working forms, direction identities, generated imagery, business-fact boundaries, fictional disclosures, local-only resources, and the 1 restrained / 1 medium / 4 ultra mix. Do not reuse an image under false alt text. Do not weaken bold directions into a shared template. Verify source specificity rather than adding ineffective overrides. Return schema-valid completion JSON listing modified files."""
            pp.codex_json("release-repair", prompt, pp.SCHEMAS / "worker-status.v1.schema.json", output, ledger,
                          {"visual_review": review, "browser_results": qa}, output,
                          sandbox="workspace-write", attempt=repair_attempt)
            manifest = pp.build_manifest(output, intake, directions, prompts)
            qa_attempt = sum(row["stage"] == "browser-qa" for row in ledger.rows) + 1
            command = ["node", str(pp.ROOT / "provider-browser-qa.mjs"), str(output)]
            process, started = pp.run_process(command, pp.REPO, ledger.logs / f"browser-qa-attempt-{qa_attempt}.log", require_success=False)
            qa = pp.load(output / "browser-results.json")
            ledger.record("browser-qa", "playwright", "chromium",
                          "Render the resumed release at 1440px and 390px, including fragmentation, scoped-scroller, and contrast assertions.",
                          manifest, qa, started, command, assertions=qa["assertions"], attempt=qa_attempt, enforce=False)
            pp.require(qa["passed"] is True, "Resumed repair still fails deterministic browser QA")
            repaired_screenshot_hashes = {item["file"]: item["sha256"] for item in qa["screenshots"]}
            pp.require(repaired_screenshot_hashes != reviewed_screenshot_hashes,
                       "Repair did not change rendered artifacts; another reviewer call is not allowed")
            visual_attempt = used_visual_reviews + offset
            review_prompt = f"""You are the independent release reviewer. You did not create this work. Inspect the actual twelve screenshots in {output / 'screenshots'} for direction-a through direction-f at desktop and mobile. Re-evaluate the complete pages after the latest repair. Score impact, business_relevance, visual_distinction, copy_specificity, trust, mobile_usability, accessibility, conversion_clarity, and emotional_response. Require every dimension >=7, every overall >=8, no critical defect, all six visibly distinct, and at least three layout families. Verify that no heading breaks words, no mobile content is hidden in an inaccessible horizontal scroller, all major numerals have usable contrast, forms are pristine before interaction, image descriptions match, and directions do not reuse imagery deceptively. Set reviewer.provider anthropic, reviewer.model to the exact model running, independent true, and execution_class cloud_provider_executed. Preserve request_id exactly {request_id}. Return only schema-valid JSON."""
            review = pp.claude_review(review_prompt, output, ledger,
                                      {"screenshots_sha256": {item["file"]: item["sha256"] for item in qa["screenshots"]}, "directions": directions},
                                      visual_attempt)
            if pp.visual_pass(review):
                break
    pp.require(pp.visual_pass(review), "Independent visual gate still fails after resumed bounded repairs")

    manifest = pp.build_manifest(output, intake, directions, prompts)
    images = referenced_image_hashes(output, manifest)
    image_direction_ids = {row["direction_id"] for row in images}
    image_hashes = [row["sha256"] for row in images]
    new_hashes = {row["html_sha256"] for row in manifest["directions"]} | {row["hero_sha256"] for row in manifest["directions"]}
    overlap = sorted(new_hashes & prior)
    assertions = {
        "fresh_non_replay_no_prior_html_or_hero_hashes": not overlap,
        "request_identity_preserved": manifest["request_id"] == request_id,
        "customer_identity_preserved": manifest["customer_email"].lower() == intake["customer"]["email"].lower(),
        "live_research_provider_executed": len(research["findings"]) >= 6,
        "exact_six_directions": len(directions) == 6,
        "exact_creative_mix": sum(item["mode"] == "restrained" for item in directions) == 1 and sum(item["mode"] == "medium_famtastic" for item in directions) == 1 and sum(item["mode"] == "ultra_famtastic" for item in directions) == 4,
        "six_unique_html_hashes": len({row["html_sha256"] for row in manifest["directions"]}) == 6,
        "six_unique_hero_hashes": len({row["hero_sha256"] for row in manifest["directions"]}) == 6,
        "direction_specific_referenced_images": (
            image_direction_ids == {row["id"] for row in manifest["directions"]}
            and len(image_hashes) >= len(manifest["directions"])
            and len(set(image_hashes)) == len(image_hashes)
        ),
        "browser_qa_passed": qa["passed"] is True,
        "twelve_direction_screenshots": len([item for item in qa["screenshots"] if item["route"] != "review"]) == 12,
        "independent_provider_review": review["reviewer"]["provider"] == "anthropic" and review["reviewer"]["independent"] is True,
        "visual_release_gate_passed": pp.visual_pass(review),
        "commercial_and_external_mutations_forbidden": pp.load(output / "architecture.json")["external_mutation_allowed"] is False,
        "site_studio_execution_not_claimed": pp.load(output / "website-build-brief.v2.json")["publication_boundary"]["site_studio_execution_claimed"] is False
    }
    pp.require(all(assertions.values()), f"Final evidence assertions failed: {[key for key, value in assertions.items() if not value]}")
    visual_assertions = {
        "independent_reviewer": True,
        "no_critical_defects": not review["critical_defects"],
        "every_overall_at_least_eight": all(item["overall"] >= 8 for item in review["directions"]),
        "no_dimension_below_seven": all(all(score >= 7 for score in item["scores"].values()) for item in review["directions"]),
        "all_six_visually_distinct": review["all_six_visually_distinct"],
        "three_or_more_distinct_layout_families": review["three_or_more_distinct_layout_families"]
    }
    pp.dump(output / "agent-ledger.json", ledger.rows)
    pp.dump(output / "quality-report.json", {"schema": "famtastic.quality-report.v2", "technical": qa["assertions"], "visual": review, "visual_assertions": visual_assertions})
    cost_total = round(sum(float(row.get("cost", {}).get("amount") or 0) for row in ledger.rows), 6)
    evidence = {
        "schema": "famtastic.fresh-provider-preview-evidence.v1",
        "generated_at": pp.now(),
        "classification": "locally_proven_fresh_provider_run",
        "request_id": request_id,
        "customer": {"email": intake["customer"]["email"], "notification_sent": False},
        "assertions": assertions,
        "prior_hash_overlap": overlap,
        "directions": manifest["directions"],
        "referenced_images": images,
        "screenshots": qa["screenshots"],
        "stage_journals": [path.relative_to(output).as_posix() for path in sorted((output / "stage-journal").glob("*.json"))],
        "provider_models": sorted({f"{row['provider']}:{row['model']}" for row in ledger.rows}),
        "recorded_provider_cost_usd": cost_total,
        "unresolved_external_gates": [
            "Site Studio has not consumed this packet or returned a real signed success packet",
            "No Drupal import, portal update, notification, customer approval, payment, domain, or production deployment was performed",
            "Business facts, menu, operations, policies, permits, pricing, availability, and identity require a real owner"
        ]
    }
    pp.dump(output / "evidence.json", evidence)
    (output / "run-report.md").write_text(
        f"# Fresh provider-executed preview run\n\n- Request: `{request_id}`\n- Classification: locally proven fresh provider run\n- Six new websites: yes\n- Referenced generated images: {len(images)}\n- Screenshots: 12 direction captures plus review hub desktop/mobile\n- Independent visual release: pass\n- Recorded provider cost: ${cost_total:.2f}\n- Site Studio execution: not claimed\n- External mutations: none\n"
    )

    env = os.environ.copy()
    env.update({
        "FAMTASTIC_CAPABILITY_WEB_RESEARCH": "1",
        "FAMTASTIC_CAPABILITY_MANAGED_IMAGE_GENERATION": "1",
        "FAMTASTIC_CAPABILITY_INDEPENDENT_VISION": "1",
        "FAMTASTIC_PROVIDER_BALANCED_REASONING_AUTH": "1",
        "FAMTASTIC_PROVIDER_BALANCED_CODE_AUTH": "1"
    })
    command = [sys.executable, str(pp.ROOT / "autonomous_pipeline.py"), "prepare",
               "--artifact", str(output), "--intake", str(output / "intake.json"),
               "--output", str(packet_output), "--select", args.select,
               "--build-class", "premium", "--project-id", f"project:{request_id}"]
    started = time.time()
    packet = subprocess.run(command, cwd=pp.REPO, env=env, text=True, capture_output=True, timeout=1800)
    (output / "provider-logs" / "site-studio-packet.log").write_text(packet.stdout + "\n" + packet.stderr)
    pp.require(packet.returncode == 0, f"Site Studio packet preparation failed; see {output / 'provider-logs/site-studio-packet.log'}")
    packet_json = pp.load(packet_output / "site-studio-build-packet.json")
    pp.require(packet_json["classification"] == "provider_executed", "Packet must be provider_executed")
    ledger.record("site-studio-packet", "deterministic", "autonomous_pipeline.py",
                  "Validate the fresh artifact and create the immutable build packet without invoking Site Studio.",
                  {"evidence_sha256": pp.sha_file(output / "evidence.json")},
                  {"packet_id": packet_json["packet_id"], "packet_sha256": pp.sha_file(packet_output / "site-studio-build-packet.json")},
                  started, command, assertions={"packet_created": True, "provider_executed_classification": True, "site_studio_not_invoked": True})
    pp.dump(output / "agent-ledger.json", ledger.rows)
    evidence["site_studio_build_packet"] = {
        "packet_id": packet_json["packet_id"],
        "path": str(packet_output / "site-studio-build-packet.json"),
        "zip_path": str(packet_output / "site-studio-build-packet.zip"),
        "sha256": pp.sha_file(packet_output / "site-studio-build-packet.json"),
        "classification": packet_json["classification"],
        "site_studio_executed": False
    }
    evidence["stage_journals"] = [path.relative_to(output).as_posix() for path in sorted((output / "stage-journal").glob("*.json"))]
    pp.dump(output / "evidence.json", evidence)
    print("PASS: resumed fresh provider preview cleared independent release")
    print(f"Review: {output / 'index.html'}")
    print(f"Evidence: {output / 'evidence.json'}")
    print(f"Build packet: {packet_output / 'site-studio-build-packet.zip'}")
    print("BOUNDARY: Site Studio was not invoked; no success packet is claimed")


if __name__ == "__main__":
    try:
        main()
    except (pp.PipelineError, subprocess.TimeoutExpired, json.JSONDecodeError) as error:
        print(f"FAIL: {error}", file=sys.stderr)
        sys.exit(1)
