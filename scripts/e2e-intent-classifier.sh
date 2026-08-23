#!/usr/bin/env bash
# FAMtastic Designs — B1 support intent classifier acceptance.
# Runs the deterministic classifier over the labeled corpus fixture and
# records per-intent precision/recall plus overall accuracy as evidence.
# Pure offline evaluation — no mail, no Drupal bootstrap, nothing transmits.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ART="$REPO_ROOT/.artifacts/support-triage/$(date +%s)"
CLASSIFIER="$REPO_ROOT/backend/web/modules/custom/famtastic_pipeline/src/Service/SupportIntentClassifier.php"
FIXTURE="$REPO_ROOT/backend/tests/fixtures/support-intent-labeled.json"
mkdir -p "$ART"

php -r '
require $argv[1];
use Drupal\famtastic_pipeline\Service\SupportIntentClassifier;
$cases = json_decode(file_get_contents($argv[2]), TRUE);
if (!is_array($cases) || count($cases) < 20) {
  fwrite(STDERR, "FAIL: corpus must contain >=20 labeled messages\n");
  exit(1);
}
$c = new SupportIntentClassifier();
$stats = [];
$correct = 0; $escalated = 0; $rows = [];
foreach ($cases as $case) {
  $r = $c->classify($case["subject"], $case["body"]);
  $ok = ($r["intent"] === $case["expected_intent"]);
  // Safe routing: correct intent OR flagged for human draft queue anyway.
  $safe = $ok || $r["escalate"];
  foreach ([$case["expected_intent"], "all"] as $bucket) {
    $stats[$bucket]["n"] = ($stats[$bucket]["n"] ?? 0) + 1;
    if ($ok) { $stats[$bucket]["tp"] = ($stats[$bucket]["tp"] ?? 0) + 1; }
    if ($safe) { $stats[$bucket]["safe"] = ($stats[$bucket]["safe"] ?? 0) + 1; }
  }
  $correct += $ok ? 1 : 0;
  $escalated += $r["escalate"] ? 1 : 0;
  $rows[] = ["id" => $case["id"], "expected" => $case["expected_intent"],
             "intent" => $r["intent"], "confidence" => $r["confidence"],
             "escalate" => $r["escalate"], "signals" => $r["signals"],
             "correct" => $ok, "safe_routed" => $safe];
}
$perIntent = [];
foreach ($stats as $intent => $s) {
  if ($intent === "all") { continue; }
  $perIntent[$intent] = [
    "n" => $s["n"],
    "accuracy" => round(($s["tp"] ?? 0) / max($s["n"], 1), 3),
    "safe_route_rate" => round(($s["safe"] ?? 0) / max($s["n"], 1), 3),
  ];
}
$total = $stats["all"]["n"];
$accuracy = round($correct / $total, 3);
$safeRate = round($stats["all"]["safe"] / $total, 3);
$pass = ($accuracy >= 0.9 && $safeRate === 1.0);
$evidence = [
  "schema" => "famtastic.support-intent-b1.v1",
  "status" => $pass,
  "corpus_size" => $total,
  "accuracy" => $accuracy,
  "safe_route_rate" => $safeRate,
  "escalation_count" => $escalated,
  "per_intent" => $perIntent,
  "corpus_provenance" => "synthetic-realistic labeled corpus (backend/tests/fixtures/support-intent-labeled.json); real-history validation pending Fritz-run production export",
  "results" => $rows,
  "generated_at" => gmdate("Y-m-d\TH:i:s\Z"),
];
file_put_contents($argv[3], json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("%s — corpus=%d accuracy=%.1f%% safe_route=%.1f%% escalated=%d\n",
  $pass ? "PASS" : "FAIL", $total, $accuracy * 100, $safeRate * 100, $escalated);
exit($pass ? 0 : 1);
' "$CLASSIFIER" "$FIXTURE" "$ART/evidence.json"
RC=$?

[[ $RC -eq 0 ]] \
  && printf 'Evidence: %s\n' "$ART/evidence.json" \
  || printf 'B1 acceptance FAILED — inspect %s\n' "$ART/evidence.json"
exit $RC
