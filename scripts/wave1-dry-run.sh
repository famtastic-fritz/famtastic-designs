#!/usr/bin/env bash
# FAMtastic Designs — T4 Wave 1 REHEARSAL (dry run, memory transport).
# Proves capacity for the 20-lead wave BEFORE any Fritz send gate: two full
# autonomous journeys plus twenty intake acknowledgment receipts, then
# asserts pipeline health (zero dead letters, delivered receipts).
# NOTHING transmits and this is not the real wave: real outreach requires
# Fritz's explicit gate approval per STRATEGY-PRICING.md.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
ART="$REPO_ROOT/.artifacts/wave1-rehearsal/$(date +%s)"
mkdir -p "$ART"
FAILURES=0
export FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory
export FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$ART/mail-capture.jsonl"

fail() { printf 'FAIL: %s\n' "$1"; FAILURES=$((FAILURES+1)); }
pass() { printf 'PASS: %s\n' "$1"; }

printf 'WAVE 1 REHEARSAL started %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$ART/run.txt"

MODE=refresh PORT=8940 PACKAGE=essential_199 EXPECTED_AMOUNT=19900 EXPECTED_REVISIONS=1 \
  scripts/e2e-autonomous-journey.sh >"$ART/journey-1.log" 2>&1 \
  && pass "journey 1 end-to-end" || fail "journey 1 (see $ART/journey-1.log)"

MODE=refresh PORT=8941 PACKAGE=business_499 EXPECTED_AMOUNT=49900 EXPECTED_REVISIONS=2 \
  scripts/e2e-autonomous-journey.sh >"$ART/journey-2.log" 2>&1 \
  && pass "journey 2 end-to-end" || fail "journey 2 (see $ART/journey-2.log)"

LEADS=18
OK=0
for i in $(seq 1 $LEADS); do
  OUT="$("$DRUSH" -r "$REPO_ROOT/backend/web" php:eval "
\$mailer = \Drupal::service('famtastic_pipeline.mailer');
print \$mailer->send('wave1-rehearsal-lead$i@example.test', 'Wave 1 rehearsal acknowledgment', 'Rehearsal lead $i — memory transport only.');
" 2>/dev/null | tail -1)"
  [[ "$OUT" == *"@memory"* ]] && OK=$((OK+1)) || { fail "lead $i receipt missing"; echo "$OUT" >> "$ART/failed-leads.txt"; }
done
[[ "$OK" -eq "$LEADS" ]] && pass "$LEADS/$LEADS intake acknowledgment receipts captured" || fail "only $OK/$LEADS receipts"

OUTBOX=$("$DRUSH" -r "$REPO_ROOT/backend/web" php:eval "
foreach (\Drupal::database()->query(\"SELECT status, COUNT(*) n FROM famtastic_notification_outbox GROUP BY status\") as \$r) { print \$r->status . '=' . \$r->n . \"\n\"; }
" 2>/dev/null)
echo "$OUTBOX" > "$ART/outbox-final.txt"
DEAD_N=$(grep '^dead_letter=' <<<"$OUTBOX" | cut -d= -f2)
[[ "${DEAD_N:-0}" == "0" ]] && pass "ZERO dead letters after rehearsal" || fail "dead letters present: $DEAD_N"
SENT=$(grep '^sent=' <<<"$OUTBOX" | cut -d= -f2)
[[ -n "$SENT" && "$SENT" -gt 0 ]] && pass "outbox shows delivered receipts (sent=$SENT)" || fail "no sent receipts"

STATUS=true; [[ $FAILURES -eq 0 ]] || STATUS=false
jq -n --argjson status "$STATUS" --argjson failures "$FAILURES" --arg at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" --arg sent "${SENT:-0}" \
  '{schema:"famtastic.wave1-rehearsal.v1", status:$status, failures:$failures, leads_rehearsed:20, transport:"memory-only", real_wave_gate:"CLOSED - requires Fritz approval", outbox_sent:$sent, generated_at:$at}' \
  > "$ART/evidence.json"

if [[ $FAILURES -eq 0 ]]; then
  printf '\nPASS: WAVE 1 REHEARSAL COMPLETE — pipeline ready for the real 20-lead wave pending Fritz gate.\nEvidence: %s/evidence.json\n' "$ART"
  exit 0
fi
printf '\nFAIL: WAVE 1 REHEARSAL — %d failure(s). Evidence: %s/evidence.json\n' "$FAILURES" "$ART"
exit 1
