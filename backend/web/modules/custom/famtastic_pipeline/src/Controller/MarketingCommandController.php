<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Service\PostizChannelsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Staff-only Marketing Command Center: one workspace over the canonical
 * campaign manifest, creative records, lead/attribution joins, outbox mail,
 * and Build DNA. This is NOT a second campaign system and NOT the customer
 * portal - every surface here is operator-facing.
 *
 * Execution truth (rendered on every tab):
 * - Gemini Lite output is valid only when its actual provider receipt is attached.
 * - Antigravity is not a headless worker path.
 * - MuAPI requires a human-approved creative/copy direction before asset fan-out.
 * - Proof approval, creative generation, email acceptance, or a local fixture
 *   is NEVER approval to publish, send marketing email, charge, or launch.
 */
final class MarketingCommandController extends ControllerBase {

  private const TABS = [
    'command' => 'Command',
    'queue' => 'Content queue',
    'calendar' => 'Calendar',
    'channels' => 'Channel health',
    'attribution' => 'Leads & attribution',
    'email' => 'Email center',
    'creative' => 'Creative & media',
    'builddna' => 'Build DNA & recipes',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $pipelineEntityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly TimeInterface $time,
    private readonly PostizChannelsService $postizChannels,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('famtastic_pipeline.postiz_channels'),
    );
  }

  public function command(): array {
    return $this->page('command');
  }

  public function tab(string $tab): array {
    if (!isset(self::TABS[$tab])) {
      throw new NotFoundHttpException('Marketing surface not found.');
    }
    return $this->page($tab);
  }

  public function emailInspect(int $id): array {
    $row = $this->database->select('famtastic_notification_outbox', 'n')
      ->fields('n')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) {
      throw new NotFoundHttpException('Message not found.');
    }
    $rows = [
      ['Message-ID', $row['provider_message_id'] ?: '— (not yet accepted by provider)'],
      ['Status', $this->badge((string) $row['status'])],
      ['Recipient', Html::escape((string) $row['recipient'])],
      ['Category', Html::escape((string) $row['category'])],
      ['Attempts', (int) $row['attempts'] . ' / ' . (int) $row['max_attempts']],
      ['Queued', $this->date((int) $row['created'])],
      ['Last attempt', $this->date((int) $row['changed'])],
      ['Last error', Html::escape((string) ($row['last_error'] ?: '—'))],
    ];
    return $this->page([
      'back' => Link::fromTextAndUrl('← Email center', Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'email']))->toRenderable(),
      'facts' => ['#type' => 'table', '#header' => ['Field', 'Value'], '#rows' => $rows, '#attributes' => ['class' => ['famtastic-ops__table']]],
      'body' => [
        '#type' => 'details',
        '#title' => $this->t('Inspectable body (plain text as queued)'),
        'pre' => ['#markup' => '<pre class="famtastic-email-body">' . Html::escape((string) $row['body']) . '</pre>'],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__actions']],
        'retry' => in_array($row['status'], ['dead_letter', 'retry', 'failed'], TRUE)
          ? ['#type' => 'link', '#title' => $this->t('Retry this message'), '#url' => Url::fromRoute('famtastic_pipeline.notification_retry', ['id' => (int) $row['id']]), '#attributes' => ['class' => ['button', 'button--primary']]]
          : [],
        'back2' => ['#type' => 'link', '#title' => $this->t('Back'), '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'email']), '#attributes' => ['class' => ['button']]],
      ],
    ], 'Email message #' . $id);
  }

  public function buildDnaDetail(int $id): array {
    $run = $this->database->select('famtastic_build_run', 'b')
      ->fields('b')->condition('id', $id)->execute()->fetchAssoc();
    if (!$run) {
      throw new NotFoundHttpException('Build run not found.');
    }
    $rows = [
      ['Build key', Html::escape((string) $run['build_key'])],
      ['Campaign', Html::escape((string) $run['campaign_key'])],
      ['Flow / task', Html::escape((string) $run['flow_key']) . ' / ' . Html::escape((string) $run['task_key'])],
      ['Provider (receipt basis)', Html::escape((string) $run['provider'])],
      ['Agent', Html::escape((string) $run['agent_name'])],
      ['Status', $this->badge((string) $run['status'])],
      ['Source SHA-256', Html::escape((string) $run['source_sha'])],
      ['Created', $this->date((int) $run['created'])],
    ];
    $snapshots = [];
    foreach (['prompt_snapshot' => 'Prompt artifact', 'input_snapshot' => 'Inputs (evidence basis)', 'output_manifest' => 'Outputs / artifact manifest'] as $key => $label) {
      $value = (string) ($run[$key] ?? '');
      $snapshots[$key] = [
        '#type' => 'details',
        '#title' => $label,
        'pre' => ['#markup' => '<pre class="famtastic-email-body">' . Html::escape(mb_strimwidth($value, 0, 4000, '…')) . '</pre>'],
        '#open' => $key === 'prompt_snapshot',
      ];
    }
    return $this->page([
      'back' => Link::fromTextAndUrl('← Build DNA', Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'builddna']))->toRenderable(),
      'truth' => ['#markup' => $this->executionTruth()],
      'facts' => ['#type' => 'table', '#header' => ['Field', 'Value'], '#rows' => $rows, '#attributes' => ['class' => ['famtastic-ops__table']]],
      'snapshots' => $snapshots,
    ], 'Build DNA #' . $id . ' — ' . $run['build_key']);
  }

  private function page(string $tab): array {
    $tabs = [];
    foreach (self::TABS as $id => $label) {
      $tabs['t_' . $id] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $id === 'command' ? Url::fromRoute('famtastic_pipeline.marketing') : Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => $id]),
        '#attributes' => ['class' => ['famtastic-mkt__tab', $tab === $id ? 'active' : '']],
      ];
    }
    $content = [
      'truth' => ['#markup' => $this->executionTruth()],
      'tabs' => ['#type' => 'container', '#attributes' => ['class' => ['famtastic-mkt__tabs']], 'items' => $tabs],
    ] + match ($tab) {
      'command' => $this->tabCommand(),
      'queue' => $this->tabQueue(),
      'calendar' => $this->tabCalendar(),
      'channels' => $this->tabChannels(),
      'attribution' => $this->tabAttribution(),
      'email' => $this->tabEmail(),
      'creative' => $this->tabCreative(),
      'builddna' => $this->tabBuildDna(),
      default => [],
    };
    return $this->shell($content, self::TABS[$tab]);
  }

  /** The non-negotiable execution truth banner. */
  private function executionTruth(): string {
    return '<div class="famtastic-mkt__truth"><strong>Execution truth</strong><ul>'
      . '<li>Gemini Lite output is valid only when its actual provider receipt is attached.</li>'
      . '<li>Antigravity is not a headless worker path.</li>'
      . '<li>MuAPI requires a human-approved creative/copy direction before asset fan-out.</li>'
      . '<li>Proof approval, creative generation, email acceptance, or a local fixture is <em>never</em> approval to publish, send marketing email, charge, or launch.</li>'
      . '</ul></div>';
  }

  private function shell(array $content, string $title): array {
    return [
      '#title' => 'Marketing Command Center — ' . $title,
      '#attached' => ['library' => ['famtastic_pipeline/operations']],
      'content' => ['#type' => 'container', '#attributes' => ['class' => ['famtastic-ops famtastic-mkt']]] + $content,
    ];
  }

  private function kpis(): array {
    $gatesOpen = (int) $this->database->select('famtastic_social_record', 'r')
      ->condition('approval_content', 0)->countQuery()->execute()->fetchField();
    $drafts = (int) $this->database->select('famtastic_support_draft', 'd')
      ->condition('status', 'pending')->countQuery()->execute()->fetchField();
    $dead = (int) $this->database->select('famtastic_notification_outbox', 'n')
      ->condition('status', 'dead_letter')->countQuery()->execute()->fetchField();
    $revenueQuery = $this->database->select('famtastic_commerce_fulfillment', 'f')
      ->condition('f.fulfilled_at', $this->time->getRequestTime() - 2592000, '>=')
      ->condition('f.status', 'fulfilled');
    $revenueQuery->addExpression('SUM(f.amount_minor)', 't');
    $revenue = (int) $revenueQuery->execute()->fetchField();
    return [
      'records' => $this->count('famtastic_social_record'),
      'gates_open' => $gatesOpen,
      'drafts' => $drafts,
      'dead' => $dead,
      'revenue_minor' => $revenue,
    ];
  }

  private function tabCommand(): array {
    $k = $this->kpis();
    $nextGate = $this->database->select('famtastic_social_record', 'r')
      ->fields('r', ['content_id', 'day', 'moment'])
      ->condition('approval_content', 0)
      ->orderBy('r.day')->orderBy('r.id')->range(0, 1)->execute()->fetchAssoc();
    $cards = [
      ['Campaign records', (string) $k['records'], 'Canonical manifest records under gate control', 'neutral'],
      ['Content gates open', (string) $k['gates_open'], 'Content review decisions waiting on you', $k['gates_open'] > 0 ? 'attention' : 'good'],
      ['Support drafts', (string) $k['drafts'], 'L0 replies awaiting approve/reject', $k['drafts'] > 0 ? 'attention' : 'good'],
      ['Dead letters', (string) $k['dead'], 'Must always be zero', $k['dead'] > 0 ? 'danger' : 'good'],
      ['Revenue 30d', '$' . number_format($k['revenue_minor'] / 100, 2), 'Paid Commerce totals, last 30 days', 'good'],
    ];
    $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-command__pulse']]];
    foreach ($cards as $index => [$label, $value, $detail, $tone]) {
      $build['c_' . $index] = ['#markup' => '<article class="famtastic-command__pulse-card famtastic-command__pulse-card--' . Html::getClass($tone) . '"><span>' . Html::escape($label) . '</span><strong>' . Html::escape($value) . '</strong><p>' . Html::escape($detail) . '</p></article>'];
    }
    $actions = [
      '#type' => 'container',
      '#attributes' => ['class' => ['famtastic-ops__actions', 'famtastic-command__actions']],
      'sync' => [
        '#type' => 'link',
        '#title' => $this->t('⚡ Sync Manifest Records'),
        '#url' => Url::fromRoute('famtastic_pipeline.social_records_sync'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'queue' => [
        '#type' => 'link',
        '#title' => $this->t('Open Content Queue →'),
        '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'queue']),
        '#attributes' => ['class' => ['button']],
      ],
    ];
    $oneGate = ['#markup' => '<div class="famtastic-mkt__onegate"><strong>One gate needs you</strong><p>' . ($nextGate
      ? 'Content review is open for <code>' . Html::escape($nextGate['content_id']) . '</code> (day ' . (int) $nextGate['day'] . ', ' . Html::escape((string) $nextGate['moment']) . '). Message, media, and public release remain separate decisions.'
      : 'No content gates are open. Clean.') . '</p>' . Link::fromTextAndUrl('Review content record →', Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'queue']))->toRenderable() . '</div>'];
    return ['actions' => $actions, 'onegate' => $oneGate, 'cards' => $build];
  }

  private function tabQueue(): array {
    $rows = [];
    foreach ($this->database->select('famtastic_social_record', 'r')->fields('r')
      ->orderBy('r.day')->orderBy('r.id')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $cells = [];
      foreach (['content' => 'approval_content', 'media' => 'approval_media', 'publish' => 'approval_publish'] as $gate => $col) {
        $on = (int) $record[$col] === 1;
        $cells[] = ['data' => [
          '#markup' => '<span class="famtastic-ops__badge famtastic-ops__badge--' . ($on ? 'good' : 'off') . '">' . ($on ? '✓' : '—') . '</span> ',
          'link' => ['data' => $this->linkCell(Link::fromTextAndUrl($on ? 'revoke' : 'approve', Url::fromRoute('famtastic_pipeline.social_record_gate', ['content_id' => $record['content_id'], 'gate' => $gate, 'direction' => $on ? 'revoke' : 'approve'])))],
        ]];
      }
      $batchLink = Link::fromTextAndUrl('Approve Day ' . $record['day'], Url::fromRoute('famtastic_pipeline.social_record_batch_gate', ['day' => (int) $record['day'], 'gate' => 'all', 'direction' => 'approve']));
      $rows[] = [
        (int) $record['day'], (string) $record['moment'], Html::escape((string) $record['content_id']),
        (string) $record['scheduled_time_et'], Html::escape((string) $record['promise']),
        ['data' => ['#markup' => $this->badge((string) $record['state'])]],
        $cells[0], $cells[1], $cells[2],
        (string) ($record['postiz_draft_id'] ?: '—'),
        ['data' => $this->linkCell($batchLink)],
      ];
    }
    $page = $this->table('Content queue — first day of the actual campaign spine', 'Each row is the one content ID that must travel through copy, media, channel, UTM, evidence, and results. Draft-first: publishing stays off until the exact item is approved.', ['Day', 'Moment', 'Content ID', 'ET', 'Promise', 'State', 'Content', 'Media', 'Publish', 'Draft ID', 'Batch Day'], $rows, 'No records imported yet.');
    $page['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['famtastic-ops__actions']],
      'sync' => [
        '#type' => 'link',
        '#title' => $this->t('⚡ Sync Manifest Records'),
        '#url' => Url::fromRoute('famtastic_pipeline.social_records_sync'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
    $page['actions']['#weight'] = -20;
    return $page;
  }

  private function tabCalendar(): array {
    $rows = [];
    foreach ($this->database->select('famtastic_social_record', 'r')->fields('r')
      ->orderBy('r.day')->orderBy('r.id')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $on = (int) $record['approval_content'] + (int) $record['approval_media'] + (int) $record['approval_publish'];
      $batchLink = Link::fromTextAndUrl('Approve Day ' . $record['day'] . ' →', Url::fromRoute('famtastic_pipeline.social_record_batch_gate', ['day' => (int) $record['day'], 'gate' => 'all', 'direction' => 'approve']));
      $rows[] = [
        (int) $record['day'],
        ucfirst((string) $record['moment']),
        Html::escape((string) $record['content_id']),
        (string) $record['scheduled_time_et'],
        $on . '/3',
        ['data' => ['#markup' => $this->badge((string) $record['state'])]],
        ['data' => $this->linkCell($batchLink)],
      ];
    }
    $page = $this->table('Calendar — live gate state', 'Gate counts per record. Days with no imported records show nothing rather than invented numbers.', ['Day', 'Moment', 'Content ID', 'ET', 'Gates', 'State', 'Batch Approval'], $rows, 'No records imported yet.');
    $page['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['famtastic-ops__actions']],
      'sync' => [
        '#type' => 'link',
        '#title' => $this->t('⚡ Sync Manifest Records'),
        '#url' => Url::fromRoute('famtastic_pipeline.social_records_sync'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
    $page['actions']['#weight'] = -20;
    return $page;
  }

  private function tabChannels(): array {
    $snapshot = $this->postizChannels->channels();
    $cards = [];
    if (!$snapshot['configured']) {
      $cards[] = ['Channel health', 'Not configured', 'Set FAMTASTIC_POSTIZ_API_KEY + base URL to show live channel state.', 'neutral'];
    }
    elseif (!$snapshot['reachable']) {
      $cards[] = ['Channel health', 'Unreachable', $snapshot['error'], 'danger'];
    }
    else {
      foreach ($snapshot['platforms'] as $platform) {
        $cards[] = [ucfirst($platform['identifier']) . ' · ' . $platform['name'], ucfirst($platform['state']), $platform['detail'], $platform['state'] === 'connected' ? 'good' : 'attention'];
      }
    }
    $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-command__pulse']]];
    foreach ($cards as $index => [$label, $value, $detail, $tone]) {
      $build['c_' . $index] = ['#markup' => '<article class="famtastic-command__pulse-card famtastic-command__pulse-card--' . Html::getClass($tone) . '"><span>' . Html::escape($label) . '</span><strong>' . Html::escape($value) . '</strong><p>' . Html::escape($detail) . '</p></article>'];
    }
    return ['cards' => $build];
  }

  /**
   * Attribution v2: content-grain join (social record ↔ prospect utm_json)
   * above the original honest campaign-grain join.
   */
  private function tabAttribution(): array {
    $rows = [];
    $query = $this->database->select('famtastic_prospect', 'p');
    $query->leftJoin('famtastic_project_request', 'r', 'r.prospect_id = p.id');
    $query->leftJoin('famtastic_commerce_fulfillment', 'f', 'f.prospect_id = p.id AND f.status = \'fulfilled\'');
    $query->fields('p', ['campaign', 'source', 'created']);
    $query->addExpression('COUNT(DISTINCT p.id)', 'leads');
    $query->addExpression('COUNT(DISTINCT r.id)', 'requests');
    $query->addExpression('SUM(f.amount_minor)', 'revenue');
    $query->groupBy('p.campaign');
    $query->groupBy('p.source');
    $query->orderBy('revenue', 'DESC')->range(0, 25);
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $revenue = (int) ($record['revenue'] ?? 0);
      $rows[] = [
        Html::escape((string) ($record['campaign'] ?: '—')),
        Html::escape((string) ($record['source'] ?: '—')),
        (int) $record['leads'],
        (int) $record['requests'],
        $revenue > 0 ? '$' . number_format($revenue / 100, 2) : '—',
      ];
    }
    return [
      'content_grain' => $this->table(
        'Leads & attribution — content grain',
        'Social content ID → leads whose captured utm_content matches it → website requests → paid revenue. Live join over prospect attribution snapshots (utm persisted at capture since update 8036); zero-lead rows show exactly which posts produced nothing.',
        ['Content ID', 'Day', 'Leads', 'Requests', 'Paid revenue'],
        $this->contentGrainRows(),
        'No social records imported yet.',
      ),
      'campaign_grain' => $this->table(
        'Leads & attribution — campaign/source grain',
        'Campaign/source → leads → website requests → paid order totals.',
        ['Campaign', 'Source', 'Leads', 'Requests', 'Paid revenue'],
        $rows,
        'No attributed leads recorded yet.',
      ),
    ];
  }

  /**
   * Joins famtastic_social_record.content_id to prospect utm snapshots.
   *
   * The utm_content match is resolved in PHP over the bounded snapshot set so
   * the query stays portable across MySQL production and SQLite local runs
   * (no JSON_EXTRACT / CONCAT dependence).
   */
  private function contentGrainRows(): array {
    $leadsByContent = [];
    $snapshots = $this->database->select('famtastic_prospect', 'p')
      ->fields('p', ['id', 'utm_json'])
      ->isNotNull('p.utm_json')
      ->execute();
    foreach ($snapshots as $snapshot) {
      $decoded = json_decode((string) $snapshot->utm_json, TRUE);
      $contentId = is_array($decoded) ? mb_substr(trim((string) ($decoded['utm_content'] ?? '')), 0, 64) : '';
      if ($contentId !== '') {
        $leadsByContent[$contentId][] = (int) $snapshot->id;
      }
    }
    if (!$leadsByContent && !$this->count('famtastic_social_record')) {
      return [];
    }
    $rows = [];
    foreach ($this->database->select('famtastic_social_record', 's')
      ->fields('s', ['content_id', 'day'])
      ->orderBy('s.day')->orderBy('s.id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $leadIds = $leadsByContent[(string) $record['content_id']] ?? [];
      $requests = 0;
      $revenue = 0;
      if ($leadIds) {
        $requestQuery = $this->database->select('famtastic_project_request', 'r');
        $requestQuery->addExpression('COUNT(DISTINCT r.id)', 'c');
        $requests = (int) $requestQuery->condition('r.prospect_id', $leadIds, 'IN')->execute()->fetchField();
        $revenueQuery = $this->database->select('famtastic_commerce_fulfillment', 'f');
        $revenueQuery->addExpression('SUM(f.amount_minor)', 't');
        $revenue = (int) $revenueQuery
          ->condition('f.prospect_id', $leadIds, 'IN')
          ->condition('f.status', 'fulfilled')
          ->execute()->fetchField();
      }
      $rows[] = [
        Html::escape((string) $record['content_id']),
        (int) $record['day'],
        count($leadIds),
        $requests,
        $revenue > 0 ? '$' . number_format($revenue / 100, 2) : '—',
      ];
    }
    return $rows;
  }

  private function tabEmail(): array {
    $queuedCount = (int) $this->database->select('famtastic_notification_outbox', 'n')->condition('status', 'queued')->countQuery()->execute()->fetchField();
    $sentCount = (int) $this->database->select('famtastic_notification_outbox', 'n')->condition('status', 'sent')->countQuery()->execute()->fetchField();
    $retryCount = (int) $this->database->select('famtastic_notification_outbox', 'n')->condition('status', ['retry', 'dead_letter', 'failed'], 'IN')->countQuery()->execute()->fetchField();

    $rows = [];
    foreach ($this->database->select('famtastic_notification_outbox', 'n')->extend(\Drupal\Core\Database\Query\PagerSelectExtender::class)
      ->fields('n', ['id', 'category', 'recipient', 'subject', 'status', 'attempts', 'provider_message_id', 'changed'])
      ->orderBy('changed', 'DESC')->limit(25)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $rows[] = [
        (int) $record['id'],
        Html::escape((string) $record['recipient']),
        Html::escape((string) $record['subject']),
        ['data' => ['#markup' => $this->badge((string) $record['status'])]],
        Html::escape((string) ($record['provider_message_id'] ?: '—')),
        ['data' => $this->linkCell(Link::fromTextAndUrl('Inspect', Url::fromRoute('famtastic_pipeline.marketing.email_inspect', ['id' => (int) $record['id']])))],
      ];
    }
    $page = $this->table('Email center — inspectable, triageable', 'Every queued message with its body, provider message-ID, and state. Retry is one click. Status counts: ' . $sentCount . ' sent · ' . $queuedCount . ' queued · ' . $retryCount . ' needing attention.', ['ID', 'Recipient', 'Subject', 'Status', 'Provider message-ID', ''], $rows, 'No notifications queued.');
    $page['test'] = ['#markup' => '<p class="famtastic-ops__lede">Owner-test flow: use <code>drush php:script backend/scripts/send-owner-test-email.php</code> (memory-safe, owner address only) or retry any failed row above.</p>'];
    return $page;
  }

  private function tabCreative(): array {
    $rows = [];
    foreach ($this->database->select('famtastic_social_record', 'r')->fields('r')
      ->orderBy('r.day')->orderBy('r.id')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $variants = json_decode((string) $record['asset_variants'], TRUE) ?: [];
      $evidence = [];
      $rows[] = [
        Html::escape((string) $record['content_id']),
        (int) $record['day'],
        Html::escape(implode(' + ', $variants) ?: '—'),
        ['data' => ['#markup' => $this->badge((string) $record['state'])]],
        ((int) $record['approval_content'] ? '✓' : '—') . ' / ' . ((int) $record['approval_media'] ? '✓' : '—') . ' / ' . ((int) $record['approval_publish'] ? '✓' : '—'),
        Html::escape((string) ($record['postiz_draft_id'] ?: '—')),
      ];
    }
    return $this->table('Creative & media library', 'Each asset carries its content record, crop variants, and its own content/media/publish gates. Provider receipts and accessibility/rights metadata attach at generation time and appear in evidence entries; MuAPI fan-out requires a human-approved direction first.', ['Content ID', 'Day', 'Crops', 'State', 'Gates (c/m/p)', 'Draft ID'], $rows, 'No creative records imported.');
  }

  private function tabBuildDna(): array {
    $rows = [];
    foreach ($this->database->select('famtastic_build_run', 'b')->extend(\Drupal\Core\Database\Query\PagerSelectExtender::class)
      ->fields('b', ['id', 'build_key', 'campaign_key', 'provider', 'agent_name', 'status', 'source_sha', 'created'])
      ->orderBy('b.created', 'DESC')->limit(25)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $run) {
      $rows[] = [
        (int) $run['id'],
        Html::escape((string) $run['build_key']),
        Html::escape((string) $run['campaign_key']),
        Html::escape((string) $run['provider']),
        Html::escape((string) $run['agent_name']),
        ['data' => ['#markup' => $this->badge((string) $run['status'])]],
        Html::escape(substr((string) $run['source_sha'], 0, 12) ?: '—'),
        ['data' => $this->linkCell(Link::fromTextAndUrl('Inspect DNA', Url::fromRoute('famtastic_pipeline.marketing.build_dna', ['id' => (int) $run['id']])))],
      ];
    }
    return $this->table('Build DNA & recipes', 'Every build run with its brief basis, inputs, provider/model receipt, prompt artifact, hashes, outputs, and status. Execution truth applies: a receipt-less Gemini Lite output is not valid evidence, and no build output is launch approval.', ['#', 'Build key', 'Campaign', 'Provider', 'Agent', 'Status', 'Source SHA', ''], $rows, 'No build runs recorded.');
  }

  private function table(string $title, string $description, array $header, array $rows, string $empty): array {
    return [
      'heading' => ['#markup' => '<h2>' . Html::escape($title) . '</h2><p class="famtastic-ops__lede">' . Html::escape($description) . '</p>'],
      'table' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__table-scroll', 'famtastic-ops__table-scroll--wide']],
        't' => ['#type' => 'table', '#header' => $header, '#rows' => $rows, '#empty' => $empty, '#attributes' => ['class' => ['famtastic-ops__table', 'famtastic-ops__table--readable']]],
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

  private function badge(string $status): string {
    return '<span class="famtastic-ops__badge famtastic-ops__badge--' . mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'x') . '">' . Html::escape($status) . '</span>';
  }

  private function linkCell(Link $link): array {
    return ['data' => $link->toRenderable()];
  }

  private function date(int $stamp): string {
    return $stamp > 0 ? $this->dateFormatter->format($stamp, 'short') : '—';
  }

  private function count(string $table, array $conditions = []): int {
    $query = $this->database->select($table, 't');
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  private function countIn(string $table, string $field, array $values): int {
    return (int) $this->database->select($table, 't')
      ->condition($field, $values, 'IN')->countQuery()->execute()->fetchField();
  }

}
