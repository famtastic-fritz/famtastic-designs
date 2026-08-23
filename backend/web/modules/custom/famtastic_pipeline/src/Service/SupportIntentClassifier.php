<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Deterministic rules-first intent classifier for inbound support messages.
 *
 * Phase B step B1 of docs/playbook/RECIPES/AUTONOMOUS_CUSTOMER_SERVICE.md.
 * Five intents: status, revision, billing, technical, other. Every result
 * carries a confidence score; anything below the escalation threshold — and
 * every "other" fallback — MUST route to the human draft queue, never guess.
 *
 * Deliberately dependency-free so validators can require() this file without
 * a Drupal bootstrap while Drupal callers use the registered service.
 */
final class SupportIntentClassifier {

  public const INTENTS = ['status', 'revision', 'billing', 'technical', 'other'];
  public const FALLBACK = 'other';
  public const ESCALATION_THRESHOLD = 0.6;

  /**
   * Rule id => [intent, regex, weight].
   *
   * Weights express evidence strength; subject hits count double (applied at
   * classification time). Keep ids stable: they appear in evidence JSON and
   * docs/playbook/RUNBOOKS/B1-intent-classification-rules.md.
   */
  private const RULES = [
    // Revision — imperative change requests (work being commissioned).
    'r_change_verb' => ['revision', '/\b(please\s+)?(change|revise|redo|tweak|adjust|swap|replace|fix up)\b/i', 2],
    'r_revision_noun' => ['revision', '/\b(revision|revisions|new version|another (round|version)|one more (round|edit))\b/i', 2],
    'r_add_remove' => ['revision', '/\b(add|remove|take off|put back|delete)\b[^.?!]*\b(logo|page|section|photo|image|picture|text|link|form|map|hours|button|color|colour)\b/i', 2],
    'r_want_different' => ['revision', "/\bi('d| would| want| need)[^.?!]*\b(different|changed|updated|edited)\b/i", 1],

    // Billing — money, invoices, payment mechanics.
    'b_invoice' => ['billing', '/\b(invoice|receipt|statement)\b/i', 2],
    'b_refund' => ['billing', '/\b(refund|charge ?back|money back)\b/i', 2],
    'b_payment' => ['billing', "/\b(pay(ment|ed|ing|s)?|bill(ed|ing)?|charged|my card|credit card)\b/i", 2],
    'b_cost_ask' => ['billing', '/\b(price|cost|renewal|subscription fee)\b/i', 1],

    // Technical — breakage, access, performance.
    't_broken' => ['technical', "/\b(broken|not working|doesn'?t work|did not work|crashed|white screen|down)\b/i", 2],
    't_error' => ['technical', '/\b(error|error message|404|500 error|fatal)\b/i', 2],
    't_access' => ['technical', "/\b(can'?t|cannot|unable to)\s+(log ?in|sign in|access)|\blocked out|\bpassword reset?\b/i", 2],
    't_perf' => ['technical', '/\b(slow|loading forever|times? out|timeout|hanging)\b/i', 1],

    // Status — questions about progress/timing (asks, not commissions).
    's_when' => ['status', "/\bwhen (will|can|do you|are you)|\bhow long\b|\beta\b|\btimeline\b|\bdeadline\b/i", 2],
    's_progress' => ['status', "/\b(status|progress|any news|an update on|update on|how'?s it going|is it (done|ready|live)|ready yet|still waiting)\b/i", 2],
  ];

  private const PRIORITY = ['revision', 'billing', 'technical', 'status'];

  /**
   * Classify one inbound message.
   *
   * @return array{intent: string, confidence: float, signals: string[], escalate: bool}
   *   Intent, confidence in [0,1] rounded to 2dp, matched rule ids annotated
   *   with their source (`@subject`/`@body`), and whether the message must go
   *   to the human draft queue instead of automated handling.
   */
  public function classify(string $subject, string $body): array {
    $scores = array_fill_keys(self::INTENTS, 0.0);
    $signals = [];

    foreach ([['subject', $subject, 2.0], ['body', $body, 1.0]] as [$source, $text, $multiplier]) {
      if ($text === '') {
        continue;
      }
      foreach (self::RULES as $id => [$intent, $pattern]) {
        if (preg_match($pattern, $text) === 1) {
          $scores[$intent] += $multiplier;
          $signals[] = $id . '@' . $source;
        }
      }
    }

    // Interrogative bias: questions ask about state rather than commission
    // work, so give status one extra point of evidence when the message is
    // phrased as a question. Documented in B1-intent-classification-rules.md.
    $combined = trim($subject . "\n" . $body);
    $isQuestion = str_contains($combined, '?')
      || preg_match('/^\s*(when|how|is|are|did|does|do|can you tell)\b/i', $combined) === 1;
    if ($isQuestion && $scores['status'] > 0) {
      $scores['status'] += 1.0;
      $signals[] = 'interrogative_bias@message';
    }

    arsort($scores);
    $topIntent = array_key_first($scores);
    $topScore = $scores[$topIntent];

    if ($topScore <= 0.0) {
      return [
        'intent' => self::FALLBACK,
        'confidence' => 0.0,
        'signals' => [],
        'escalate' => TRUE,
      ];
    }

    $secondScore = 0.0;
    foreach ($scores as $intent => $score) {
      if ($intent !== $topIntent && $score > $secondScore) {
        $secondScore = $score;
      }
    }

    // Tie-break by fixed priority (actionable work outranks informational
    // asks once the interrogative bias has had its say).
    if ($secondScore === $topScore && in_array($topIntent, self::PRIORITY, TRUE)) {
      foreach (self::PRIORITY as $candidate) {
        if ($scores[$candidate] === $topScore) {
          $topIntent = $candidate;
          break;
        }
      }
    }

    $confidence = round($topScore / ($topScore + $secondScore + 1.0), 2);

    return [
      'intent' => $topIntent,
      'confidence' => $confidence,
      'signals' => $signals,
      'escalate' => $confidence < self::ESCALATION_THRESHOLD,
    ];
  }

}
