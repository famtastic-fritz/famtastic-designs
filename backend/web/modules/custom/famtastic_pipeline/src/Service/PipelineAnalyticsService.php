<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/**
 * Produces decision-grade campaign and funnel analytics from source-of-truth data.
 */
final class PipelineAnalyticsService {

  public function __construct(private readonly Connection $database) {}

  public function report(): array {
    $campaigns = [];
    $rows = $this->database->select('famtastic_campaign', 'c')
      ->fields('c')
      ->orderBy('campaign_key')
      ->execute()
      ->fetchAllAssoc('campaign_key', \PDO::FETCH_ASSOC);
    foreach ($rows as $key => $campaign) {
      $campaigns[$key] = [
        'campaign_key' => $key,
        'status' => $campaign['status'],
        'spend_minor' => (int) $campaign['spent_minor'],
        'currency' => $campaign['currency'],
        'leads' => 0,
        'qualified' => 0,
        'proofs_ready' => 0,
        'sales' => 0,
        'revenue_minor' => 0,
        'launches' => 0,
        'renewals_paid' => 0,
        'conversion_rate' => 0.0,
        'cost_per_sale_minor' => NULL,
        'average_time_to_launch_seconds' => NULL,
      ];
    }

    $prospects = $this->database->select('famtastic_prospect', 'p')
      ->fields('p', ['id', 'campaign', 'source', 'created'])
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $sources = [];
    foreach ($prospects as $prospect) {
      $key = (string) ($prospect['campaign'] ?: 'unattributed');
      $source = (string) ($prospect['source'] ?: 'unknown');
      $campaigns[$key] ??= [
        'campaign_key' => $key, 'status' => 'unregistered', 'spend_minor' => 0,
        'currency' => 'usd', 'leads' => 0, 'qualified' => 0, 'proofs_ready' => 0,
        'sales' => 0, 'revenue_minor' => 0, 'launches' => 0, 'renewals_paid' => 0,
        'conversion_rate' => 0.0, 'cost_per_sale_minor' => NULL,
        'average_time_to_launch_seconds' => NULL,
      ];
      $campaigns[$key]['leads']++;
      $sources[$source] ??= ['source' => $source, 'leads' => 0, 'sales' => 0, 'revenue_minor' => 0];
      $sources[$source]['leads']++;

      $qualified = (bool) $this->database->select('famtastic_lead_import', 'l')
        ->condition('prospect_id', $prospect['id'])
        ->condition('status', 'qualified')
        ->countQuery()->execute()->fetchField();
      $campaigns[$key]['qualified'] += $qualified ? 1 : 0;
      $campaigns[$key]['proofs_ready'] += $this->eventExists('proof.ready', (int) $prospect['id']) ? 1 : 0;

      $orders = $this->database->select('famtastic_order', 'o')
        ->fields('o', ['id', 'amount', 'payment_status'])
        ->condition('prospect_ref', $prospect['id'])
        ->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($orders as $order) {
        if ($order['payment_status'] === 'paid') {
          $campaigns[$key]['sales']++;
          $campaigns[$key]['revenue_minor'] += (int) $order['amount'];
          $sources[$source]['sales']++;
          $sources[$source]['revenue_minor'] += (int) $order['amount'];
        }
      }
      $launch = $this->database->select('famtastic_event', 'e')
        ->fields('e', ['occurred_at'])
        ->condition('prospect_id', $prospect['id'])
        ->condition('event_type', 'deployment.deployed')
        ->orderBy('occurred_at')->range(0, 1)->execute()->fetchField();
      if ($launch) {
        $campaigns[$key]['launches']++;
        $campaigns[$key]['launch_seconds'][] = max(0, (int) $launch - (int) $prospect['created']);
      }
      $campaigns[$key]['renewals_paid'] += (int) $this->database->select('famtastic_event', 'e')
        ->condition('prospect_id', $prospect['id'])
        ->condition('event_type', 'hosting.renewal_paid')
        ->countQuery()->execute()->fetchField();
    }

    foreach ($campaigns as &$campaign) {
      $campaign['conversion_rate'] = $campaign['leads'] > 0
        ? round($campaign['sales'] / $campaign['leads'], 4)
        : 0.0;
      $campaign['cost_per_sale_minor'] = $campaign['sales'] > 0
        ? (int) round($campaign['spend_minor'] / $campaign['sales'])
        : NULL;
      if (!empty($campaign['launch_seconds'])) {
        $campaign['average_time_to_launch_seconds'] = (int) round(array_sum($campaign['launch_seconds']) / count($campaign['launch_seconds']));
      }
      unset($campaign['launch_seconds']);
    }
    unset($campaign);
    foreach ($sources as &$source) {
      $source['conversion_rate'] = $source['leads'] > 0 ? round($source['sales'] / $source['leads'], 4) : 0.0;
    }

    return [
      'generated_at' => time(),
      'campaigns' => array_values($campaigns),
      'sources' => array_values($sources),
      'definitions' => [
        'conversion_rate' => 'paid orders divided by attributed prospects',
        'cost_per_sale_minor' => 'campaign spend divided by paid orders',
        'average_time_to_launch_seconds' => 'first deployment event minus prospect creation',
        'revenue_minor' => 'verified paid order amount in minor currency units',
      ],
    ];
  }

  private function eventExists(string $type, int $prospectId): bool {
    return (bool) $this->database->select('famtastic_event', 'e')
      ->condition('event_type', $type)
      ->condition('prospect_id', $prospectId)
      ->countQuery()->execute()->fetchField();
  }

}
