#!/usr/bin/env python3
"""Fresh, provider-executed FAMtastic preview pipeline.

This runner refuses golden artifacts. It calls authenticated model CLIs, creates
six new sites and images, runs deterministic browser QA, asks a different
provider to review the screenshots, performs bounded repairs, and packages the
result for Site Studio without claiming Site Studio executed it.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import html
import json
import os
import pathlib
import re
import shutil
import subprocess
import sys
import time
import uuid


ROOT = pathlib.Path(__file__).resolve().parent
REPO = ROOT.parent
SCHEMAS = ROOT / "provider-schemas"
CODEX_MODEL = os.getenv("FAMTASTIC_CODEX_MODEL", "gpt-5.6-sol")
CLAUDE_MODEL = os.getenv("FAMTASTIC_CLAUDE_REVIEW_MODEL", "opus")


class PipelineError(RuntimeError):
    pass


def now() -> str:
    return dt.datetime.now(dt.timezone.utc).isoformat()


def load(path: pathlib.Path):
    return json.loads(path.read_text())


def dump(path: pathlib.Path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n")


def sha_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def sha_file(path: pathlib.Path) -> str:
    return sha_bytes(path.read_bytes())


def canonical(value) -> bytes:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()


def first_json_object(value: str):
    """Decode the first JSON value while preserving any CLI diagnostics in logs."""
    start = value.find("{")
    require(start >= 0, "Provider stdout did not contain JSON")
    decoded, _ = json.JSONDecoder().raw_decode(value[start:])
    return decoded


def require(condition: bool, message: str):
    if not condition:
        raise PipelineError(message)


def command_exists(name: str):
    require(shutil.which(name) is not None, f"Required command is unavailable: {name}")


class Ledger:
    def __init__(self, output: pathlib.Path, request_id: str):
        self.output = output
        self.request_id = request_id
        self.rows = []
        self.journal = output / "stage-journal"
        self.logs = output / "provider-logs"
        self.journal.mkdir(parents=True, exist_ok=True)
        self.logs.mkdir(parents=True, exist_ok=True)

    def record(self, stage: str, provider: str, model: str, prompt: str, given, returned,
               started: float, command: list[str], usage=None, cost=None, assertions=None,
               attempt: int = 1, fallback_used: bool = False, enforce: bool = True):
        assertions = assertions or {"completed": True}
        row = {
            "schema": "famtastic.stage-journal.v2",
            "task_id": f"{self.request_id}:{len(self.rows) + 1:02d}",
            "order": len(self.rows) + 1,
            "stage": stage,
            "provider": provider,
            "model": model,
            "execution_class": "cloud_provider_executed" if provider not in {"deterministic", "playwright"} else "local_deterministic",
            "attempt": attempt,
            "fallback_used": fallback_used,
            "started_at": dt.datetime.fromtimestamp(started, dt.timezone.utc).isoformat(),
            "completed_at": now(),
            "duration_ms": max(1, round((time.time() - started) * 1000)),
            "asked_verbatim": prompt,
            "given_verbatim": given,
            "returned_verbatim": returned,
            "input_sha256": sha_bytes(canonical(given)),
            "output_sha256": sha_bytes(canonical(returned)),
            "command": command,
            "usage": usage or {"status": "provider_did_not_report"},
            "cost": cost or {"currency": "USD", "amount": None, "status": "provider_did_not_report_currency_cost"},
            "assertions": assertions,
            "status": "passed" if all(assertions.values()) else "failed"
        }
        dump(self.journal / f"{row['order']:02d}-{stage}-attempt-{attempt}.json", row)
        self.rows.append(row)
        if enforce:
            require(row["status"] == "passed", f"Stage assertions failed: {stage}")
        return row


def run_process(command: list[str], cwd: pathlib.Path, log: pathlib.Path, timeout: int = 1800, require_success: bool = True):
    started = time.time()
    process = subprocess.run(command, cwd=cwd, text=True, capture_output=True, timeout=timeout)
    log.write_text("COMMAND\n" + json.dumps(command) + "\n\nSTDOUT\n" + process.stdout + "\n\nSTDERR\n" + process.stderr)
    if require_success:
        require(process.returncode == 0, f"Provider command failed ({process.returncode}); see {log}")
    return process, started


def codex_json(stage: str, prompt: str, schema: pathlib.Path, output: pathlib.Path,
               ledger: Ledger, given, cwd: pathlib.Path, sandbox: str = "read-only", attempt: int = 1):
    response = output / f".{stage}-response-{attempt}.json"
    command = [
        "codex", "exec", "--ephemeral", "--ignore-rules", "--sandbox", sandbox,
        "-m", CODEX_MODEL, "-C", str(cwd), "--output-schema", str(schema),
        "-o", str(response), prompt
    ]
    process, started = run_process(command, cwd, ledger.logs / f"{stage}-attempt-{attempt}.log")
    returned = load(response)
    ledger.record(stage, "openai", CODEX_MODEL, prompt, given, returned, started, command,
                  assertions={"provider_exit_zero": True, "structured_output_valid": True}, attempt=attempt)
    return returned


def claude_review(prompt: str, output: pathlib.Path, ledger: Ledger, given, attempt: int):
    schema_value = load(SCHEMAS / "visual-review.v1.schema.json")
    schema_value.pop("$schema", None)
    schema = json.dumps(schema_value, separators=(",", ":"))
    command = [
        "claude", "-p", "--model", CLAUDE_MODEL, "--effort", "high",
        "--allowedTools", "Read", "--add-dir", str(output), "--json-schema", schema,
        "--output-format", "json", "--max-budget-usd", "8", prompt
    ]
    process, started = run_process(command, output, ledger.logs / f"visual-review-attempt-{attempt}.log")
    wrapper = first_json_object(process.stdout)
    result_value = wrapper.get("structured_output")
    if result_value is None:
        raw = wrapper.get("result", "")
        result_value = json.loads(raw) if isinstance(raw, str) else raw
    require(isinstance(result_value, dict), "Claude review did not return structured JSON")
    models = list((wrapper.get("modelUsage") or {}).keys())
    exact_model = models[0] if models else CLAUDE_MODEL
    amount = wrapper.get("total_cost_usd")
    usage = wrapper.get("usage") or wrapper.get("modelUsage") or {"status": "not_reported"}
    ledger.record("visual-review", "anthropic", exact_model, prompt, given, result_value, started, command,
                  usage=usage,
                  cost={"currency": "USD", "amount": amount, "status": "reported" if amount is not None else "not_reported"},
                  assertions={"provider_exit_zero": True, "structured_output_valid": True, "independent_provider": True},
                  attempt=attempt)
    dump(output / "visual-review.json", result_value)
    return result_value


def claude_json(stage: str, prompt: str, schema_path: pathlib.Path, output: pathlib.Path,
                ledger: Ledger, given, allowed_tools: str, attempt: int = 1):
    schema_value = load(schema_path)
    schema_value.pop("$schema", None)
    schema = json.dumps(schema_value, separators=(",", ":"))
    command = [
        "claude", "-p", "--model", CLAUDE_MODEL, "--effort", "high",
        "--allowedTools", allowed_tools, "--json-schema", schema,
        "--output-format", "json", "--max-budget-usd", "8", prompt
    ]
    process, started = run_process(command, output, ledger.logs / f"{stage}-attempt-{attempt}.log")
    wrapper = first_json_object(process.stdout)
    result_value = wrapper.get("structured_output")
    if result_value is None:
        raw = wrapper.get("result", "")
        result_value = json.loads(raw) if isinstance(raw, str) else raw
    require(isinstance(result_value, dict), f"Claude {stage} did not return structured JSON")
    models = list((wrapper.get("modelUsage") or {}).keys())
    exact_model = models[-1] if models else CLAUDE_MODEL
    amount = wrapper.get("total_cost_usd")
    ledger.record(stage, "anthropic", exact_model, prompt, given, result_value, started, command,
                  usage=wrapper.get("usage") or wrapper.get("modelUsage") or {"status": "not_reported"},
                  cost={"currency": "USD", "amount": amount, "status": "reported" if amount is not None else "not_reported"},
                  assertions={"provider_exit_zero": True, "structured_output_valid": True, "declared_tools": bool(allowed_tools)},
                  attempt=attempt)
    return result_value


def auth_probe(output: pathlib.Path, ledger: Ledger):
    command_exists("codex")
    command_exists("claude")
    command_exists("node")
    command_exists("python3")
    image_worker = ROOT / "gemini_flash_lite_image_worker.mjs"
    require(image_worker.is_file(), "FAMtastic image-only worker is missing")
    image_preflight_command = ["node", str(image_worker), "--preflight"]
    image_preflight, started = run_process(
        image_preflight_command, REPO, ledger.logs / "openai-image-auth.log", timeout=60
    )
    require(
        "GEMINI_FLASH_LITE_IMAGE_PREFLIGHT_AUTHENTICATED" in image_preflight.stdout,
        "Gemini Flash Lite Image preflight failed",
    )
    ledger.record(
        "provider-preflight-gemini-flash-lite-image", "google-gemini-api", "gemini-3.1-flash-lite-image",
        "Authenticate the local Gemini Flash Lite Image Keychain credential without generating an image.",
        {"keychain_service": "FAMtastic.Gemini.Image", "keychain_account": "famtastic-gemini-image-worker"},
        {"authenticated": True, "image_generated": False, "model_allowlist": ["gemini-3.1-flash-lite-image"]},
        started, image_preflight_command,
        usage={"image_requests": 0},
        cost={"currency": "USD", "amount": 0, "status": "no_image_generated"},
        assertions={"image_only_keychain_present": True, "gemini_flash_lite_authenticated": True, "no_image_generated": True},
    )
    prompt = "Return exactly AUTH_OK"
    codex_command = ["codex", "exec", "--ephemeral", "--ignore-rules", "--sandbox", "read-only", "-m", CODEX_MODEL, prompt]
    codex, started = run_process(codex_command, REPO, ledger.logs / "codex-auth.log", timeout=180)
    require("AUTH_OK" in codex.stdout + codex.stderr, "Codex authentication probe failed")
    ledger.record("provider-preflight-codex", "openai", CODEX_MODEL, prompt, {}, {"authenticated": True}, started, codex_command,
                  assertions={"command_present": True, "authenticated": True})
    claude_command = ["claude", "-p", "--model", CLAUDE_MODEL, "--output-format", "json", prompt]
    claude, started = run_process(claude_command, REPO, ledger.logs / "claude-auth.log", timeout=180)
    wrapper = first_json_object(claude.stdout)
    require("AUTH_OK" in str(wrapper.get("result", "")), "Claude authentication probe failed")
    exact = next(iter((wrapper.get("modelUsage") or {CLAUDE_MODEL: {}}).keys()))
    ledger.record("provider-preflight-claude", "anthropic", exact, prompt, {}, {"authenticated": True}, started, claude_command,
                  usage=wrapper.get("usage") or wrapper.get("modelUsage"),
                  cost={"currency": "USD", "amount": wrapper.get("total_cost_usd"), "status": "reported"},
                  assertions={"command_present": True, "authenticated": True})


def fetch_live_references(intake: dict, output: pathlib.Path, ledger: Ledger) -> dict:
    """Fetch intake references now so live research cannot be inferred from citations alone."""
    command_exists("curl")
    references = [item for item in intake.get("references", []) if isinstance(item, dict) and str(item.get("url", "")).startswith("https://")]
    require(len(references) >= 3, "Fresh live research requires at least three HTTPS reference sources")
    scratch = output / ".source-fetch"
    scratch.mkdir()
    started = time.time()
    results = []
    try:
        for index, item in enumerate(references, start=1):
            target = scratch / f"source-{index}.body"
            command = [
                "curl", "--location", "--silent", "--show-error", "--fail",
                "--max-time", "45", "--user-agent", "FAMtasticPreviewResearch/1.0",
                "--output", str(target), "--write-out", "%{http_code}\t%{content_type}",
                str(item["url"]),
            ]
            process = subprocess.run(command, cwd=REPO, text=True, capture_output=True, timeout=60)
            status_text, _, content_type = process.stdout.strip().partition("\t")
            status = int(status_text) if status_text.isdigit() else 0
            row = {
                "url": str(item["url"]),
                "intended_use": str(item.get("use", "")),
                "http_status": status,
                "content_type": content_type,
                "fetched": process.returncode == 0 and status >= 200 and status < 400 and target.is_file(),
                "error": process.stderr.strip()[:500],
            }
            if row["fetched"]:
                row["bytes"] = target.stat().st_size
                row["sha256"] = sha_file(target)
            results.append(row)
    finally:
        shutil.rmtree(scratch, ignore_errors=True)
    successful = [item for item in results if item["fetched"]]
    verification = {
        "schema": "famtastic.live-source-verification.v1",
        "request_id": intake["request_id"],
        "executed_at": now(),
        "provider": "https-curl",
        "sources": results,
        "successful_fetches": len(successful),
        "failed_fetches": len(results) - len(successful),
    }
    dump(output / "live-source-verification.json", verification)
    ledger.record(
        "live-source-fetch", "web-http", "curl",
        "Fetch every supplied HTTPS research reference at run time, record status, content type, byte count, and SHA-256, and fail closed unless at least three sources respond successfully.",
        {"references": references}, verification, started, ["curl", "<each intake reference>"],
        usage={"requests": len(results), "successful": len(successful)},
        cost={"currency": "USD", "amount": 0, "status": "no_model_provider_charge"},
        assertions={"three_or_more_live_sources": len(successful) >= 3, "request_identity_preserved": verification["request_id"] == intake["request_id"]},
    )
    return verification


def validate_directions(plan: dict, request_id: str):
    directions = plan["directions"]
    prompts = plan["image_prompts"]
    require(plan["request_id"] == request_id, "Creative plan changed request identity")
    require([item["id"] for item in directions] == [f"direction-{letter}" for letter in "abcdef"], "Direction IDs/order invalid")
    require(sum(item["mode"] == "restrained" for item in directions) == 1, "Exactly one restrained direction required")
    require(sum(item["mode"] == "medium_famtastic" for item in directions) == 1, "Exactly one medium direction required")
    require(sum(item["mode"] == "ultra_famtastic" for item in directions) == 4, "Exactly four ultra directions required")
    require(len({item["slug"] for item in directions}) == 6, "Direction slugs must be unique")
    require(len({item["information_architecture"] for item in directions}) == 6, "Information architectures must be unique")
    require([item["direction_id"] for item in prompts] == [f"direction-{letter}" for letter in "abcdef"], "Image prompts/order invalid")


def make_brief_and_architecture(output: pathlib.Path, intake: dict, research: dict, directions: list[dict]):
    brief = {
        "schema": "website_build_brief.v2",
        "request_id": intake["request_id"],
        "generated_at": now(),
        "classification": "fresh_provider_executed_heldout_benchmark",
        "customer": intake["customer"],
        "business": intake["business"],
        "audience": intake["audience"],
        "brand": intake["brand"],
        "scope": intake["scope"],
        "research_context": {
            "business_context": research["business_context"],
            "findings": research["findings"],
            "creative_opportunities": research["creative_opportunities"]
        },
        "direction_contracts": directions,
        "facts_requiring_confirmation": research["unknowns_requiring_client_confirmation"],
        "constraints": intake["constraints"],
        "commercial_boundary": {"sku": None, "price": None, "checkout_allowed": False},
        "publication_boundary": {"external_mutation_allowed": False, "site_studio_execution_claimed": False}
    }
    architecture = {
        "schema": "famtastic.solution-architecture.v2",
        "request_id": intake["request_id"],
        "package": {"status": "staff_scope_required", "sku": None, "price": None, "direct_checkout": False},
        "prototype": {"direction_count": 6, "selected_direction_limit": 2, "responsive_profiles": [1440, 390]},
        "future_capabilities": intake["scope"]["features"],
        "external_mutation_allowed": False,
        "site_studio_boundary": "Build packet only; success requires a real signed Site Studio return packet"
    }
    dump(output / "website-build-brief.v2.json", brief)
    dump(output / "architecture.json", architecture)


def stage_design_fonts(output: pathlib.Path):
    """Stage the repo's existing open webfont assets for local-only proof typography."""
    sources = {
        "inter-var.woff2": REPO / "backend/web/core/modules/navigation/assets/fonts/inter-var.woff2",
        "metropolis-regular.woff2": REPO / "backend/web/core/themes/olivero/fonts/metropolis/Metropolis-Regular.woff2",
        "metropolis-bold.woff2": REPO / "backend/web/core/themes/olivero/fonts/metropolis/Metropolis-Bold.woff2",
        "lora-regular.woff2": REPO / "backend/web/core/themes/olivero/fonts/lora/lora-v14-latin-regular.woff2",
        "lora-italic.woff2": REPO / "backend/web/core/themes/olivero/fonts/lora/lora-v14-latin-italic.woff2",
        "lora-bold.woff2": REPO / "backend/web/core/themes/olivero/fonts/lora/lora-v14-latin-700.woff2",
        "scope-one.woff2": REPO / "backend/web/core/profiles/demo_umami/themes/umami/fonts/scope-one-v14-latin-regular.woff2",
    }
    destination = output / "_design-assets/fonts"
    destination.mkdir(parents=True, exist_ok=True)
    for name, source in sources.items():
        require(source.is_file(), f"Missing staged design font: {source}")
        shutil.copy2(source, destination / name)


