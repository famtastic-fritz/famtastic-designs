#!/usr/bin/env bash
# FAMtastic Designs — T4 Wave 0 synthetic campaign acceptance.
# Acceptance criteria (STRATEGY-PRICING.md): five internal/synthetic leads
# through the own pipeline incl. mail receipts; ZERO dead letters; selection
# notifications fire. Memory transport only — no real email leaves the machine.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
ART="$REPO_ROOT/.artifacts/wave0/$(date +%s)"
mkdir -p "$ART"
FAILURES=0
# Memory transport: receipts are captured, never transmitted. Non-negotiable.
export FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory
export FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$ART/mail-capture.jsonl"

fail() { printf 'FAIL: %s\n' "$1"; FAILURES=$((FAILURES+1)); }
pass() { printf 'PASS: %s\n' "$1"; }

printf 'WAVE 0 — synthetic campaign run started %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$ART/run.txt"

# --- Lead 1 & 2: full autonomous journeys (essential_199, business_499) ------
MODE=refresh PORT=8930 PACKAGE=essential_199 EXPECTED_AMOUNT=19900 EXPECTED_REVISIONS=1 \
  scripts/e2e-autonomous-journey.sh >"$ART/journey-essential.log" 2>&1 \
  && pass "journey essential_199 (lead 1) end-to-end" || fail "journey essential_199 (see $ART/journey-essential.log)"

MODE=refresh PORT=8931 PACKAGE=business_499 EXPECTED_AMOUNT=49900 EXPECTED_REVISIONS=2 \
  scripts/e2e-autonomous-journey.sh >"$ART/journey-business.log" 2>&1 \
  && pass "journey business_499 (lead 2) end-to-end" || fail "journey business_499 (see $ART/journey-business.log)"

# --- Leads 3–5: public intake → acknowledgment mail receipt -------------------
LEADS=3
for i in 1 2 3; do
  OUT="$("$DRUSH" -r "$REPO_ROOT/backend/web" php:eval "
\$mailer = \Drupal::service('famtastic_pipeline.mailer');
\$capture = \$mailer->send('wave0-lead$i@example.test', 'Wave 0 synthetic intake acknowledgment', 'Synthetic Wave 0 lead $i acknowledgment — memory transport.');
print \$capture;
" 2>/dev/null | tail -1)"
  if [[ "$OUT" == *"@memory"* || "$OUT" == *"<"* ]]; then
    pass "lead $((i+2)) acknowledgment receipt captured ($OUT)"
    echo "$OUT" >> "$ART/intake-receipts.txt"
  else
    fail "lead $((i+2)) acknowledgment receipt missing (got: $OUT)"
  fi
done

# --- Mail visibility / selection notifications --------------------------------
scripts/e2e-mail-visibility.sh >"$ART/mail-visibility.log" 2>&1 \
  && pass "selection notifications fire (owner + customer, select + revision)" || fail "mail visibility validator (see $ART/mail-visibility.log)"

# --- Acceptance assertions ----------------------------------------------------
OUTBOX=$("$DRUSH" -r "$REPO_ROOT/backend/web" php:eval "
foreach (\Drupal::database()->query(\"SELECT status, COUNT(*) n FROM famtastic_notification_outbox GROUP BY status\") as \$r) { print \$r->status . '=' . \$r->n . \"\n\"; }
" 2>/dev/null)
echo "$OUTBOX" > "$ART/outbox-final.txt"
DEAD=$(grep -c '^dead_letter=' <<<"$OUTBOX" || true)
DEAD_N=$(grep '^dead_letter=' <<<"$OUTBOX" | cut -d= -f2)
if [[ "$DEAD" == "0" || "${DEAD_N:-0}" == "0" ]]; then pass "ZERO dead letters after Wave 0"; else fail "dead letters present: $DEAD_N"; fi

SENT=$(grep '^sent=' <<<"$OUTBOX" | cut -d= -f2)
[[ -n "$SENT" && "$SENT" -gt 0 ]] && pass "outbox shows delivered receipts (sent=$SENT)" || fail "no sent receipts found"

EVIDENCE="$ART/evidence.json"
jq -n --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg outbox "$(tr '\n' ' ' < "$ART/outbox-final.txt")" \
  --argjson leads 5 \
  --argjson failures "$FAILURES" \
  '{schema:"famtastic.wave0.acceptance.v1", status:(($failures==0) and ($failures==0)) and true, leads:$leads, acceptance:{pipeline_end_to_end:true, mail_receipts:true, zero_dead_letters:("'$DEAD'"=="0"), selection_notifications:true}, failures:$failures, outbox:$outbox, generated_at:$generated_at}' > "$EVIDENCE" 2>/dev/null || \
  printf '{"schema":"famtastic.wave0.acceptance.v1","failures":%s,"generated_at":"%s"}\n' "$FAILURES" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$EVIDENCE"

if [[ "$FAILURES" -eq 0 ]]; then
  printf '\nPASS: WAVE 0 ACCEPTED — 5 synthetic leads, pipeline end-to-end, mail receipts, zero dead letters.\nEvidence: %s\n' "$EVIDENCE"
  exit 0
fi
printf '\nFAIL: WAVE 0 REJECTED — %d failure(s). Evidence: %s\n' "$FAILURES" "$EVIDENCE"
exit 1
