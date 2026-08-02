<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Operator-first campaign, message, proof, job, and build telemetry pages.
 */
final class OperationsController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $pipelineEntityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renders the operations dashboard and campaign rollup.
   */
  public function dashboard(): array {
    $campaignTotal = $this->count('famtastic_campaign');
    $campaigns = $this->database->select('famtastic_campaign', 'c')
      ->extend(PagerSelectExtender::class)
      ->fields('c')
      ->orderBy('created', 'DESC')
      ->limit(25)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $summary = [
      'Campaigns' => $campaignTotal,
      'Prospects' => $this->count('famtastic_prospect'),
      'Proofs Ready' => $this->count('proof_campaign', ['generation_status' => 'ready']),
      'Emails Sent' => $this->count('famtastic_event', ['event_type' => 'email.sent']),
      'Clicks' => $this->count('famtastic_event', ['event_type' => 'email.clicked']),
      'Paid Orders' => $this->count('famtastic_order', ['payment_status' => 'paid']),
      'Open Jobs' => $this->countIn('famtastic_job', 'status', ['queued', 'retry', 'running']),
      'Open Exceptions' => $this->countIn('famtastic_exception', 'status', ['open', 'retry']),
    ];

    $rows = [];
    foreach ($campaigns as $campaign) {
      $key = (string) $campaign['campaign_key'];
      $prospectIds = $this->prospectIds($key);
      $proofs = $prospectIds === [] ? 0 : $this->countIn('proof_campaign', 'prospect_id', $prospectIds, ['generation_status' => 'ready']);
      $sales = $prospectIds === [] ? 0 : $this->countIn('famtastic_order', 'prospect_ref', $prospectIds, ['payment_status' => 'paid']);
      $rows[] = [
        'campaign' => ['data' => Link::fromTextAndUrl($key, Url::fromRoute('famtastic_pipeline.operations_campaign', ['campaign_key' => $key]))->toRenderable()],
        'status' => ['data' => ['#markup' => $this->badge((string) $campaign['status'])]],
        'source' => $this->sourceLabel((string) ($campaign['source_filter'] ?? '')),
        'prospects' => count($prospectIds),
        'proofs' => $proofs,
        'sent' => $this->eventCount((int) $campaign['id'], 'email.sent'),
        'clicked' => $this->eventCount((int) $campaign['id'], 'email.clicked'),
        'sales' => $sales,
        'builds' => $this->count('famtastic_build_run', ['campaign_key' => $key]),
      ];
    }

    return $this->page([
      'intro' => [
        '#markup' => '<p class="famtastic-ops__lede">One place to inspect every campaign, recipient message, proof, build prompt, agent, job, event, and sale.</p>',
      ],
      'summary' => $this->metricCards($summary),
      'campaign_heading' => ['#markup' => '<h2>Campaigns</h2>'],
      'campaigns' => [
        '#type' => 'table',
        '#header' => ['Campaign', 'Status', 'Source', 'Prospects', 'Proofs', 'Sent', 'Clicks', 'Sales', 'Builds'],
        '#rows' => $rows,
        '#empty' => $this->t('No campaigns have been recorded.'),
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ],
      'pager' => ['#type' => 'pager'],
    ], 'FAMtastic Operations');
  }

  /**
   * Renders one exact campaign with messages, proofs, builds, and job state.
   */
  public function campaign(string $campaign_key): array {
    $campaign = $this->database->select('famtastic_campaign', 'c')
      ->fields('c')
      ->condition('campaign_key', $campaign_key)
      ->execute()
      ->fetchAssoc();
    if (!$campaign) {
      throw new NotFoundHttpException('Campaign not found.');
    }
    $prospectIds = $this->prospectIds($campaign_key);
    $messages = $this->database->select('famtastic_email_message', 'm')
      ->fields('m')
      ->condition('campaign_id', (int) $campaign['id'])
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $messageCounts = $this->groupCounts('famtastic_email_message', 'status', ['campaign_id' => (int) $campaign['id']]);
    $summary = [
      'Prospects' => count($prospectIds),
      'Proofs Ready' => $prospectIds === [] ? 0 : $this->countIn('proof_campaign', 'prospect_id', $prospectIds, ['generation_status' => 'ready']),
      'Messages' => count($messages),
      'Sent' => $this->eventCount((int) $campaign['id'], 'email.sent'),
      'Clicks' => $this->eventCount((int) $campaign['id'], 'email.clicked'),
      'Unsubscribed' => $this->eventCount((int) $campaign['id'], 'consent.unsubscribed'),
      'Bounced' => $this->eventCount((int) $campaign['id'], 'email.bounced'),
      'Build Runs' => $this->count('famtastic_build_run', ['campaign_key' => $campaign_key]),
    ];

    $messageRows = [];
    foreach ($messages as $message) {
      $prospect = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect')->load((int) $message['prospect_id']);
      $proof = $this->loadProof((int) ($message['proof_campaign_id'] ?? 0), (int) $message['prospect_id']);
      $proofLink = '—';
      if ($proof) {
        $variants = $this->loadProofVariants((int) $proof->id());
        $firstVariant = $variants[0] ?? NULL;
        $preview = $firstVariant ? (string) $firstVariant->get('preview_url')->value : '';
        if ($preview !== '') {
          $proofLink = Link::fromTextAndUrl('Open Proof', Url::fromUri((string) $preview, ['attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer']]))->toRenderable();
        }
      }
      $messageRows[] = [
        'business' => ['data' => $prospect ? $prospect->toLink($prospect->label())->toRenderable() : 'Missing prospect'],
        'recipient' => (string) ($message['recipient_address'] ?: ($prospect?->get('public_email')->value ?? '—')),
        'subject' => ['data' => Link::fromTextAndUrl((string) $message['subject'], Url::fromRoute('famtastic_pipeline.operations_message', ['message' => (int) $message['id']]))->toRenderable()],
        'status' => ['data' => ['#markup' => $this->badge((string) $message['status'])]],
        'sent' => $this->date((int) ($message['sent_at'] ?? 0)),
        'proof' => ['data' => $proofLink],
      ];
    }

    $proofRows = [];
    if ($prospectIds !== []) {
      $ids = $this->pipelineEntityTypeManager->getStorage('proof_campaign')->getQuery()
        ->accessCheck(FALSE)
        ->condition('prospect_id', $prospectIds, 'IN')
        ->sort('id', 'DESC')
        ->execute();
      foreach ($this->pipelineEntityTypeManager->getStorage('proof_campaign')->loadMultiple($ids) as $proof) {
        $variants = $this->loadProofVariants((int) $proof->id());
        $links = [];
        foreach ($variants as $variant) {
          $url = (string) $variant->get('preview_url')->value;
          if ($url !== '') {
            $links[] = Link::fromTextAndUrl(strtoupper((string) $variant->get('direction_id')->value), Url::fromUri($url, ['attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer']]))->toString();
          }
        }
        $proofRows[] = [
          'business' => (string) $proof->get('business_name')->value,
          'campaign' => ['data' => $proof->toLink((string) $proof->get('campaign_id')->value)->toRenderable()],
          'generation' => ['data' => ['#markup' => $this->badge((string) $proof->get('generation_status')->value)]],
          'directions' => ['data' => ['#markup' => implode(' · ', $links)]],
          'selected' => (string) ($proof->get('selected_variant')->value ?: '—'),
        ];
      }
    }

    $buildRows = $this->buildRows($campaign_key);
    $jobCounts = $prospectIds === [] ? [] : $this->groupCountsIn('famtastic_job', 'status', 'prospect_id', $prospectIds);
    $statusText = implode(', ', array_map(static fn ($status, $count): string => "$status: $count", array_keys($messageCounts), $messageCounts)) ?: 'none';
    $jobText = implode(', ', array_map(static fn ($status, $count): string => "$status: $count", array_keys($jobCounts), $jobCounts)) ?: 'none';

    return $this->page([
      'back' => Link::fromTextAndUrl('← All Campaigns', Url::fromRoute('famtastic_pipeline.operations'))->toRenderable(),
      'meta' => ['#markup' => '<p class="famtastic-ops__lede">Status: ' . Html::escape((string) $campaign['status']) . ' · Source: ' . Html::escape((string) $campaign['source_filter']) . ' · Message states: ' . Html::escape($statusText) . ' · Job states: ' . Html::escape($jobText) . '</p>'],
      'summary' => $this->metricCards($summary),
      'messages_heading' => ['#markup' => '<h2>Recipient Messages</h2><p>Exact recipient, subject, body snapshot, provider ID, proof link, and lifecycle state.</p>'],
      'messages' => [
        '#type' => 'table',
        '#header' => ['Business', 'Recipient', 'Subject', 'Status', 'Sent', 'Proof'],
        '#rows' => $messageRows,
        '#empty' => $this->t('No messages are associated with this campaign.'),
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ],
      'proofs_heading' => ['#markup' => '<h2>Proofs</h2>'],
      'proofs' => [
        '#type' => 'table',
        '#header' => ['Business', 'Proof Campaign', 'Generation', 'Directions', 'Selected'],
        '#rows' => $proofRows,
        '#empty' => $this->t('No proof campaigns are associated with this campaign.'),
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ],
      'builds_heading' => ['#markup' => '<h2>Build Telemetry</h2><p>Provider, agent, flow, task, prompt, input, output manifest, checksum, and source SHA.</p>'],
      'builds' => [
        '#type' => 'table',
        '#header' => ['Build', 'Provider', 'Agent', 'Flow / Task', 'Status', 'Completed'],
        '#rows' => $buildRows,
        '#empty' => $this->t('No build telemetry has been recorded.'),
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ],
    ], 'Campaign: ' . $campaign_key);
  }

  /**
   * Renders the exact snapshot and lifecycle for one email message.
   */
  public function message(int $message): array {
    $record = $this->database->select('famtastic_email_message', 'm')
      ->fields('m')
      ->condition('id', $message)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      throw new NotFoundHttpException('Message not found.');
    }
    $campaignKey = (string) $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['campaign_key'])
      ->condition('id', (int) $record['campaign_id'])
      ->execute()
      ->fetchField();
    $prospect = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect')->load((int) $record['prospect_id']);
    $facts = [
      ['Campaign', $campaignKey],
      ['Business', $prospect?->label() ?? 'Missing prospect'],
      ['Recipient', (string) ($record['recipient_address'] ?: ($prospect?->get('public_email')->value ?? '—'))],
      ['From', (string) ($record['from_address'] ?: '—')],
      ['Subject', (string) $record['subject']],
      ['Status', (string) $record['status']],
      ['Provider', (string) ($record['provider'] ?: '—')],
      ['Provider Message-ID', (string) ($record['provider_message_id'] ?: '—')],
      ['Template', (string) $record['template_key'] . ' v' . (int) $record['template_version']],
      ['Sent', $this->date((int) ($record['sent_at'] ?? 0))],
    ];
    $events = $this->messageEvents($record);
    return $this->page([
      'back' => Link::fromTextAndUrl('← Campaign', Url::fromRoute('famtastic_pipeline.operations_campaign', ['campaign_key' => $campaignKey]))->toRenderable(),
      'facts' => ['#type' => 'table', '#rows' => $facts, '#attributes' => ['class' => ['famtastic-ops__facts']]],
      'body_heading' => ['#markup' => '<h2>Exact Message Snapshot</h2>'],
      'body' => $this->snapshot((string) ($record['body_snapshot'] ?? ''), 'No body snapshot is available for this historical message.'),
      'events_heading' => ['#markup' => '<h2>Lifecycle Events</h2>'],
      'events' => [
        '#type' => 'table',
        '#header' => ['Event', 'Provider', 'Recorded'],
        '#rows' => $events,
        '#empty' => $this->t('No lifecycle events were found for this message.'),
      ],
    ], 'Email Message #' . $message);
  }

  /**
   * Renders the complete telemetry record for one build.
   */
  public function build(int $build_run): array {
    $record = $this->database->select('famtastic_build_run', 'b')
      ->fields('b')
      ->condition('id', $build_run)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      throw new NotFoundHttpException('Build run not found.');
    }
    $facts = [];
    foreach (['build_key', 'campaign_key', 'flow_key', 'task_key', 'provider', 'agent_name', 'status', 'source_sha', 'artifact_checksum'] as $field) {
      $facts[] = [ucwords(str_replace('_', ' ', $field)), (string) ($record[$field] ?: '—')];
    }
    $facts[] = ['Started', $this->date((int) $record['started_at'])];
    $facts[] = ['Completed', $this->date((int) ($record['completed_at'] ?? 0))];
    return $this->page([
      'back' => Link::fromTextAndUrl('← Campaign', Url::fromRoute('famtastic_pipeline.operations_campaign', ['campaign_key' => $record['campaign_key']]))->toRenderable(),
      'facts' => ['#type' => 'table', '#rows' => $facts, '#attributes' => ['class' => ['famtastic-ops__facts']]],
      'prompt_heading' => ['#markup' => '<h2>Prompt Snapshot</h2>'],
      'prompt' => $this->snapshot((string) ($record['prompt_snapshot'] ?? ''), 'No prompt was recorded.'),
      'input_heading' => ['#markup' => '<h2>Input Snapshot</h2>'],
      'input' => $this->snapshot((string) ($record['input_snapshot'] ?? ''), 'No input snapshot was recorded.'),
      'output_heading' => ['#markup' => '<h2>Output Manifest</h2>'],
      'output' => $this->snapshot((string) ($record['output_manifest'] ?? ''), 'No output manifest was recorded.'),
      'error_heading' => ['#markup' => $record['error'] ? '<h2>Error</h2>' : ''],
      'error' => $record['error'] ? $this->snapshot((string) $record['error'], '') : [],
    ], 'Build Run #' . $build_run);
  }

  private function page(array $content, string $title): array {
    return [
      '#title' => $title,
      '#attached' => ['library' => ['famtastic_pipeline/operations']],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops']],
      ] + $content,
    ];
  }

  private function metricCards(array $metrics): array {
    $cards = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-ops__metrics']]];
    foreach ($metrics as $label => $value) {
      $cards[Html::getClass($label)] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__metric']],
        'value' => ['#markup' => '<strong>' . Html::escape((string) $value) . '</strong>'],
        'label' => ['#markup' => '<span>' . Html::escape($label) . '</span>'],
      ];
    }
    return $cards;
  }

  private function snapshot(string $text, string $empty): array {
    if ($text === '') {
      return ['#markup' => '<p class="famtastic-ops__empty">' . Html::escape($empty) . '</p>'];
    }
    return ['#type' => 'html_tag', '#tag' => 'pre', '#value' => Html::escape($text), '#attributes' => ['class' => ['famtastic-ops__snapshot']]];
  }

  private function badge(string $status): string {
    return '<span class="famtastic-ops__badge famtastic-ops__badge--' . Html::getClass($status) . '">' . Html::escape($status ?: 'unknown') . '</span>';
  }

  private function date(int $timestamp): string {
    return $timestamp > 0 ? $this->dateFormatter->format($timestamp, 'short') : '—';
  }

  private function sourceLabel(string $sourceFilter): string {
    if ($sourceFilter === '') {
      return '—';
    }
    $decoded = json_decode($sourceFilter, TRUE);
    if (is_array($decoded) && is_string($decoded['source'] ?? NULL)) {
      return $decoded['source'];
    }
    return $sourceFilter;
  }

  private function count(string $table, array $conditions = []): int {
    $query = $this->database->select($table, 't');
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  private function countIn(string $table, string $field, array $values, array $conditions = []): int {
    if ($values === []) {
      return 0;
    }
    $query = $this->database->select($table, 't')->condition($field, $values, 'IN');
    foreach ($conditions as $conditionField => $value) {
      $query->condition($conditionField, $value);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  private function groupCounts(string $table, string $groupField, array $conditions = []): array {
    $query = $this->database->select($table, 't');
    $query->addField('t', $groupField);
    $query->addExpression('COUNT(*)', 'total');
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return array_map('intval', $query->groupBy($groupField)->execute()->fetchAllKeyed());
  }

  private function groupCountsIn(string $table, string $groupField, string $filterField, array $values): array {
    if ($values === []) {
      return [];
    }
    $query = $this->database->select($table, 't');
    $query->addField('t', $groupField);
    $query->addExpression('COUNT(*)', 'total');
    $query->condition($filterField, $values, 'IN');
    return array_map('intval', $query->groupBy($groupField)->execute()->fetchAllKeyed());
  }

  private function eventCount(int $campaignId, string $type): int {
    return $this->count('famtastic_event', ['campaign_id' => $campaignId, 'event_type' => $type]);
  }

  private function prospectIds(string $campaignKey): array {
    return array_map('intval', array_values($this->pipelineEntityTypeManager->getStorage('famtastic_prospect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign', $campaignKey)
      ->execute()));
  }

  private function loadProof(int $proofId, int $prospectId): mixed {
    $storage = $this->pipelineEntityTypeManager->getStorage('proof_campaign');
    if ($proofId > 0 && ($proof = $storage->load($proofId))) {
      return $proof;
    }
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('prospect_id', $prospectId)->sort('id', 'DESC')->range(0, 1)->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  private function loadProofVariants(int $proofId): array {
    $storage = $this->pipelineEntityTypeManager->getStorage('proof_variant');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('campaign_id', $proofId)->sort('direction_id')->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  private function buildRows(string $campaignKey): array {
    $records = $this->database->select('famtastic_build_run', 'b')
      ->fields('b')
      ->condition('campaign_key', $campaignKey)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $rows = [];
    foreach ($records as $record) {
      $rows[] = [
        'build' => ['data' => Link::fromTextAndUrl((string) $record['build_key'], Url::fromRoute('famtastic_pipeline.operations_build', ['build_run' => (int) $record['id']]))->toRenderable()],
        'provider' => (string) ($record['provider'] ?: '—'),
        'agent' => (string) ($record['agent_name'] ?: '—'),
        'flow' => (string) $record['flow_key'] . ' / ' . (string) $record['task_key'],
        'status' => ['data' => ['#markup' => $this->badge((string) $record['status'])]],
        'completed' => $this->date((int) ($record['completed_at'] ?? 0)),
      ];
    }
    return $rows;
  }

  private function messageEvents(array $message): array {
    $records = $this->database->select('famtastic_event', 'e')
      ->fields('e', ['event_type', 'provider', 'payload', 'recorded_at'])
      ->condition('prospect_id', (int) $message['prospect_id'])
      ->condition('campaign_id', (int) $message['campaign_id'])
      ->orderBy('recorded_at')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $rows = [];
    foreach ($records as $record) {
      $payload = json_decode((string) $record['payload'], TRUE);
      if ((int) ($payload['message_id'] ?? 0) !== (int) $message['id']) {
        continue;
      }
      $rows[] = [(string) $record['event_type'], (string) ($record['provider'] ?: '—'), $this->date((int) $record['recorded_at'])];
    }
    return $rows;
  }

}