def write_guided_review_hub(output: pathlib.Path, intake: dict, directions: list[dict], prompts: list[dict]):
    """Write the deterministic, plain-language entry point for six concepts.

    A construction worker still builds every individual site, but the review
    hub is product UX, not an artistic variable. People must understand that
    they received six separate homepage directions, how to compare them, and
    what feedback to give without mistaking a prototype link for a purchase or
    launch action.
    """
    prompt_by_direction = {item["direction_id"]: item for item in prompts}
    business_name = html.escape(str(intake.get("business", {}).get("name") or "your project"), quote=True)
    mode_labels = {
        "restrained": "A · familiar & credible",
        "medium_famtastic": "B · elevated signature direction",
        "ultra_famtastic": "Ultra FAMtastic · expressive concept",
    }
    cards = []
    for number, direction in enumerate(directions, 1):
        prompt = prompt_by_direction[direction["id"]]
        name = html.escape(str(direction["name"]), quote=True)
        strategy = html.escape(str(direction["strategy"]), quote=True)
        entry = html.escape(f"{direction['slug']}/index.html", quote=True)
        alt = html.escape(str(prompt["alt_text"]), quote=True)
        mode = html.escape(mode_labels[direction["mode"]], quote=True)
        level = int(direction["famtastic_level"])
        cards.append(f'''<article class="concept-card" data-proof-direction="{html.escape(direction["id"], quote=True)}">
  <img src="{entry.rsplit('/', 1)[0]}/assets/hero.png" alt="{alt}">
  <div class="concept-card__body">
    <p class="concept-card__label">Concept {number:02d} · {mode} · FAMtastic {level}/10</p>
    <h2>{name}</h2>
    <p>{strategy}</p>
    <a class="concept-card__link" href="{entry}" target="_blank" rel="noopener noreferrer">Open this complete website <span aria-hidden="true">↗</span></a>
  </div>
</article>''')
    cards_html = "\n".join(cards)
    page = f'''<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Compare six website concepts · FAMtastic Designs</title>
  <style>
    :root {{ color-scheme: dark; --ink:#f7f2e8; --muted:#c8c1b4; --night:#10231a; --deep:#07110c; --green:#b9ea68; --orange:#f28e39; --line:rgba(247,242,232,.18); }}
    * {{ box-sizing:border-box; }} html {{ background:var(--deep); }} body {{ margin:0;background:radial-gradient(circle at 80% 0,rgba(185,234,104,.16),transparent 28rem),var(--deep);color:var(--ink);font:400 16px/1.55 Arial,Helvetica,sans-serif; }}
    a {{ color:inherit; }} .shell {{ width:min(1160px,calc(100% - 32px));margin:auto; }}
    header {{ padding:56px 0 28px; border-bottom:1px solid var(--line); }} .eyebrow,.concept-card__label {{ margin:0;color:var(--green);font:800 12px/1.2 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.12em;text-transform:uppercase; }}
    h1,h2 {{ margin:0;font-family:Impact,"Arial Black",Arial,sans-serif;letter-spacing:-.045em;line-height:.92;text-transform:uppercase; }} h1 {{ max-width:820px;margin-top:14px;font-size:clamp(3rem,7vw,6.5rem); }} h1 em {{ color:var(--orange);font-family:Georgia,serif;font-weight:400;text-transform:none; }}
    .intro {{ max-width:720px;margin:22px 0 0;color:var(--muted);font-size:clamp(1rem,1.6vw,1.2rem); }}
    .steps {{ display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;margin:28px 0 0;background:var(--line);border:1px solid var(--line); }} .step {{ min-height:118px;padding:17px;background:var(--night); }} .step b {{ display:block;color:var(--orange);font:800 13px/1 ui-monospace,SFMono-Regular,Menlo,monospace; }} .step strong {{ display:block;margin:8px 0 4px;font-size:18px; }} .step span {{ color:var(--muted);font-size:14px; }}
    main {{ padding:42px 0 58px; }} .review-note {{ display:flex;gap:12px;align-items:flex-start;margin:0 0 26px;padding:18px;border-left:4px solid var(--orange);background:rgba(242,142,57,.1); }} .review-note strong {{ display:block;margin-bottom:3px; }} .review-note p {{ margin:0;color:var(--muted); }}
    .grid {{ display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px; }} .concept-card {{ overflow:hidden;border:1px solid var(--line);background:#10231a;box-shadow:0 20px 44px rgba(0,0,0,.18); }} .concept-card img {{ display:block;width:100%;aspect-ratio:16/8;object-fit:cover;background:#1b3125; }} .concept-card__body {{ padding:20px; }} .concept-card h2 {{ margin:9px 0 11px;font-size:clamp(2rem,4vw,3.25rem); }} .concept-card__body>p:not(.concept-card__label) {{ min-height:72px;margin:0;color:var(--muted); }}
    .concept-card__link {{ display:inline-flex;gap:8px;align-items:center;min-height:44px;margin-top:18px;padding:11px 14px;background:var(--green);color:#10231a;font-weight:800;text-decoration:none; }} .concept-card__link:hover,.concept-card__link:focus-visible {{ background:#fff0c9;outline:3px solid var(--orange);outline-offset:3px; }}
    footer {{ padding:26px 0 46px;border-top:1px solid var(--line);color:var(--muted); }} footer strong {{ color:var(--ink); }}
    @media (max-width:700px) {{ header {{ padding-top:34px; }} .steps,.grid {{ grid-template-columns:1fr; }} .step {{ min-height:auto; }} .concept-card__body>p:not(.concept-card__label) {{ min-height:0; }} }}
  </style>
</head>
<body>
  <header><div class="shell">
    <p class="eyebrow">FAMtastic Designs · concept comparison</p>
    <h1>Six ways to make <em>{business_name}</em> unmistakable.</h1>
    <p class="intro">These are six separate, complete homepage concepts—not six links you need to decode. Each opens in a new tab so you can compare the experience, then come right back here.</p>
    <section class="steps" aria-label="How to review the concepts" data-review-guide>
      <div class="step"><b>01</b><strong>Open a concept</strong><span>Start with whatever catches your eye.</span></div>
      <div class="step"><b>02</b><strong>Compare the feeling</strong><span>Look at the message, layout, energy, and usability—not only colors.</span></div>
      <div class="step"><b>03</b><strong>Shortlist one or two</strong><span>Tell us the concept names and what you would combine or change.</span></div>
    </section>
  </div></header>
  <main class="shell" id="compare">
    <aside class="review-note"><div aria-hidden="true">✦</div><div><strong>You do not have to choose all six.</strong><p>Open the six concepts, narrow them to one or two favorites, and use the project conversation or portal to share your direction. This is a concept proof, not a live website or checkout. Details still need confirmation.</p></div></aside>
    <section class="grid" aria-label="Six separate website concepts">{cards_html}</section>
  </main>
  <footer><div class="shell"><strong>What happens next:</strong> Select a direction, identify the pieces you want to keep, and we refine the chosen direction before any real launch decision. Concept proof only; no purchase or publishing occurs from this page.</div></footer>
</body>
</html>'''
    (output / "index.html").write_text(page)


