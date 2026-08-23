# B1 — Inbound Intent Classification Rules

**Recipe**: `docs/playbook/RECIPES/AUTONOMOUS_CUSTOMER_SERVICE.md` step B1
**Owner**: Support Triage agent (`@fam-support-triage`)
**Created**: 2026-08-23
**Implementation**: `backend/web/modules/custom/famtastic_pipeline/src/Service/SupportIntentClassifier.php`

## Contract

Every inbound message (`famtastic_inbound_message`) is classified into exactly
one of five intents: **status, revision, billing, technical, other**. The
result always carries a confidence score and matched signal ids. Classification
is deterministic — same input, same output — so evidence is reproducible.

Result shape:

```json
{"intent": "billing", "confidence": 0.67, "signals": ["b_payment@body"], "escalate": false}
```

## Confidence model

- Each rule hit adds its weight (see rule table) to one intent's score.
- Subject hits count **double**; body hits count single.
- **Interrogative bias**: if the combined message is phrased as a question
  (contains `?` or starts with when/how/is/are/did/does/do/can you tell), and
  status already has at least one signal, status gains +1. Questions ask about
  state; they rarely commission new work.
- `confidence = top / (top + second + 1)` — a margin-aware share that stays
  low for single weak hits and near-ties.
- **Escalation threshold: 0.6.** Below it — and every `other` fallback with no
  signals at all — the message MUST go to the human draft queue. Never guess.

Tie-break order once bias has applied: revision > billing > technical > status.
Rationale: actionable work outranks informational asks.

## Rule table (ids are stable; they appear in evidence)

| Id | Intent | Matches (case-insensitive) | Weight |
|---|---|---|---|
| r_change_verb | revision | please change/revise/redo/tweak/adjust/swap/replace/fix up | 2 |
| r_revision_noun | revision | revision(s), new version, another round/version, one more round/edit | 2 |
| r_add_remove | revision | add/remove/take off/put back/delete … logo/page/section/photo/image/picture/text/link/form/map/hours/button/color/colour | 2 |
| r_want_different | revision | I'd/would/want/need … different/changed/updated/edited | 1 |
| b_invoice | billing | invoice, receipt, statement | 2 |
| b_refund | billing | refund, chargeback, money back | 2 |
| b_payment | billing | pay/payment/paid/pays, billed/billing, charged, my card, credit card | 2 |
| b_cost_ask | billing | price, cost, renewal, subscription fee | 1 |
| t_broken | technical | broken, not working, doesn't work, crashed, white screen, down | 2 |
| t_error | technical | error, error message, 404, 500 error, fatal | 2 |
| t_access | technical | can't/cannot/unable to log in/sign in/access, locked out, password reset | 2 |
| t_perf | technical | slow, loading forever, times out, timeout, hanging | 1 |
| s_when | status | when will/can/do you/are you, how long, ETA, timeline, deadline | 2 |
| s_progress | status | status, progress, any news, (an) update on, how's it going, is it done/ready/live, ready yet, still waiting | 2 |

Deliberate exclusions:
- "update" is NOT a change verb (it would collide with "any update on…" status
  language). Revision phrasing must use change/revise/add/remove-family words.
- "renews" alone is not billing evidence; explicit money words are required.

## Validation

- Corpus: `backend/tests/fixtures/support-intent-labeled.json` — 27 labeled
  messages (synthetic-realistic, including adversarial overlaps like "when will
  the logo change be done?" and dual-issue messages).
- Validator: `scripts/e2e-intent-classifier.sh` → per-intent accuracy,
  safe-route rate (correct OR escalated), escalation count, full per-message
  results. Pass gate: accuracy ≥ 90% AND safe-route rate = 100%.
- Unit tests: `tests/src/Unit/SupportIntentClassifierTest.php`.
- Latest evidence: `.artifacts/support-triage/<run-id>/evidence.json`
  (schema `famtastic.support-intent-b1.v1`).

## Known gap (honest)

The corpus is synthetic-realistic, not real customer history. Real historical
messages live in the production database/Maildir and require Fritz-executed
commands (never autonomous). When approved, export ≥20 real rows:

```bash
ssh "$FAMTASTIC_SSH_TARGET" \
  '$HOME/deploy/famtastic-designs/backend/vendor/bin/drush -r $HOME/public_html \
   sqlq "SELECT subject, body FROM famtastic_inbound_message ORDER BY id LIMIT 100"' \
  > .artifacts/support-triage/prod-export.jsonl
```

then label them and re-run the validator with the real corpus before flipping
B1 to DONE.
