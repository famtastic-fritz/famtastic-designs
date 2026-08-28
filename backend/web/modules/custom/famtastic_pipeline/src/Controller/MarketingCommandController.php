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
use Symfony\Component\HttpFoundation\Response;
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
    'dispatch' => 'Daily Dispatch',
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
      'dispatch' => $this->tabDispatch(),
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

  /**
   * Safely serves campaign image assets to authenticated staff.
   */
  public function campaignAsset(string $filename): Response {
    if (!preg_match('/^[a-zA-Z0-9._-]+\.(png|jpg|jpeg|webp|mp4)$/', $filename)) {
      throw new NotFoundHttpException('Invalid asset name.');
    }
    $modulePath = __DIR__ . '/../../assets/campaign/' . $filename;
    $candidates = [
      $modulePath,
      \Drupal::root() . '/modules/custom/famtastic_pipeline/assets/campaign/' . $filename,
      \Drupal::root() . '/../marketing/campaigns/55-cents-17-day/assets/' . $filename,
      dirname(\Drupal::root(), 2) . '/marketing/campaigns/55-cents-17-day/assets/' . $filename,
      dirname(\Drupal::root()) . '/marketing/campaigns/55-cents-17-day/assets/' . $filename,
      \Drupal::root() . '/sites/default/files/marketing_assets/' . $filename,
    ];
    $found = NULL;
    foreach ($candidates as $path) {
      if (file_exists($path)) {
        $found = $path;
        break;
      }
    }
    if (!$found) {
      throw new NotFoundHttpException('Asset file not found.');
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = match ($ext) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      'jpg', 'jpeg' => 'image/jpeg',
      'mp4' => 'video/mp4',
      default => 'application/octet-stream',
    };
    $content = (string) file_get_contents($found);
    return new Response($content, 200, [
      'Content-Type' => $mime,
      'Cache-Control' => 'private, max-age=3600',
    ]);
  }

  /**
   * Daily Social Dispatch tab: One unified multi-channel day-by-day screen.
   */
  private function tabDispatch(): array {
    $request = \Drupal::request();
    $selectedDay = max(1, min(17, (int) $request->query->get('day', 1)));

    $totalRecords = $this->count('famtastic_social_record');
    if ($totalRecords === 0) {
      return [
        'heading' => ['#markup' => '<h2>Daily Social Dispatch</h2><p class="famtastic-ops__lede">No campaign records imported yet into database.</p>'],
        'sync' => [
          '#type' => 'link',
          '#title' => $this->t('⚡ Sync 17-Day Manifest Records'),
          '#url' => Url::fromRoute('famtastic_pipeline.social_records_sync'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ];
    }

    // Day navigation pills (Days 1 to 17)
    $dayButtons = [];
    for ($d = 1; $d <= 17; $d++) {
      $dayPublishApproved = (int) $this->database->select('famtastic_social_record', 'r')
        ->condition('day', $d)
        ->condition('approval_publish', 1)
        ->countQuery()->execute()->fetchField();
      $isCurrent = $d === $selectedDay;
      $dayButtons[] = [
        '#type' => 'link',
        '#title' => 'Day ' . $d . ($dayPublishApproved === 4 ? ' ✓' : ($dayPublishApproved > 0 ? ' (' . $dayPublishApproved . '/4)' : '')),
        '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'dispatch'], ['query' => ['day' => $d]]),
        '#attributes' => [
          'class' => ['button', $isCurrent ? 'button--primary' : 'button--secondary'],
          'style' => 'margin: 0 4px 6px 0; font-size: 0.85rem;' . ($isCurrent ? ' background: #7cfc00; color: #000; font-weight: bold;' : ''),
        ],
      ];
    }

    $records = $this->database->select('famtastic_social_record', 'r')
      ->fields('r')
      ->condition('day', $selectedDay)
      ->orderBy('scheduled_time_et', 'ASC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $cardsHtml = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin: 1.5rem 0;">';

    $momentIcons = [
      'teach' => '🌅',
      'challenge' => '☀️',
      'prove' => '🌆',
      'invite' => '🌙',
    ];

    foreach ($records as $rec) {
      $cid = Html::escape((string) $rec['content_id']);
      $moment = (string) $rec['moment'];
      $icon = $momentIcons[$moment] ?? '📌';
      $time = (string) $rec['scheduled_time_et'];
      $promise = Html::escape((string) $rec['promise']);
      $theme = ucfirst(Html::escape((string) $rec['theme']));
      $state = Html::escape((string) $rec['state']);

      $contentGate = (int) $rec['approval_content'] === 1;
      $mediaGate = (int) $rec['approval_media'] === 1;
      $publishGate = (int) $rec['approval_publish'] === 1;

      $cLink = Url::fromRoute('famtastic_pipeline.social_record_gate', ['content_id' => $rec['content_id'], 'gate' => 'content', 'direction' => $contentGate ? 'revoke' : 'approve'])->toString();
      $mLink = Url::fromRoute('famtastic_pipeline.social_record_gate', ['content_id' => $rec['content_id'], 'gate' => 'media', 'direction' => $mediaGate ? 'revoke' : 'approve'])->toString();
      $pLink = Url::fromRoute('famtastic_pipeline.social_record_gate', ['content_id' => $rec['content_id'], 'gate' => 'publish', 'direction' => $publishGate ? 'revoke' : 'approve'])->toString();

      $imgSrc4x5 = Url::fromRoute('famtastic_pipeline.marketing.asset', ['filename' => $rec['content_id'] . '.4x5.png'])->toString();
      $imgSrc9x16 = Url::fromRoute('famtastic_pipeline.marketing.asset', ['filename' => $rec['content_id'] . '.9x16.png'])->toString();

      $videoMap = [
        'teach' => 'offer-launch-55-cents-proof.mp4',
        'challenge' => 'service-education-ai-agent-proof.mp4',
        'prove' => 'customer-retention-growth-review-proof.mp4',
        'invite' => 'famtastic-55-cents-remotion.mp4',
      ];
      $videoFile = $videoMap[$moment] ?? 'offer-launch-55-cents-proof.mp4';
      $videoSrc = Url::fromRoute('famtastic_pipeline.marketing.asset', ['filename' => $videoFile])->toString();

      $momentCaptions = [
        'teach' => "Stop renting your website. Start owning your digital front door. ⚡\n\nFor less than 55 cents a day ($199/year), you get:\n✅ Fast SSD Cloud Hosting & SSL included\n✅ Custom Domain Registration (.com/.org/.net)\n✅ Client Portal & Interactive Project Hub\n✅ 100% Code Ownership — zero monthly SaaS traps.\n\nScan your local market in 20 seconds at famtasticdesigns.com.",
        'challenge' => "The hidden cost of generic website builders: Paying $30–$70/mo forever with zero code ownership and locked-in templates.\n\nCompare that to owning your custom digital engine with 1-year hosting included for $199 flat (~55¢/day).\n\nSee what your competitors are doing at famtasticdesigns.com.",
        'prove' => "Proof in action: How local businesses eliminate SaaS recurring taxes and get a custom, lightning-fast digital engine built to their exact workflow.\n\nExplore our interactive Solution Finder at famtasticdesigns.com.",
        'invite' => "Ready for a website that actually works for your business?\n\nLock in our $199 Web Basics package with a 30-day price hold. Includes 1-year cloud hosting, custom domain, and dedicated client command center.\n\nStart now at famtasticdesigns.com.",
      ];
      $captionText = $momentCaptions[$moment] ?? (string) $rec['promise'];

      $cardsHtml .= '
        <article style="border: 1px solid #2d382d; border-radius: 14px; background: #101510; padding: 1.25rem; display: flex; flex-direction: column; box-shadow: 0 4px 16px rgba(0,0,0,0.4);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #7cfc00; font-weight: 800;">' . $icon . ' ' . $time . ' ET · ' . ucfirst($moment) . '</span>
            <span class="famtastic-ops__badge famtastic-ops__badge--' . mb_strtolower($state) . '">' . $state . '</span>
          </div>
          <h3 style="margin: 0.2rem 0 0.5rem; font-size: 1.15rem; color: #fff; line-height: 1.3;">' . $promise . '</h3>
          <small style="color: #8e988e; margin-bottom: 0.75rem; display: block;">ID: <code>' . $cid . '</code> · Theme: ' . $theme . '</small>
          
          <!-- Channel Matrix Badges -->
          <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 0.75rem;">
            <span style="background: #1877F2; color: #fff; padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">Facebook</span>
            <span style="background: #E4405F; color: #fff; padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">Instagram</span>
            <span style="background: #222; border: 1px solid #555; color: #fff; padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">X</span>
            <span style="background: #FF0000; color: #fff; padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">YouTube Shorts</span>
            <span style="background: #00f2fe; color: #000; padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">TikTok</span>
          </div>

          <!-- Dual Media Preview (Feed Image + Shorts Video) -->
          <div style="background: #050805; border-radius: 10px; padding: 0.75rem; border: 1px solid #1c241c; margin-bottom: 0.75rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; align-items: start;">
              <!-- 4x5 Image Preview -->
              <div style="text-align: center;">
                <div style="font-size: 0.7rem; color: #7cfc00; font-weight: 700; margin-bottom: 4px;">📷 Feed Image (FB / IG / X)</div>
                <img src="' . $imgSrc4x5 . '" alt="' . $cid . '" style="width: 100%; height: 160px; object-fit: contain; border-radius: 6px; background: #000; border: 1px solid #222;" onerror="this.src=\'' . $imgSrc9x16 . '\';" />
                <div style="margin-top: 4px; font-size: 0.68rem;">
                  <a href="' . $imgSrc4x5 . '" target="_blank" style="color: #8e988e; text-decoration: underline;">4x5 Full</a> · 
                  <a href="' . $imgSrc9x16 . '" target="_blank" style="color: #8e988e; text-decoration: underline;">9x16 Full</a>
                </div>
              </div>
              <!-- 9x16 Video Preview -->
              <div style="text-align: center;">
                <div style="font-size: 0.7rem; color: #ff4d4d; font-weight: 700; margin-bottom: 4px;">🎬 Video Reel (Shorts / TikTok)</div>
                <video controls preload="metadata" playsinline style="width: 100%; height: 160px; object-fit: cover; border-radius: 6px; background: #000; border: 1px solid #222;" poster="' . $imgSrc9x16 . '">
                  <source src="' . $videoSrc . '" type="video/mp4">
                  Video preview available
                </video>
                <div style="margin-top: 4px; font-size: 0.68rem;">
                  <a href="' . $videoSrc . '" target="_blank" style="color: #8e988e; text-decoration: underline;">Open MP4 Video ↗</a>
                </div>
              </div>
            </div>
          </div>

          <details style="margin: 0.25rem 0 0.75rem; background: #080c08; border: 1px solid #1e261e; border-radius: 8px; padding: 0.5rem;" open>
            <summary style="font-size: 0.78rem; font-weight: 700; color: #aab2aa; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em;">📝 Multi-Channel Copy &amp; Script</summary>
            <pre style="margin-top: 0.5rem; font-family: inherit; font-size: 0.8rem; color: #c4d0c4; white-space: pre-wrap; word-break: break-word; line-height: 1.45;">' . Html::escape($captionText) . '</pre>
          </details>

          <div style="margin-top: auto; padding-top: 0.75rem; border-top: 1px solid #222b22;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #aab2aa; margin-bottom: 0.4rem; text-transform: uppercase;">Operator Approvals</div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.4rem; text-align: center;">
              <a href="' . $cLink . '" style="padding: 0.45rem 0.2rem; border-radius: 6px; font-size: 0.75rem; text-decoration: none; font-weight: 700; background: ' . ($contentGate ? 'rgba(124,252,0,0.18); color: #7cfc00; border: 1px solid #7cfc00;' : 'rgba(255,255,255,0.05); color: #888; border: 1px solid #333;') . '">
                ' . ($contentGate ? '✓ Copy' : '○ Copy') . '
              </a>
              <a href="' . $mLink . '" style="padding: 0.45rem 0.2rem; border-radius: 6px; font-size: 0.75rem; text-decoration: none; font-weight: 700; background: ' . ($mediaGate ? 'rgba(124,252,0,0.18); color: #7cfc00; border: 1px solid #7cfc00;' : 'rgba(255,255,255,0.05); color: #888; border: 1px solid #333;') . '">
                ' . ($mediaGate ? '✓ Media' : '○ Media') . '
              </a>
              <a href="' . $pLink . '" style="padding: 0.45rem 0.2rem; border-radius: 6px; font-size: 0.75rem; text-decoration: none; font-weight: 700; background: ' . ($publishGate ? 'rgba(124,252,0,0.18); color: #7cfc00; border: 1px solid #7cfc00;' : 'rgba(255,255,255,0.05); color: #888; border: 1px solid #333;') . '">
                ' . ($publishGate ? '✓ Publish' : '○ Publish') . '
              </a>
            </div>
          </div>
        </article>
      ';
    }
    $cardsHtml .= '</div>';

    $batchApproveUrl = Url::fromRoute('famtastic_pipeline.social_record_batch_gate', ['day' => $selectedDay, 'gate' => 'all', 'direction' => 'approve'])->toString();
    $batchRevokeUrl = Url::fromRoute('famtastic_pipeline.social_record_batch_gate', ['day' => $selectedDay, 'gate' => 'all', 'direction' => 'revoke'])->toString();

    $headerHtml = '
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
        <div>
          <h2 style="margin: 0; font-size: 1.5rem;">Daily Social Dispatch — Day ' . $selectedDay . ' of 17</h2>
          <p class="famtastic-ops__lede" style="margin: 0.25rem 0 0;">All scheduled multi-channel content moments, creative visual assets, and gate decisions for Day ' . $selectedDay . '.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
          <a href="' . $batchApproveUrl . '" class="button button--primary" style="background: #7cfc00; color: #000; font-weight: 800;">⚡ Approve Entire Day ' . $selectedDay . '</a>
          <a href="' . $batchRevokeUrl . '" class="button">Revoke Day ' . $selectedDay . '</a>
        </div>
      </div>
    ';

    $architectureGuide = '
      <div style="margin-top: 2rem; padding: 1.5rem; border-radius: 14px; background: #0c100c; border: 1px solid #222b22;">
        <h3 style="margin: 0 0 0.5rem; color: #7cfc00; font-size: 1.1rem;">🛠️ Social Media Build &amp; Multi-Channel Publishing Architecture</h3>
        <p style="color: #9da79d; font-size: 0.88rem; margin: 0 0 1rem; line-height: 1.5;">How our agentic build skills, assets, and publishing channels operate together across Facebook, YouTube, TikTok, Instagram, and X:</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
          <div style="padding: 0.75rem; border-radius: 10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);">
            <strong style="color: #fff; font-size: 0.85rem; display: block;">1. Build Skill: Creative Generation</strong>
            <span style="color: #8e988e; font-size: 0.78rem;">4x5 &amp; 9x16 graphics rendered via local script; HeyGen avatar videos &amp; MoneyPrinterTurbo short cutdowns.</span>
          </div>
          <div style="padding: 0.75rem; border-radius: 10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);">
            <strong style="color: #fff; font-size: 0.85rem; display: block;">2. Content &amp; UTM Structure</strong>
            <span style="color: #8e988e; font-size: 0.78rem;">Stable content IDs (55c-d01-*) joined to GA4 &amp; UTM leads in Attribution table.</span>
          </div>
          <div style="padding: 0.75rem; border-radius: 10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);">
            <strong style="color: #fff; font-size: 0.85rem; display: block;">3. Three-Gate Operator Review</strong>
            <span style="color: #fff; font-size: 0.78rem;">Content (copy), Media (visuals), and Publish gates must be explicitly approved by Fritz before scheduling.</span>
          </div>
          <div style="padding: 0.75rem; border-radius: 10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);">
            <strong style="color: #fff; font-size: 0.85rem; display: block;">4. Multi-Channel Dispatch</strong>
            <span style="color: #8e988e; font-size: 0.78rem;">Postiz scheduler dispatches approved moments automatically to Facebook, YouTube, TikTok, Instagram &amp; X.</span>
          </div>
        </div>
      </div>
    ';

    return [
      'day_nav' => ['#type' => 'container', '#attributes' => ['class' => ['famtastic-dispatch__days'], 'style' => 'margin-bottom: 1.25rem;'], 'items' => $dayButtons],
      'header' => ['#markup' => $headerHtml],
      'cards' => ['#markup' => $cardsHtml],
      'guide' => ['#markup' => $architectureGuide],
    ];
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