def build_manifest(output: pathlib.Path, intake: dict, directions: list[dict], prompts: list[dict]):
    rows = []
    for direction, image_prompt in zip(directions, prompts):
        entry = pathlib.Path(direction["slug"]) / "index.html"
        html_path = output / entry
        art_path = output / direction["slug"] / "assets" / "hero.png"
        require(html_path.is_file(), f"Missing built page: {html_path}")
        require(art_path.is_file(), f"Missing built art: {art_path}")
        rows.append({
            **direction,
            "entry": entry.as_posix(),
            "hero_alt": image_prompt["alt_text"],
            "html_sha256": sha_file(html_path),
            "hero_sha256": sha_file(art_path)
        })
    require((output / "index.html").is_file(), "Missing review hub")
    require(len({row["html_sha256"] for row in rows}) == 6, "HTML outputs are not unique")
    require(len({row["hero_sha256"] for row in rows}) == 6, "Artwork outputs are not unique")
    manifest = {
        "schema": "famtastic.website-showcase-manifest.v2",
        "request_id": intake["request_id"],
        "customer_email": intake["customer"]["email"],
        "classification": "locally_proven_fresh_provider_run",
        "fresh_non_replay": True,
        "direction_count": 6,
        "directions": rows
    }
    dump(output / "manifest.json", manifest)
    return manifest


