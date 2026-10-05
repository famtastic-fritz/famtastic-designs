<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Read-only campaign projection; payment entities remain monetary authority. */
final class AcquisitionCampaignReportService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  public function report(string $campaignKey): array {
    if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $campaignKey)) {
      throw new \InvalidArgumentException('Invalid campaign key.');
    }
    $campaign = $this->database->select('famtastic_campaign', 'c')->fields('c')
      ->condition('campaign_key', $campaignKey)->execute()->fetchAssoc();
    if (!$campaign) throw new \OutOfBoundsException('Campaign not found.');
    $samples = $this->rows('famtastic_acquisition_sample', 'campaign_id', (int) $campaign['id']);
    $messages = $this->rows('famtastic_email_message', 'campaign_id', (int) $campaign['id']);
    $messageIds = array_map(static fn(array $m): int => (int) $m['id'], $messages);
    $messageMetadata = $messageIds ? $this->rows('famtastic_acquisition_message', 'message_id', $messageIds) : [];
    $events = $this->rows('famtastic_event', 'campaign_id', (int) $campaign['id']);
    $invitationIds = array_values(array_unique(array_map(static fn(array $s): int => (int) $s['id'], $samples)));
    $mappings = $invitationIds ? $this->rows('famtastic_acquisition_request', 'invitation_id', $invitationIds) : [];
    $requestIds = array_values(array_unique(array_map(static fn(array $m): int => (int) $m['request_id'], $mappings)));
    $requestProspectIds = array_values(array_unique(array_filter(array_map(static fn(array $m): int => (int) $m['request_prospect_id'], $mappings))));
    $customerIds = array_values(array_unique(array_filter(array_map(static fn(array $s): int => (int) ($s['customer_id'] ?? 0), $samples))));
    $requests = $requestIds ? $this->rows('famtastic_project_request', 'id', $requestIds) : [];
    $customers = $customerIds ? $this->rows('famtastic_customer', 'id', $customerIds) : [];
    $fulfillments = $requestProspectIds ? $this->rows('famtastic_commerce_fulfillment', 'prospect_id', $requestProspectIds) : [];
    $payments = NULL;
    if ($this->entities->hasDefinition('commerce_payment')) {
      $payments = [];
      $orderIds = array_values(array_unique(array_filter(array_merge(
        array_map(static fn(array $f): int => (int) $f['commerce_order_id'], $fulfillments),
        array_map(static fn(array $r): int => (int) ($r['commerce_order_id'] ?? 0), $requests),
      ))));
      if ($orderIds) {
        $storage = $this->entities->getStorage('commerce_payment');
        $ids = $storage->getQuery()->accessCheck(FALSE)->condition('order_id', $orderIds, 'IN')->execute();
        foreach ($storage->loadMultiple($ids) as $payment) {
          $payments[] = self::paymentSnapshot($payment);
        }
      }
    }
    return self::project($campaignKey, $samples, $messages, $events, $customers, $requests, $fulfillments, $payments, $campaign, $mappings, $messageMetadata);
  }

  private function rows(string $table, string $field, int|array $value): array {
    if (!$this->database->schema()->tableExists($table)) return [];
    return $this->database->select($table, 'r')->fields('r')
      ->condition($field, $value, is_array($value) ? 'IN' : '=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** No provider calls or mutations; snapshots actual Commerce payment state. */
  public static function paymentSnapshot(object $payment): array {
    $amount = $payment->getAmount();
    $refund = $payment->getRefundedAmount();
    return [
      'id' => (int) $payment->id(), 'order_id' => (int) $payment->getOrderId(),
      'state' => (string) $payment->getState()->value,
      'gateway' => (string) $payment->getPaymentGatewayId(),
      'mode' => (string) $payment->getPaymentGatewayMode(),
      'remote_id' => (string) $payment->getRemoteId(),
      'currency' => $amount ? strtoupper((string) $amount->getCurrencyCode()) : '',
      'amount_minor' => $amount ? self::usdMinor((string) $amount->getNumber(), (string) $amount->getCurrencyCode()) : NULL,
      'refunded_minor' => $refund ? self::usdMinor((string) $refund->getNumber(), (string) $refund->getCurrencyCode()) : NULL,
    ];
  }

  /** USD-only campaign: do not mix currencies or round using float arithmetic. */
  public static function usdMinor(string $number, string $currency): ?int {
    if (strtoupper($currency) !== 'USD' || !preg_match('/^(\d{1,12})(?:\.(\d{1,2})0*)?$/D', $number, $m)) return NULL;
    return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
  }

  /** Synthetic-testable aggregate with no PII or bearer-link output. */
  public static function project(string $campaignKey, array $samples, array $messages, array $events, array $customers, array $requests, array $fulfillments, ?array $payments, array $campaign = [], array $requestMappings = [], array $messageMetadata = []): array {
    $base = self::emptyTotals();
    $byProspect = [];
    $niches = $arms = $sampleGroups = [];
    $seenSamples = [];
    $sampleById = [];
    foreach ($samples as $sample) {
      $id = (int) $sample['id'];
      if (isset($seenSamples[$id])) continue;
      $seenSamples[$id] = TRUE;
      $sampleById[$id] = $sample;
      $prospectId = (int) $sample['prospect_id'];
      $byProspect[$prospectId][] = $sample;
      $base['invitations']++;
      $niche = self::safeLabel((string) ($sample['niche'] ?? 'unknown'));
      $arm = in_array($sample['experiment_arm'] ?? '', ['generic', 'tailored'], TRUE) ? $sample['experiment_arm'] : 'unknown';
      $niches[$niche] ??= self::emptyTotals();
      $arms[$arm] ??= self::emptyTotals();
      $niches[$niche]['invitations']++;
      $arms[$arm]['invitations']++;
      $recipe = (string) ($sample['preferred_recipe'] ?? '');
      if ($recipe !== '') {
        $key = self::safeLabel($recipe);
        $sampleGroups[$key] ??= self::emptyTotals();
        $sampleGroups[$key]['invitations']++;
      }
    }
    // Invitation issuance is not qualification. Only explicit receipts count.
    $qualified = [];
    $qualificationPresent = $samples === [];
    foreach ($samples as $s) {
      $qualificationPresent = $qualificationPresent || array_key_exists('eligible_at', $s);
      if (!empty($s['eligible_at']) && !empty($s['qualification_ref'])) $qualified[(int) $s['prospect_id']] = TRUE;
    }
    $base['eligible_recipients'] = $qualificationPresent ? count($qualified) : NULL;
    if ($qualificationPresent) {
      foreach ($niches as &$totals) $totals['eligible_recipients'] = 0;
      unset($totals);
      foreach ($arms as &$totals) $totals['eligible_recipients'] = 0;
      unset($totals);
      foreach (array_keys($qualified) as $pid) self::incrementGrains($byProspect[$pid], $niches, $arms, 'eligible_recipients');
    }
    $sandboxMessages = ['sends' => 0, 'recorded_opens' => 0, 'recorded_clicks' => 0];
    $messageGroups = [];
    $seenMessages = [];
    $contentByMessage = [];
    foreach ($messageMetadata as $metadata) $contentByMessage[(int) $metadata['message_id']] = $metadata;
    foreach ($messages as $message) {
      if (($message['template_key'] ?? '') !== 'acquisition_sample_v1' || !isset($byProspect[(int) $message['prospect_id']])) continue;
      $id = (int) $message['id'];
      if (isset($seenMessages[$id])) continue;
      if (!preg_match('/^sample-v1:(\d+):day:(0|3|7)$/D', (string) $message['message_key'], $m)) continue;
      $sample = $sampleById[(int) $m[1]] ?? NULL;
      if (!$sample || (int) $sample['prospect_id'] !== (int) $message['prospect_id']) continue;
      $metadata = $contentByMessage[$id] ?? NULL;
      if ($metadata && ((int) $metadata['invitation_id'] !== (int) $sample['id'] || (int) $metadata['day'] !== (int) $m[2])) continue;
      $seenMessages[$id] = TRUE;
      $key = $metadata ? self::safeLabel((string) $metadata['content_id']) : 'unclassified_day_' . $m[2];
      $controlledMessage = in_array(strtolower((string) ($message['provider'] ?? '')), ['acquisition_memory', 'memory', 'stub', 'test'], TRUE) || in_array(strtolower((string) ($message['mode'] ?? $message['provider_mode'] ?? '')), ['test', 'sandbox'], TRUE);
      if (!$controlledMessage) $messageGroups[$key] ??= self::emptyTotals();
      foreach (['sends' => 'sent_at', 'recorded_opens' => 'opened_at', 'recorded_clicks' => 'clicked_at'] as $metric => $field) {
        if (empty($message[$field])) continue;
        if ($controlledMessage) {
          $sandboxMessages[$metric]++;
          continue;
        }
        $base[$metric]++;
        $messageGroups[$key][$metric]++;
        self::incrementGrains([$sample], $niches, $arms, $metric);
      }
    }
    $verified = [];
    foreach ($customers as $customer) if (!empty($customer['verified_at'])) $verified[(int) $customer['id']] = TRUE;
    $registeredProspects = [];
    foreach ($byProspect as $pid => $rows) {
      foreach ($rows as $sample) if (isset($verified[(int) ($sample['customer_id'] ?? 0)])) {
        $registeredProspects[$pid] = TRUE;
        break;
      }
    }
    $base['verified_registrations'] = count($registeredProspects);
    $requestMap = [];
    foreach ($requestMappings as $mapping) {
      $sample = $sampleById[(int) ($mapping['invitation_id'] ?? 0)] ?? NULL;
      if (!$sample || empty($sample['customer_id']) || (int) $sample['customer_id'] !== (int) ($mapping['customer_id'] ?? 0)) continue;
      $requestMap[(int) $mapping['request_id']] = ['prospect_id' => (int) $sample['prospect_id'], 'customer_id' => (int) $mapping['customer_id'], 'request_prospect_id' => (int) $mapping['request_prospect_id']];
    }
    $completed = [];
    $attributedRequests = [];
    $commercialProspectMap = [];
    foreach ($requests as $request) {
      $mapping = $requestMap[(int) ($request['id'] ?? 0)] ?? NULL;
      if (!$mapping || (int) ($request['customer_id'] ?? 0) !== $mapping['customer_id'] || (int) ($request['prospect_id'] ?? 0) !== $mapping['request_prospect_id']) continue;
      $pid = $mapping['prospect_id'];
      $attributedRequests[] = $request;
      $commercialProspectMap[(int) $request['prospect_id']] = $pid;
      if (!empty($request['submitted_at']) && isset($registeredProspects[$pid])) $completed[$pid] = TRUE;
    }
    $base['completed_interviews'] = count($completed);
    foreach (array_keys($registeredProspects) as $pid) self::incrementGrains($byProspect[$pid], $niches, $arms, 'verified_registrations');
    foreach (array_keys($completed) as $pid) self::incrementGrains($byProspect[$pid], $niches, $arms, 'completed_interviews');
    $replies = [];
    $preferences = $claims = $rawReplies = [];
    foreach ($events as $event) {
      $pid = (int) ($event['prospect_id'] ?? 0);
      if (!isset($byProspect[$pid])) continue;
      if (in_array($event['event_type'] ?? '', ['email.replied', 'acquisition.human_reply'], TRUE)) $replies[$pid] = TRUE;
      if (($event['event_type'] ?? '') === 'acquisition.reply_received') $rawReplies[$pid] = TRUE;
      if (($event['event_type'] ?? '') === 'acquisition.sample_preference') $preferences[$pid] = TRUE;
      if (($event['event_type'] ?? '') === 'acquisition.sample_claimed' && isset($registeredProspects[$pid])) $claims[$pid] = TRUE;
    }
    // Historical portal inbound evidence has no campaign join: zero isn't proof.
    $base['replies'] = $replies ? count($replies) : NULL;
    foreach (array_keys($replies) as $pid) self::incrementGrains($byProspect[$pid], $niches, $arms, 'replies');
    $orderMap = [];
    foreach ($attributedRequests as $row) {
      $pid = $commercialProspectMap[(int) $row['prospect_id']];
      if (!empty($row['commerce_order_id']) && isset($byProspect[$pid])) $orderMap[(int) $row['commerce_order_id']] = $pid;
    }
    foreach ($fulfillments as $row) {
      $pid = $commercialProspectMap[(int) ($row['prospect_id'] ?? 0)] ?? NULL;
      if ($pid !== NULL) $orderMap[(int) $row['commerce_order_id']] = $pid;
    }
    $money = self::reconcilePayments($payments, $orderMap);
    $sandboxMoney = self::reconcilePayments($payments, $orderMap, 'test');
    foreach (['paid_customers', 'refunds', 'revenue_minor'] as $metric) $base[$metric] = $money[$metric];
    if ($money['known']) {
      foreach (['paid_customers', 'refunds', 'revenue_minor'] as $metric) {
        foreach ($niches as &$totals) $totals[$metric] = 0;
        unset($totals);
        foreach ($arms as &$totals) $totals[$metric] = 0;
        unset($totals);
      }
    }
    foreach ($money['by_prospect'] as $pid => $totals) {
      foreach ($totals as $metric => $value) self::incrementGrains($byProspect[$pid], $niches, $arms, $metric, $value);
    }
    $base['known_campaign_spend_minor'] = array_key_exists('spent_minor', $campaign) && strtoupper((string) ($campaign['currency'] ?? '')) === 'USD' ? (int) $campaign['spent_minor'] : NULL;
    $gaps = ['Recorded opens/clicks may include privacy proxies and scanners; human/machine split is unknown.', 'Contribution awaits measured provider, processing, fulfillment and labor cost receipts.', 'Message and preference sample rows have no causal payment attribution; their revenue remains unknown.', 'Sending approval and hosted behavior are separate from these runtime records.', 'Explicit memory/stub/test message captures appear only in controlled_sandbox.messaging; provider-unknown native rows remain recorded outcomes, not proven deliveries.'];
    if ($base['replies'] === NULL) $gaps[] = 'No explicit campaign-attributed human reply evidence; inbound portal messages are not assumed to be campaign replies.';
    if ($base['eligible_recipients'] === NULL) $gaps[] = 'Eligibility receipt fields unavailable; issued invitations are not verified recipients.';
    if (!$money['known']) $gaps[] = 'Commerce payment/refund reconciliation unavailable or conflicting; financial totals withheld.';
    return [
      'schema' => 'famtastic.acquisition-report.v1', 'campaign_key' => $campaignKey,
      'generated_at' => time(), 'environment' => 'runtime_records', 'data_scope' => 'campaign_only', 'currency' => 'USD',
      'totals' => $base,
      'breakdowns' => ['niche' => self::grainRows($niches), 'message' => self::grainRows($messageGroups), 'sample' => self::grainRows($sampleGroups), 'experiment_arm' => self::grainRows($arms)],
      'interaction_quality' => ['human_opens' => NULL, 'human_clicks' => NULL, 'machine_opens' => NULL, 'machine_clicks' => NULL, 'meaningful_preferences' => count($preferences), 'verified_claims' => count($claims), 'recorded_reply_recipients' => count($rawReplies)],
      'payment_modes' => $money['modes'],
      'controlled_sandbox' => ['mode' => 'test', 'messaging' => $sandboxMessages, 'paid_customers' => $sandboxMoney['paid_customers'], 'refunds' => $sandboxMoney['refunds'], 'revenue_minor' => $sandboxMoney['revenue_minor'], 'counts_toward_campaign' => FALSE],
      'gaps' => $gaps,
      'definitions' => [
        'sends' => 'Distinct native sample message rows with sent_at, excluding explicit memory/stub/test captures; not inbox delivery.',
        'recorded_opens' => 'Distinct message rows with opened_at; proxy/scanner activity may be included.',
        'recorded_clicks' => 'Distinct message rows with clicked_at; not authenticated customer actions.',
        'verified_registrations' => 'Distinct invited prospects linked to a verified customer account.',
        'completed_interviews' => 'Distinct verified invited prospects with a submitted authenticated website request.',
        'paid_customers' => 'Distinct attributed prospects with a positive live captured Commerce payment, including subsequently refunded customers. Test-mode and zero-dollar sponsored orders excluded.',
        'refunds' => 'Distinct Commerce payment captures with a positive refunded_amount.',
        'revenue_minor' => 'Live Commerce captured USD payment amounts minus Commerce refunded amounts; no legacy package totals or test-mode captures.',
        'sample' => 'Explicit preferred recipe only; preference is illustrative and not formal proof selection.',
        'experiment_arm' => 'Random business assignment within niche; exploratory counts do not establish a statistical winner.',
      ],
    ];
  }

  /** Deduplicate captures, preserve partial refunds, reject conflicting receipts. */
  public static function reconcilePayments(?array $payments, array $orderMap, string $mode = 'live'): array {
    $out = ['known' => $payments !== NULL, 'paid_customers' => 0, 'refunds' => 0, 'revenue_minor' => 0, 'by_prospect' => [], 'modes' => []];
    $seen = $paid = $modes = [];
    foreach ($payments ?? [] as $p) {
      $orderId = (int) ($p['order_id'] ?? 0);
      if (!isset($orderMap[$orderId]) || !in_array($p['state'] ?? '', ['completed', 'partially_refunded', 'refunded'], TRUE)) continue;
      if (!in_array($p['mode'] ?? '', ['live', 'test'], TRUE)) {
        $out['known'] = FALSE;
        continue;
      }
      if ($p['mode'] !== $mode) continue;
      $pid = $orderMap[$orderId];
      $key = !empty($p['remote_id']) ? ($p['gateway'] ?? '') . ':' . ($p['mode'] ?? '') . ':' . $p['remote_id'] : 'entity:' . (int) ($p['id'] ?? 0);
      $receipt = [$orderId, $p['state'], $p['amount_minor'] ?? NULL, $p['refunded_minor'] ?? NULL, $p['currency'] ?? ''];
      if (isset($seen[$key])) {
        if ($seen[$key] !== $receipt) $out['known'] = FALSE;
        continue;
      }
      $seen[$key] = $receipt;
      $amount = $p['amount_minor'] ?? NULL;
      $refund = $p['refunded_minor'] ?? NULL;
      if (($p['currency'] ?? '') !== 'USD' || !is_int($amount) || !is_int($refund) || $amount < 0 || $refund < 0 || $refund > $amount || (($p['state'] ?? '') === 'refunded' && $refund !== $amount)) {
        $out['known'] = FALSE;
        continue;
      }
      $out['by_prospect'][$pid] ??= ['paid_customers' => 0, 'refunds' => 0, 'revenue_minor' => 0];
      if ($amount > 0 && !isset($paid[$pid])) {
        $paid[$pid] = TRUE;
        $out['by_prospect'][$pid]['paid_customers'] = 1;
      }
      $out['by_prospect'][$pid]['refunds'] += $refund > 0 ? 1 : 0;
      $out['by_prospect'][$pid]['revenue_minor'] += $amount - $refund;
      $modes[(string) ($p['mode'] ?? 'unknown')] = TRUE;
    }
    $out['modes'] = array_keys($modes);
    if (!$out['known']) {
      foreach (['paid_customers', 'refunds', 'revenue_minor'] as $k) $out[$k] = NULL;
      $out['by_prospect'] = [];
    }
    else {
      $out['paid_customers'] = count($paid);
      $out['refunds'] = array_sum(array_column($out['by_prospect'], 'refunds'));
      $out['revenue_minor'] = array_sum(array_column($out['by_prospect'], 'revenue_minor'));
    }
    return $out;
  }

  private static function emptyTotals(): array {
    return ['eligible_recipients' => NULL, 'invitations' => 0, 'sends' => 0, 'recorded_opens' => 0, 'recorded_clicks' => 0, 'replies' => NULL, 'verified_registrations' => 0, 'completed_interviews' => 0, 'paid_customers' => NULL, 'refunds' => NULL, 'revenue_minor' => NULL, 'known_campaign_spend_minor' => NULL, 'measured_cost_minor' => NULL, 'contribution_minor' => NULL];
  }

  private static function safeLabel(string $label): string {
    return preg_match('/^[a-zA-Z0-9_.:-]{1,96}$/D', $label) ? $label : 'unknown';
  }

  private static function incrementGrains(array $samples, array &$niches, array &$arms, string $metric, int $value = 1): void {
    $ns = $as = [];
    foreach ($samples as $s) {
      $ns[self::safeLabel((string) ($s['niche'] ?? 'unknown'))] = TRUE;
      $as[in_array($s['experiment_arm'] ?? '', ['generic', 'tailored'], TRUE) ? $s['experiment_arm'] : 'unknown'] = TRUE;
    }
    // Conflicting invitations cannot credit the same customer's payment to both arms.
    $n = count($ns) === 1 ? array_key_first($ns) : 'unknown';
    $a = count($as) === 1 ? array_key_first($as) : 'unknown';
    $niches[$n] ??= self::emptyTotals();
    $arms[$a] ??= self::emptyTotals();
    $niches[$n][$metric] = ($niches[$n][$metric] ?? 0) + $value;
    $arms[$a][$metric] = ($arms[$a][$metric] ?? 0) + $value;
  }

  private static function grainRows(array $groups): array {
    ksort($groups);
    return array_map(static fn(string $key, array $totals): array => ['key' => $key, 'totals' => $totals], array_keys($groups), array_values($groups));
  }
}