def prior_hashes(output: pathlib.Path):
    hashes = set()
    root = REPO / "artifacts" / "website-delivery-swarm"
    if not root.is_dir():
        return hashes
    for path in root.rglob("*"):
        if output in path.parents or not path.is_file():
            continue
        if path.name == "hero.png" or path.suffix.lower() in {".html", ".png"}:
            try:
                hashes.add(sha_file(path))
            except OSError:
                pass
    return hashes


def visual_pass(review: dict):
    return (
        review.get("release_decision") == "pass"
        and review.get("repair_required") is False
        and review.get("all_six_visually_distinct") is True
        and review.get("three_or_more_distinct_layout_families") is True
        and not review.get("critical_defects")
        and len(review.get("directions", [])) == 6
        and all(item.get("overall", 0) >= 8 and all(score >= 7 for score in item.get("scores", {}).values()) for item in review["directions"])
    )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--intake", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--packet-output", required=True)
    parser.add_argument("--select", default="direction-e,direction-f")
    parser.add_argument("--max-repairs", type=int, default=1)
    parser.add_argument("--image-max-cost-usd", type=float, required=True,
                        help="Declared ceiling for the Gemini Flash Lite 1K preview-art calls; the worker fails before generation if it is too small.")
    args = parser.parse_args()
    require(0 <= args.max_repairs <= 1,
            "Preview release permits zero or one consolidated repair cycle")
    intake_path = pathlib.Path(args.intake).resolve()
    output = pathlib.Path(args.output).resolve()
    packet_output = pathlib.Path(args.packet_output).resolve()
    require(not output.exists(), f"Fresh output must not already exist: {output}")
    require(not packet_output.exists(), f"Packet output must not already exist: {packet_output}")
    output.mkdir(parents=True)
    intake = load(intake_path)
    request_id = intake["request_id"]
    run_id = f"fresh-{uuid.uuid4()}"
    dump(output / "intake.json", intake)
    ledger = Ledger(output, request_id)
    previous = prior_hashes(output)

    auth_probe(output, ledger)
    live_sources = fetch_live_references(intake, output, ledger)

    research_prompt = f"""You are the live-research stage for FAMtastic website.preview.v2.
Use web search now. Begin with the independently fetched source-verification record, then research the locality, organization context, accessibility expectations, and membership/conversion context relevant to the supplied fictional intake. Prefer official university, government, accessibility, and primary sources. Do not invent business facts, prices, reviews, permits, availability, claims, or domain results. Every finding needs a direct HTTPS source URL and a design-use explanation. Mutable facts must be marked mutable. Return only the JSON required by the supplied schema, with request_id exactly {request_id}.

INTAKE VERBATIM:
{json.dumps(intake, indent=2)}

LIVE SOURCE VERIFICATION VERBATIM:
{json.dumps(live_sources, indent=2)}
"""
    research = claude_json("live-research", research_prompt, SCHEMAS / "research.v1.schema.json", output, ledger,
                           {"intake": intake, "live_sources": live_sources}, "WebSearch,WebFetch")
    require(research["request_id"] == request_id, "Research changed request identity")
    require(len({item["source_url"] for item in research["findings"]}) >= 6, "Research requires at least six distinct sources")
    dump(output / "research.json", research)

    direction_prompt = f"""You are the creative-director stage for FAMtastic website.preview.v2. Create exactly six genuinely different complete website directions from the intake and live research below: direction-a restrained (FAMtastic 2), direction-b medium_famtastic (FAMtastic 6), and direction-c through direction-f ultra_famtastic (levels 9 or 10). They must differ in information architecture, hero composition, typography concept, section rhythm, visual metaphor, surface treatment, and conversion path—not just color. Every visual_system must explicitly name (1) a display-typography composition technique beyond bold/color/italic, such as outlined, layered, interlocked, condensed-versus-serif, vertical, kinetic, dimensional, or editorial type; (2) a subject-native pattern or texture system applied to backgrounds and containers; and (3) a depth system using borders, embossing, shadows, translucent layers, material edges, or lighting. Subject-native symbols from the intake are required when legally safe; an original transformation is preferred over a copied official mark. Reuse of proven components, grids, and interaction patterns is allowed when adapted to this subject; wholesale cloning of a prior site is not. All six must remain usable, credible, mobile-first websites rather than posters. The four ultra directions must escalate visibly from C through F and be bold enough to become portfolio benchmarks. Generate one original wide hero-art prompt per direction; no embedded words, watermark, deceptive official identity, or copied brand style. Return only schema-valid JSON and preserve request_id exactly {request_id}.

INTAKE VERBATIM:
{json.dumps(intake, indent=2)}

RESEARCH VERBATIM:
{json.dumps(research, indent=2)}
"""
    plan = codex_json("creative-direction", direction_prompt, SCHEMAS / "directions.v1.schema.json", output, ledger,
                      {"intake": intake, "research": research}, REPO)
    validate_directions(plan, request_id)
    directions = plan["directions"]
    prompts = plan["image_prompts"]
    dump(output / "directions.json", directions)
    dump(output / "image-prompts.json", prompts)
    make_brief_and_architecture(output, intake, research, directions)
    stage_design_fonts(output)

    artwork_dir = output / "generated-artwork"
    image_worker = ROOT / "gemini_flash_lite_image_worker.mjs"
    image_command = [
        "node", str(image_worker),
        "--prompts", str(output / "image-prompts.json"),
        "--output", str(artwork_dir),
        "--request-id", request_id,
        "--execute",
        "--max-cost-usd", f"{args.image_max_cost_usd:.3f}",
    ]
    image_process, started = run_process(
        image_command, output, ledger.logs / "visual-art-attempt-1.log", timeout=1200
    )
    image_receipt = load(artwork_dir / "generation-receipt.json")
    require(image_receipt.get("status") == "complete", "Gemini image worker did not complete")
    require(image_receipt.get("request_id") == request_id, "Image worker changed request identity")
    require(image_receipt.get("model") == "gemini-3.1-flash-lite-image", "Image worker resolved an unapproved model")
    require(image_receipt.get("estimated_cost_usd", 0) <= args.image_max_cost_usd,
            "Image worker exceeded the declared cost ceiling")
    ledger.record(
        "visual-art", "google-gemini-api", "gemini-3.1-flash-lite-image",
        "Generate exactly one original 1K hero per direction through the Gemini Flash Lite Image worker. Preserve the supplied prompt text, enforce no-text/no-logo/no-watermark constraints, and record a receipt.",
        {"image_prompts": prompts, "max_cost_usd": args.image_max_cost_usd},
        image_receipt, started, image_command,
        usage={"image_requests": len(prompts), "provider_usage": image_receipt.get("artifacts", [])},
        cost={"currency": "USD", "amount": image_receipt.get("estimated_cost_usd"), "status": "estimated_pending_provider_reconciliation"},
        assertions={
            "gemini_image_endpoint": image_receipt.get("api") == "generateContent",
            "gemini_flash_lite_only": image_receipt.get("model") == "gemini-3.1-flash-lite-image",
            "within_declared_ceiling": image_receipt.get("estimated_cost_usd", 0) <= args.image_max_cost_usd,
            "all_requested_artifacts_returned": len(image_receipt.get("artifacts", [])) == len(prompts),
        },
    )
    for item in prompts:
        path = artwork_dir / item["filename"]
        require(path.is_file() and path.stat().st_size > 10000, f"Missing generated artwork: {path}")
    require(len({sha_file(path) for path in artwork_dir.iterdir() if path.is_file()}) == 6, "Generated artwork is not unique")

    build_prompt = f"""You are the prototype-construction worker for FAMtastic request {request_id}. Read intake.json, research.json, directions.json, image-prompts.json, architecture.json, website-build-brief.v2.json, and the local font assets under _design-assets/fonts/. Build all six website directions in separate directories named exactly by each direction slug. Copy that direction's generated-artwork/direction-X.png to SLUG/assets/hero.png and copy only the local WOFF2 fonts that direction actually uses into SLUG/assets/fonts/. Each SLUG/index.html must be a polished, complete, responsive single-page website with its own visual system and information architecture, at least five substantive main sections, one H1, semantic navigation, credible business-specific draft copy, local functional anchors, accessible alt text, visible focus states, and a clear inquiry path. Typography is a designed visual layer: use @font-face plus deliberate composition such as outlined/layered/interlocked/vertical/editorial type, not merely generic bold sans text. Every direction must visibly apply its own subject-native pattern or texture to backgrounds or containers and use depth through material edges, shadows, lighting, overlays, embossing, or layered surfaces. Implement the symbolic vocabulary required by the intake through original graphics or CSS motifs without falsely presenting an official mark. The restrained, medium, and four ultra directions must look structurally different, and C through F must escalate in spectacle and conceptual ambition. Proven component-level patterns may be adapted; do not wholesale clone a prior site. Create a responsive index.html review hub linking all six. Use only local HTML/CSS and optional local inline JavaScript; no external resources, submitting forms, inline event-handler attributes, trackers, remote fonts, iframes, or hotlinks. All select controls must begin with a neutral disabled placeholder; horizontal rails must expose every item with keyboard-operable labeled controls; no heading may split a word; no text or controls may overlap; normal text contrast must meet 4.5:1 and large text 3:1, including text placed over gradients. Every page and hub must visibly disclose this is a fictional concept and details require owner confirmation. Do not create manifest.json; the deterministic runner owns hashes and manifest truth. Return schema-valid completion JSON listing the hub and six site entries."""
    build_status = codex_json("prototype-construction", build_prompt, SCHEMAS / "worker-status.v1.schema.json", output, ledger,
                              {"brief_sha256": sha_file(output / "website-build-brief.v2.json"), "directions": directions},
                              output, sandbox="workspace-write")
    require(build_status["request_id"] == request_id, "Builder changed request identity")
    write_guided_review_hub(output, intake, directions, prompts)
    manifest = build_manifest(output, intake, directions, prompts)

    qa_attempt = 1
    while True:
        qa_command = ["node", str(ROOT / "provider-browser-qa.mjs"), str(output)]
        process, started = run_process(qa_command, REPO, ledger.logs / f"browser-qa-attempt-{qa_attempt}.log", require_success=False)
        qa = load(output / "browser-results.json")
        ledger.record("browser-qa", "playwright", "chromium", "Render all six sites and the review hub at 1440px and 390px and fail closed on technical defects.",
                      manifest, qa, started, qa_command, assertions=qa["assertions"], attempt=qa_attempt, enforce=False)
        if qa["passed"] is True:
            break
        require(qa_attempt <= args.max_repairs, "Browser QA still fails after bounded technical repairs")
        technical_repair_prompt = f"""You are the technical repair worker for request {request_id}. Read browser-results.json in the current directory. Inspect only failed route/profile results and the corresponding site files. Fix every reported technical defect, especially overflow, broken anchors/assets, semantic failures, or console errors. Preserve the direction's creative identity, content, generated art, fictional disclosure, local-only resource boundary, and exact 1/1/4 mix. Do not modify sites that passed unless a shared defect requires it. Return schema-valid completion JSON listing modified files."""
        codex_json("technical-repair", technical_repair_prompt, SCHEMAS / "worker-status.v1.schema.json", output, ledger,
                   {"failed_results": {key: value for key, value in qa["results"].items() if not value["passed"]}},
                   output, sandbox="workspace-write", attempt=qa_attempt)
        write_guided_review_hub(output, intake, directions, prompts)
        manifest = build_manifest(output, intake, directions, prompts)
        qa_attempt += 1

    review = None
    for attempt in range(1, args.max_repairs + 2):
        review_prompt = f"""You are the independent release reviewer. You did not create this work. Inspect the desktop and mobile contact sheets at {output / 'screenshots/review-contact-sheet-desktop.png'} and {output / 'screenshots/review-contact-sheet-mobile.png'} first; open an individual direction screenshot only when the contact sheet reveals a possible defect. Read directions.json only to understand intended distinctions. Score each direction from 0-10 on impact, business_relevance, visual_distinction, copy_specificity, trust, mobile_usability, accessibility, conversion_clarity, and emotional_response. Require every dimension >=7, every overall >=8, no critical defect, all six visibly distinct, at least three layout families, and a clear increase in ambition from C through F. Reject any direction whose typography is only generic bold/color/italic treatment, whose surfaces lack visible subject-native pattern/texture/depth, whose symbolism could fit an unrelated business, or whose layout is merely a recolored template. Also reject poster-like pages, clipped mobile layouts, weak copy, illegible contrast, pristine-form failures, inaccessible rails, or unclear conversion. If anything fails, set repair_required true, release_decision repair, and consolidate all exact actionable repair instructions into one pass. Set reviewer.provider anthropic, reviewer.model to the exact model you are running, independent true, and execution_class cloud_provider_executed. Preserve request_id exactly {request_id}. Return only schema-valid JSON."""
        review = claude_review(review_prompt, output, ledger,
                               {"contact_sheet_sha256": {item["file"]: item["sha256"] for item in qa.get("review_contact_sheets", [])}, "directions": directions}, attempt)
        require(review["request_id"] == request_id, "Reviewer changed request identity")
        if visual_pass(review):
            break
        require(attempt <= args.max_repairs, "Independent visual gate still fails after bounded repairs")
        reviewed_screenshot_hashes = {item["file"]: item["sha256"] for item in qa["screenshots"]}
        repair_prompt = f"""You are the prototype-repair worker for request {request_id}. Read visual-review.json and browser-results.json in the current directory, then inspect the affected site source files. Apply every actionable repair instruction while preserving business facts, direction identity, local-only assets, fictional disclosure, and the exact 1/1/4 creative mix. Do not weaken a bold direction into a generic template. Do not touch unflagged sites unless needed for a shared critical defect. Do not use external resources or claim customer approval. Return schema-valid completion JSON listing modified files."""
        codex_json("prototype-repair", repair_prompt, SCHEMAS / "worker-status.v1.schema.json", output, ledger,
                   {"visual_review": review, "browser_results": qa}, output, sandbox="workspace-write", attempt=attempt)
        write_guided_review_hub(output, intake, directions, prompts)
        manifest = build_manifest(output, intake, directions, prompts)
        qa_attempt += 1
        qa_command = ["node", str(ROOT / "provider-browser-qa.mjs"), str(output)]
        process, started = run_process(qa_command, REPO, ledger.logs / f"browser-qa-attempt-{qa_attempt}.log", require_success=False)
        qa = load(output / "browser-results.json")
        ledger.record("browser-qa", "playwright", "chromium", "Re-render repaired sites at 1440px and 390px and fail closed on technical defects.",
                      manifest, qa, started, qa_command, assertions=qa["assertions"], attempt=qa_attempt, enforce=False)
        require(qa["passed"] is True, "Visual repair introduced a technical browser defect")
        repaired_screenshot_hashes = {item["file"]: item["sha256"] for item in qa["screenshots"]}
        require(repaired_screenshot_hashes != reviewed_screenshot_hashes,
                "Repair did not change rendered artifacts; another reviewer call is not allowed")

    require(review is not None and visual_pass(review), "Independent visual release gate did not pass")
    new_hashes = {row["html_sha256"] for row in manifest["directions"]} | {row["hero_sha256"] for row in manifest["directions"]}
    overlap = sorted(new_hashes & previous)
    assertions = {
        "fresh_non_replay_no_prior_html_or_art_hashes": len(overlap) == 0,
        "request_identity_preserved": manifest["request_id"] == request_id,
        "customer_identity_preserved": manifest["customer_email"].lower() == intake["customer"]["email"].lower(),
        "live_research_provider_executed": live_sources["successful_fetches"] >= 3 and len(research["findings"]) >= 6,
        "exact_six_directions": len(directions) == 6,
        "exact_creative_mix": sum(item["mode"] == "restrained" for item in directions) == 1 and sum(item["mode"] == "medium_famtastic" for item in directions) == 1 and sum(item["mode"] == "ultra_famtastic" for item in directions) == 4,
        "six_unique_html_hashes": len({row["html_sha256"] for row in manifest["directions"]}) == 6,
        "six_unique_generated_art_hashes": len({row["hero_sha256"] for row in manifest["directions"]}) == 6,
        "browser_qa_passed": qa["passed"] is True,
        "twelve_direction_screenshots": len([item for item in qa["screenshots"] if item["route"] != "review"]) == 12,
        "independent_provider_review": review["reviewer"]["provider"] == "anthropic" and review["reviewer"]["independent"] is True,
        "visual_release_gate_passed": visual_pass(review),
        "commercial_and_external_mutations_forbidden": load(output / "architecture.json")["external_mutation_allowed"] is False,
        "site_studio_execution_not_claimed": load(output / "website-build-brief.v2.json")["publication_boundary"]["site_studio_execution_claimed"] is False
    }
    require(all(assertions.values()), f"Final evidence assertions failed: {[key for key, value in assertions.items() if not value]}")
    visual_assertions = {
        "independent_reviewer": True,
        "no_critical_defects": not review["critical_defects"],
        "every_overall_at_least_eight": all(item["overall"] >= 8 for item in review["directions"]),
        "no_dimension_below_seven": all(all(score >= 7 for score in item["scores"].values()) for item in review["directions"]),
        "all_six_visually_distinct": review["all_six_visually_distinct"],
        "three_or_more_distinct_layout_families": review["three_or_more_distinct_layout_families"]
    }
    dump(output / "agent-ledger.json", ledger.rows)
    dump(output / "quality-report.json", {"schema": "famtastic.quality-report.v2", "technical": qa["assertions"], "visual": review, "visual_assertions": visual_assertions})
    evidence = {
        "schema": "famtastic.fresh-provider-preview-evidence.v1",
        "generated_at": now(),
        "run_id": run_id,
        "classification": "locally_proven_fresh_provider_run",
        "request_id": request_id,
        "customer": {"email": intake["customer"]["email"], "notification_sent": False},
        "assertions": assertions,
        "prior_hash_overlap": overlap,
        "directions": manifest["directions"],
        "screenshots": qa["screenshots"],
        "stage_journals": [path.relative_to(output).as_posix() for path in sorted((output / "stage-journal").glob("*.json"))],
        "provider_models": sorted({f"{row['provider']}:{row['model']}" for row in ledger.rows}),
        "live_source_verification": live_sources,
        "unresolved_external_gates": [
            "Site Studio has not consumed this packet or returned a real signed success packet",
            "No Drupal import, portal update, notification, customer approval, payment, domain, or production deployment was performed",
            "Business facts, menu, operations, policies, permits, pricing, availability, and identity require a real owner"
        ]
    }
    dump(output / "evidence.json", evidence)
    (output / "run-report.md").write_text(
        f"# Fresh provider-executed preview run\n\n- Request: `{request_id}`\n- Run: `{run_id}`\n- Classification: locally proven fresh provider run\n- Six new websites: yes\n- Six new generated hero images: yes\n- Screenshots: 12 direction captures plus review hub desktop/mobile\n- Independent visual release: pass\n- Site Studio execution: not claimed\n- External mutations: none\n"
    )

    env = os.environ.copy()
    env.update({
        "FAMTASTIC_CAPABILITY_WEB_RESEARCH": "1",
        "FAMTASTIC_CAPABILITY_MANAGED_IMAGE_GENERATION": "1",
        "FAMTASTIC_CAPABILITY_INDEPENDENT_VISION": "1",
        "FAMTASTIC_PROVIDER_BALANCED_REASONING_AUTH": "1",
        "FAMTASTIC_PROVIDER_BALANCED_CODE_AUTH": "1"
    })
    packet_command = [
        sys.executable, str(ROOT / "autonomous_pipeline.py"), "prepare",
        "--artifact", str(output), "--intake", str(output / "intake.json"),
        "--output", str(packet_output), "--select", args.select,
        "--build-class", "premium", "--project-id", f"project:{request_id}"
    ]
    started = time.time()
    packet = subprocess.run(packet_command, cwd=REPO, env=env, text=True, capture_output=True, timeout=900)
    (output / "provider-logs" / "site-studio-packet.log").write_text(packet.stdout + "\n" + packet.stderr)
    require(packet.returncode == 0, f"Site Studio packet preparation failed; see {output / 'provider-logs/site-studio-packet.log'}")
    packet_json = load(packet_output / "site-studio-build-packet.json")
    require(packet_json["classification"] == "provider_executed", "Packet must be provider_executed")
    require(packet_json["request_id"] == request_id, "Packet request identity mismatch")
    require(packet_json["selected_direction_ids"] == [item.strip() for item in args.select.split(",")], "Packet selection mismatch")
    ledger.record("site-studio-packet", "deterministic", "autonomous_pipeline.py", "Validate the fresh artifact and create an immutable Site Studio build packet without invoking Site Studio.",
                  {"artifact_evidence_sha256": sha_file(output / "evidence.json")},
                  {"packet_id": packet_json["packet_id"], "packet_sha256": sha_file(packet_output / "site-studio-build-packet.json")},
                  started, packet_command,
                  assertions={"packet_created": True, "provider_executed_classification": True, "site_studio_not_invoked": True})
    dump(output / "agent-ledger.json", ledger.rows)
    evidence["site_studio_build_packet"] = {
        "packet_id": packet_json["packet_id"],
        "path": str(packet_output / "site-studio-build-packet.json"),
        "zip_path": str(packet_output / "site-studio-build-packet.zip"),
        "sha256": sha_file(packet_output / "site-studio-build-packet.json"),
        "classification": packet_json["classification"],
        "site_studio_executed": False
    }
    evidence["stage_journals"] = [path.relative_to(output).as_posix() for path in sorted((output / "stage-journal").glob("*.json"))]
    evidence["provider_models"] = sorted({f"{row['provider']}:{row['model']}" for row in ledger.rows})
    dump(output / "evidence.json", evidence)
    print("PASS: fresh provider-executed six-direction preview")
    print(f"Review: {output / 'index.html'}")
    print(f"Evidence: {output / 'evidence.json'}")
    print(f"Build packet: {packet_output / 'site-studio-build-packet.zip'}")
    print("BOUNDARY: Site Studio was not invoked; no success packet is claimed")


if __name__ == "__main__":
    try:
        main()
    except (PipelineError, subprocess.TimeoutExpired, json.JSONDecodeError) as error:
        print(f"FAIL: {error}", file=sys.stderr)
        sys.exit(1)
