<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Service\PostizChannelsService;
use Drupal\famtastic_pipeline\Service\CampaignWorkspace;
use Drupal\famtastic_pipeline\Utility\CampaignFileLocator;
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
    'drops' => 'Postiz drops',
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
    private readonly CampaignWorkspace $workspace,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('famtastic_pipeline.postiz_channels'),
      $container->get('famtastic_pipeline.campaign_workspace'),
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
      ['Status', ['data' => ['#markup' => $this->badge((string) $row['status'])]]],
      ['Recipient', Html::escape((string) $row['recipient'])],
      ['Category', Html::escape((string) $row['category'])],
      ['Attempts', (int) $row['attempts'] . ' / ' . (int) $row['max_attempts']],
      ['Queued', $this->date((int) $row['created'])],
      ['Last attempt', $this->date((int) $row['changed'])],
      ['Last error', Html::escape((string) ($row['last_error'] ?: '—'))],
    ];
    return [
      '#title' => 'Email message #' . $id,
      'selector' => $this->campaignSelector('email'),
      'truth' => ['#type' => 'details', '#title' => 'How saving and publishing differ', 'body' => ['#markup' => $this->executionTruth()]],
      'back' => Link::fromTextAndUrl('← Back to Email Center', Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'email'], ['query' => ['campaign' => $this->selectedCampaign(), 'email_view' => \Drupal::request()->query->get('email_view', 'customer')]]))->toRenderable(),
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
        'back2' => ['#type' => 'link', '#title' => $this->t('Back'), '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'email'], ['query' => ['campaign' => $this->selectedCampaign(), 'email_view' => \Drupal::request()->query->get('email_view', 'customer')]]), '#attributes' => ['class' => ['button']]],
      ],
    ];
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
      ['Status', ['data' => ['#markup' => $this->badge((string) $run['status'])]]],
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
    return [
      '#title' => 'Build DNA #' . $id . ' — ' . $run['build_key'],
      'selector' => $this->campaignSelector('builddna'),
      'truth' => ['#type' => 'details', '#title' => 'How saving and publishing differ', 'body' => ['#markup' => $this->executionTruth()]],
      'back' => Link::fromTextAndUrl('← Back to Build DNA Registry', Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'builddna'], ['query' => ['campaign' => $this->selectedCampaign(), 'email_view' => \Drupal::request()->query->get('email_view', 'customer')]]))->toRenderable(),
      'facts' => ['#type' => 'table', '#header' => ['Field', 'Value'], '#rows' => $rows, '#attributes' => ['class' => ['famtastic-ops__table']]],
      'snapshots' => $snapshots,
    ];
  }

  /**
   * Renders a committed marketing/campaigns/<slug>/scorecard.json (see
   * scripts/score-campaign.py and marketing/engine/schemas/campaign-scorecard.schema.json)
   * as a staff detail view, matching the Build DNA detail pattern above:
   * facts table + raw evidence in inspectable <details> blocks. This is
   * read-only — it never regenerates or edits the scorecard; that stays
   * scripts/score-campaign.py's job, run against real (read-only) Postiz
   * state.
   */
  public function scorecardDetail(string $campaign_slug): array {
    $data = CampaignFileLocator::readJson($campaign_slug, 'scorecard.json');
    if ($data === NULL) {
      throw new NotFoundHttpException('No scorecard.json found for this campaign. Run: python3 scripts/score-campaign.py --campaign ' . $campaign_slug);
    }
    // Plain strings below are deliberately NOT Html::escape()'d: #type table
    // already HTML-escapes plain-string row cells once when it renders them
    // (Twig autoescaping in table.html.twig). Escaping here too would
    // double-encode entities. Html::escape() is used further down only where
    // a value is concatenated into a raw '#markup' HTML string, which is not
    // auto-escaped.
    $totals = (array) ($data['totals'] ?? []);
    $rows = [
      ['Campaign slug', (string) ($data['campaign_slug'] ?? $campaign_slug)],
      ['Campaign ID', (string) ($data['campaign_id'] ?? '—')],
      ['Program / series', (string) ($data['program_id'] ?? '—') . ' / ' . (string) ($data['series_id'] ?? '—')],
      ['Generated at', (string) ($data['generated_at'] ?? '—')],
      ['Provider', (string) ($data['provider'] ?? '—')],
      ['Clicks/conversions available', empty($data['clicks_conversions_available']) ? 'No — see gap note below' : 'Yes'],
      ['Drops scored', (string) ($totals['drops'] ?? 0)],
      ['Requested channels', (string) ($totals['requested_channels'] ?? 0)],
      ['Provider records found', (string) ($totals['provider_records_found'] ?? 0)],
      ['Published', (string) ($totals['published'] ?? 0)],
      ['Error', (string) ($totals['error'] ?? 0)],
      ['Queued', (string) ($totals['queued'] ?? 0)],
      ['Not found', (string) ($totals['not_found'] ?? 0)],
      ['Publish success rate', isset($totals['publish_success_rate']) ? number_format(((float) $totals['publish_success_rate']) * 100, 1) . '%' : '—'],
    ];

    $dropRows = [];
    foreach ((array) ($data['drops'] ?? []) as $drop) {
      if (!is_array($drop)) {
        continue;
      }
      $counts = (array) ($drop['counts'] ?? []);
      $dropRows[] = [
        (string) ($drop['content_id'] ?? $drop['drop_id'] ?? '—'),
        mb_strimwidth((string) ($drop['theme'] ?? ''), 0, 60, '…'),
        (string) ($counts['requested_channels'] ?? 0),
        (string) ($counts['provider_records_found'] ?? 0),
        (string) ($counts['published'] ?? 0),
        (string) ($counts['error'] ?? 0),
        (string) ($counts['queued'] ?? 0),
        (string) ($counts['not_found'] ?? 0),
      ];
    }

    return [
      '#title' => 'Scorecard — ' . $campaign_slug,
      '#attached' => ['library' => ['famtastic_pipeline/operations', 'famtastic_pipeline/campaign_workspace']],
      'selector' => $this->campaignSelector('drops'),
      'truth' => ['#type' => 'details', '#title' => 'How saving and publishing differ', 'body' => ['#markup' => $this->executionTruth()]],
      'back' => Link::fromTextAndUrl('← Back to Postiz drops', Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'drops'], ['query' => ['campaign' => $campaign_slug]]))->toRenderable(),
      'facts' => ['#type' => 'table', '#header' => ['Field', 'Value'], '#rows' => $rows, '#attributes' => ['class' => ['famtastic-ops__table']]],
      'per_drop' => $this->table(
        'Per-drop publish state',
        'Real Postiz publish state per drop, read directly from the committed scorecard — never estimated or backfilled.',
        ['Content ID', 'Theme', 'Requested', 'Found', 'Published', 'Error', 'Queued', 'Not found'],
        $dropRows,
        'No drops in this scorecard.',
      ),
      'gap_note' => [
        '#type' => 'details',
        '#title' => $this->t('Gap note (clicks / conversions)'),
        '#open' => empty($data['clicks_conversions_available']),
        'pre' => ['#markup' => '<pre class="famtastic-email-body">' . Html::escape((string) ($data['gap_note'] ?? '—')) . '</pre>'],
      ],
      'attribution_note' => [
        '#type' => 'details',
        '#title' => $this->t('Attribution note'),
        'pre' => ['#markup' => '<pre class="famtastic-email-body">' . Html::escape((string) ($data['attribution_note'] ?? '—')) . '</pre>'],
      ],
    ];
  }

  private function page(string $tab): array {
    $selected = $this->selectedCampaign();
    $tabs = [];
    foreach (self::TABS as $id => $label) {
      $tabs['t_' . $id] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $id === 'command' ? Url::fromRoute('famtastic_pipeline.marketing', [], ['query' => ['campaign' => $selected]]) : Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => $id], ['query' => ['campaign' => $selected]]),
        '#attributes' => ['class' => ['famtastic-mkt__tab', $tab === $id ? 'active' : '']],
      ];
    }
    $content = [
      'selector' => $this->campaignSelector($tab),
      'truth' => ['#type' => 'details', '#title' => 'How saving and publishing differ', 'body' => ['#markup' => $this->executionTruth()]],
      'tabs' => ['#type' => 'container', '#attributes' => ['class' => ['famtastic-mkt__tabs']], 'items' => $tabs],
    ] + match ($tab) {
      'command' => $this->tabCommand(),
      'dispatch' => $this->tabDispatch(),
      'queue' => $this->tabQueue(),
      'drops' => $this->tabDrops(),
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
    if ($source = CampaignFileLocator::releaseSource()) $content['release_source'] = ['#weight' => -50, '#plain_text' => 'Read-only source snapshot ' . substr($source['sha'], 0, 12) . ' · synced ' . $source['synced_at'] . '. Recorded schedules are source metadata; check provider receipts for live state.'];
    return [
      '#title' => 'Marketing Command Center — ' . $title,
      '#attached' => ['library' => ['famtastic_pipeline/operations', 'famtastic_pipeline/campaign_workspace']],
      '#cache' => ['max-age' => 0],
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
    $key = $this->selectedCampaign();
    $campaign = $this->workspace->get($key);
    if (!$campaign) throw new NotFoundHttpException('Campaign not found.');
    $rows = [];
    foreach (['goal' => 'Goal', 'audience' => 'Audience', 'offer' => 'Offer', 'evidence' => 'Evidence', 'cta' => 'Next customer action', 'start_date' => 'Starts', 'end_date' => 'Ends'] as $field => $label) $rows[] = [$label, $campaign['plan'][$field] ?? 'Not planned yet'];
    $page = $this->table('Campaign plan — ' . $campaign['name'], 'Status: ' . $campaign['status'] . '. Plan records are drafts; provider approvals and delivery receipts remain separate.', ['Planning detail', 'Value'], $rows, 'Create a draft plan to begin.');
    $items = $this->workspace->items($key);
    $counts = array_count_values(array_map(static fn(array $item): string => (string) ($item['state'] ?? 'draft'), $items));
    $page['counts'] = ['#plain_text' => count($items) . ' content items. ' . implode(' · ', array_map(static fn(string $state, int $count): string => $state . ': ' . $count, array_keys($counts), array_values($counts)))];
    $page['next'] = ['#type' => 'link', '#title' => 'Review content plan', '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'queue'], ['query' => ['campaign' => $key]]), '#attributes' => ['class' => ['button']]];
    return $page;
  }


  private function tabQueue(): array {
    return $this->campaignItems('Content queue');
  }


  /**
   * Postiz drops: per-drop live-record control, read directly from each
   * campaign's posting-schedule.json (the Phase 1/2 posting-schedule
   * campaigns — cost-is-not-the-reason, ghost-town-ep1 — are not synced into
   * famtastic_social_record; that table only holds the older 55-cents-17-day
   * manifest). Edit/delete here call PostizDropMutationService directly, as
   * an injected Drupal service — never a shell-out to
   * scripts/queue-campaign-drops.py, which was flagged as an unplanned
   * security hole for exactly this reason. Only the live Postiz record is
   * touched; posting-schedule.json itself stays the CLI's job.
   */
  private function tabDrops(): array {
    $selected = $this->selectedCampaign();
    $pillsBuild = [];
    if (CampaignFileLocator::readJson($selected, 'posting-schedule.json') === NULL) {
      return ['heading' => ['#markup' => '<h2>Provider schedule unavailable</h2><p>The selected campaign has no readable deployed posting-schedule.json. Its Drupal plan remains available in Content queue. This does not mean Postiz is empty or disconnected. Check Channel health separately.</p>']];
    }
    $schedule = CampaignFileLocator::readJson($selected, 'posting-schedule.json') ?? [];
    $rows = [];
    foreach ((array) ($schedule['drops'] ?? []) as $drop) {
      if (!is_array($drop)) {
        continue;
      }
      $cid = (string) ($drop['content_id'] ?? '');
      if ($cid === '') {
        continue;
      }
      $known = CampaignFileLocator::knownProviderIds($drop);
      // Plain strings here, deliberately NOT Html::escape()'d: Drupal's
      // #type table already HTML-escapes plain-string row cells once when
      // it renders them (Twig autoescaping in table.html.twig). Escaping
      // here too would double-encode entities (e.g. an "&" in a drop theme
      // rendering as "&amp;amp;") — the table markup below (badge/links) is
      // the only column that legitimately needs its own escaping, because
      // it is passed as trusted '#markup'.
      $rows[] = [
        $cid,
        (string) ($drop['scheduled_time'] ?? '—'),
        mb_strimwidth((string) ($drop['theme'] ?? ''), 0, 60, '…'),
        implode(', ', array_map('strval', (array) ($drop['channels'] ?? []))),
        ['data' => ['#markup' => $this->badge((string) ($drop['state'] ?? 'idea'))]],
        ['data' => ['#markup' => $this->badge($this->dropDeliveryClock($drop))]],
        (string) count($known),
        // '#type' => 'link' render arrays (not Link::toRenderable(), which
        // carries no class) so these get the theme's .button styling —
        // a bare <a> here would fall under the 44px mobile touch-target
        // minimum (Design DNA v1).
        ['data' => [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => Url::fromRoute('famtastic_pipeline.marketing.drop_edit', ['campaign_slug' => $selected, 'content_id' => $cid]),
          '#attributes' => ['class' => ['button', 'button--small']],
        ]],
        ['data' => [
          '#type' => 'link',
          '#title' => $this->t('Delete'),
          '#url' => Url::fromRoute('famtastic_pipeline.marketing.drop_delete', ['campaign_slug' => $selected, 'content_id' => $cid]),
          '#attributes' => ['class' => ['button', 'button--small', 'button--danger']],
        ]],
      ];
    }

    $page = $this->table(
      'Postiz drops — ' . $selected,
      'Source plan and recorded provider IDs, read from posting-schedule.json. A recorded ID is not proof of current provider state. “Draft retime required” means the source plan was intentionally corrected but no provider call was made; it must be reconciled before any publish approval. Copy, media, channels, and schedule time stay the CLI\'s job (scripts/queue-campaign-drops.py --edit-drop), never rewritten here from a live request.',
      ['Content ID', 'Planned source time', 'Theme', 'Channels', 'Creative state', 'Delivery clock', 'Recorded provider IDs', '', ''],
      $rows,
      'No drops in this campaign schedule.',
    );
    $page['campaigns'] = $pillsBuild;
    $page['campaigns']['#weight'] = -30;
    $page['cadence'] = [
      '#markup' => '<p class="famtastic-ops__lede"><strong>Cadence truth:</strong> ' . Html::escape($this->scheduleCadenceSummary($schedule)) . '</p>',
      '#weight' => -28,
    ];
    $page['scorecard_link'] = [
      '#type' => 'link',
      '#title' => $this->t('View scorecard for @s →', ['@s' => $selected]),
      '#url' => Url::fromRoute('famtastic_pipeline.marketing.scorecard', ['campaign_slug' => $selected]),
      '#attributes' => ['class' => ['button']],
      '#weight' => -25,
    ];
    return $page;
  }

  private function tabCalendar(): array {
    return $this->campaignItems('Campaign calendar');
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
    $query->condition('p.campaign', $this->selectedCampaign());
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
      'lifecycle_clock' => $this->table(
        'Campaign lifecycle clocks',
        'One local operational ledger per campaign: draft/staged, queued jobs, sent mail, reply evidence, and fulfilled Commerce. “Replies not campaign-attributable” is deliberate: inbound support mail has no campaign join, so this screen will not invent a reply count. Sent means a recorded outbound send, not inbox placement.',
        ['Campaign', 'Data lane', 'Draft / staged', 'Queued', 'Sent', 'Replied', 'Paid', 'Last ledger change'],
        $this->campaignLifecycleClockRows(),
        'No campaign ledger records yet.',
      ),
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
   * Returns a delivery state without treating a stored Postiz ID as live proof.
   */
  private function dropDeliveryClock(array $drop): string {
    $reconciliation = $drop['provider_reconciliation'] ?? NULL;
    if (is_array($reconciliation) && ($reconciliation['status'] ?? '') !== 'reconciled') {
      return 'Draft retime required';
    }
    $publishApproved = (bool) (($drop['approval'] ?? [])['publish'] ?? FALSE);
    $known = CampaignFileLocator::knownProviderIds($drop);
    if (!$publishApproved && $known) {
      return 'Draft recorded / publish closed';
    }
    if (!$publishApproved) {
      return 'Not queued / publish closed';
    }
    return $known ? 'Provider read-back required' : 'Not queued';
  }

  /**
   * Summarizes a source schedule without claiming live scheduler state.
   */
  private function scheduleCadenceSummary(array $schedule): string {
    $drops = array_values(array_filter((array) ($schedule['drops'] ?? []), 'is_array'));
    if ($drops === []) {
      return 'No source drops are recorded.';
    }
    $times = array_values(array_filter(array_map(static fn (array $drop): string => (string) ($drop['scheduled_time'] ?? ''), $drops)));
    sort($times, SORT_STRING);
    $closed = count(array_filter($drops, static fn (array $drop): bool => (($drop['approval'] ?? [])['publish'] ?? FALSE) === FALSE));
    $reconciliation = count(array_filter($drops, static fn (array $drop): bool => is_array($drop['provider_reconciliation'] ?? NULL) && (($drop['provider_reconciliation']['status'] ?? '') !== 'reconciled')));
    $window = $times ? $times[0] . ' → ' . $times[count($times) - 1] : 'no planned timestamps';
    return count($drops) . ' source drops across ' . $window . '; ' . $closed . ' publish gate(s) closed; ' . $reconciliation . ' provider reconciliation block(s).';
  }

  /**
   * Campaign-level outreach clocks from durable local facts only.
   */
  private function campaignLifecycleClockRows(): array {
    $rows = [];
    $campaigns = $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['id', 'campaign_key', 'status', 'changed'])
      ->condition('campaign_key', $this->selectedCampaign())
      ->orderBy('changed', 'DESC')
      ->range(0, 50)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($campaigns as $campaign) {
      $campaignId = (int) $campaign['id'];
      $campaignKey = (string) $campaign['campaign_key'];
      $staged = 0;
      $sent = 0;
      $lastChange = (int) $campaign['changed'];
      foreach ($this->database->select('famtastic_email_message', 'm')
        ->fields('m', ['status', 'sent_at', 'changed'])
        ->condition('campaign_id', $campaignId)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) as $message) {
        $staged += $message['status'] === 'staged' ? 1 : 0;
        $sent += (int) $message['sent_at'] > 0 ? 1 : 0;
        $lastChange = max($lastChange, (int) $message['changed']);
      }

      $queuedQuery = $this->database->select('famtastic_job', 'j');
      $queuedQuery->join('famtastic_prospect', 'p', 'p.id = j.prospect_id');
      $queued = (int) $queuedQuery
        ->condition('j.job_type', 'outreach.send')
        ->condition('j.status', 'queued')
        ->condition('p.campaign', $campaignKey)
        ->countQuery()->execute()->fetchField();

      $paidQuery = $this->database->select('famtastic_commerce_fulfillment', 'f');
      $paidQuery->join('famtastic_prospect', 'p', 'p.id = f.prospect_id');
      $paidQuery->addExpression('COUNT(f.id)', 'orders');
      $paidQuery->addExpression('SUM(f.amount_minor)', 'amount');
      $paid = $paidQuery
        ->condition('f.status', 'fulfilled')
        ->condition('p.campaign', $campaignKey)
        ->execute()->fetchAssoc() ?: ['orders' => 0, 'amount' => 0];

      // The inbound-mail ledger is intentionally linked to a portal thread,
      // not a campaign. Count only an explicit event if one is ever added;
      // otherwise state the attribution gap rather than presenting a false 0.
      $replied = (int) $this->database->select('famtastic_event', 'e')
        ->condition('campaign_id', $campaignId)
        ->condition('event_type', 'email.replied')
        ->countQuery()->execute()->fetchField();
      $paidOrders = (int) $paid['orders'];
      $paidAmount = (int) $paid['amount'];
      $draftDisplay = $staged > 0 ? $staged . ' staged' : ((string) $campaign['status'] === 'draft' ? 'Campaign draft' : '—');
      $replyDisplay = $replied > 0 ? (string) $replied : 'Not campaign-attributable';
      $paidDisplay = $paidOrders > 0 ? $paidOrders . ' / $' . number_format($paidAmount / 100, 2) : '—';
      $dataLane = $this->isFixtureCampaign($campaignKey) ? 'Fixture / smoke' : 'Operational record';
      $rows[] = [
        Html::escape($campaignKey),
        $dataLane,
        $draftDisplay,
        $queued ?: '—',
        $sent ?: '—',
        $replyDisplay,
        $paidDisplay,
        $this->date($lastChange),
      ];
    }
    return $rows;
  }

  private function isFixtureCampaign(string $campaignKey): bool {
    return (bool) preg_match('/(?:^|[-_])(e2e|fixture|smoke|test|journey)(?:[-_]|$)/i', $campaignKey);
  }

  /**
   * Joins famtastic_social_record.content_id to prospect utm snapshots.
   *
   * The utm_content match is resolved in PHP over the bounded snapshot set so
   * the query stays portable across MySQL production and SQLite local runs
   * (no JSON_EXTRACT / CONCAT dependence).
   */
  private function contentGrainRows(): array {
    if ($this->selectedCampaign() !== CampaignWorkspace::LEGACY) return [];
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
    $view = (string) \Drupal::request()->query->get('email_view', 'customer');
    if (!in_array($view, ['customer', 'operational', 'failed'], TRUE)) $view = 'customer';
    $failed = (int) $this->database->select('famtastic_notification_outbox', 'n')->condition('status', ['retry', 'dead_letter', 'failed'], 'IN')->countQuery()->execute()->fetchField();
    $page = ['compose' => ['#type' => 'link', '#title' => 'Open messages and draft a reply', '#url' => Url::fromRoute('famtastic_pipeline.client_messages_admin'), '#attributes' => ['class' => ['button', 'button--primary']]]];
    foreach (['customer' => 'Customer communications', 'operational' => 'Operational alerts', 'failed' => 'Failed deliveries (' . $failed . ')'] as $key => $label) {
      $page['filter_' . $key] = ['#type' => 'link', '#title' => $label, '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'email'], ['query' => ['campaign' => $this->selectedCampaign(), 'email_view' => $key]]), '#attributes' => ['class' => ['button', $view === $key ? 'button--primary' : 'button--secondary']]];
    }
    $query = $this->database->select('famtastic_notification_outbox', 'n');
    if ($view === 'failed') $query->condition('status', ['retry', 'dead_letter', 'failed'], 'IN');
    else $query->condition('category', 'operational', $view === 'operational' ? '=' : '<>');
    $records = $query->extend(\Drupal\Core\Database\Query\PagerSelectExtender::class)->fields('n', ['id', 'category', 'recipient', 'subject', 'status', 'provider_message_id', 'changed'])->orderBy('changed', 'DESC')->limit(25)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $rows = []; $groups = [];
    foreach ($records as $record) {
      $link = $this->linkCell(Link::fromTextAndUrl('Inspect #' . $record['id'], Url::fromRoute('famtastic_pipeline.marketing.email_inspect', ['id' => (int) $record['id']], ['query' => ['campaign' => $this->selectedCampaign(), 'email_view' => $view]])));
      if ($view === 'operational') {
        $key = hash('sha256', $record['subject'] . ':' . $record['status']);
        $groups[$key] ??= ['#type' => 'details', '#title' => $record['subject'] . ' — ' . $record['status']];
        $groups[$key]['record_' . $record['id']] = $link;
      }
      else $rows[] = [(int) $record['id'], Html::escape((string) $record['recipient']), Html::escape((string) $record['subject']), ['data' => ['#markup' => $this->badge((string) $record['status'])]], Html::escape((string) ($record['provider_message_id'] ?: '—')), ['data' => $link]];
    }
    $page['records'] = $this->table($view === 'operational' ? 'Operational alerts' : ($view === 'failed' ? 'Failed deliveries' : 'Customer communications'), 'Delivery history across all campaigns. Draft and preview messages from the conversation desk. Failed deliveries remain available in their own view.', ['ID', 'Recipient', 'Subject', 'Status', 'Provider message-ID', ''], $rows, $view === 'operational' ? 'Grouped alerts appear below.' : 'No messages in this view.');
    $page['groups'] = $groups;
    return $page;
  }

  private function tabCreative(): array {
    return $this->campaignItems('Creative and media');
  }


  private function tabBuildDna(): array {
    $rows = [];
    foreach ($this->database->select('famtastic_build_run', 'b')->extend(\Drupal\Core\Database\Query\PagerSelectExtender::class)
      ->fields('b', ['id', 'build_key', 'campaign_key', 'provider', 'agent_name', 'status', 'source_sha', 'created'])
      ->condition('campaign_key', $this->selectedCampaign())
      ->orderBy('b.created', 'DESC')->limit(25)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $run) {
      $rows[] = [
        (int) $run['id'],
        Html::escape((string) $run['build_key']),
        Html::escape((string) $run['campaign_key']),
        Html::escape((string) $run['provider']),
        Html::escape((string) $run['agent_name']),
        ['data' => ['#markup' => $this->badge((string) $run['status'])]],
        Html::escape(substr((string) $run['source_sha'], 0, 12) ?: '—'),
        ['data' => $this->linkCell(Link::fromTextAndUrl('Inspect DNA', Url::fromRoute('famtastic_pipeline.marketing.build_dna', ['id' => (int) $run['id']], ['query' => ['campaign' => $this->selectedCampaign()]])))],
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
    return $this->campaignItems('Dispatch review');
  }


  /** Serve only a selected schedule's recorded media from approved roots. */
  public function campaignMedia(string $campaign_key, string $content_id, string $variant = 'primary_media'): Response {
    $schedule = CampaignFileLocator::readJson($campaign_key, 'posting-schedule.json');
    if (!$schedule) throw new NotFoundHttpException('Campaign schedule unavailable.');
    $drop = CampaignFileLocator::findDrop($schedule, $content_id);
    $path = in_array($variant, ['primary_media', 'supporting_media'], TRUE) ? ($drop[$variant] ?? '') : ($drop['surface_assets'][$variant] ?? '');
    if (!is_string($path) || !preg_match('#^marketing/(?:campaigns/|creative/campaign-assets/)[a-zA-Z0-9/_.-]+\.(png|jpe?g|webp|mp4)$#D', $path) || str_contains($path, '..')) throw new NotFoundHttpException('No approved media path recorded.');
    foreach ([dirname(\Drupal::root(), 2), dirname(\Drupal::root())] as $root) {
      $allowed = realpath($root . '/marketing');
      $file = realpath($root . '/' . $path);
      if (!$allowed || !$file || !str_starts_with($file, $allowed . DIRECTORY_SEPARATOR) || !is_file($file)) continue;
      $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($file);
      $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      $response->headers->set('Content-Type', match ($extension) { 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'mp4' => 'video/mp4' });
      $response->headers->set('X-Content-Type-Options', 'nosniff');
      $response->headers->set('Cache-Control', 'private, no-store');
      return $response;
    }
    throw new NotFoundHttpException('Recorded media is not deployed on this server.');
  }

  private function selectedCampaign(): string {
    $all = $this->workspace->all();
    $key = (string) \Drupal::request()->query->get('campaign', '');
    if ($key !== '' && !isset($all[$key])) throw new NotFoundHttpException('Campaign not found.');
    return $key !== '' ? $key : (string) array_key_first($all);
  }

  private function campaignSelector(string $tab): array {
    $selected = $this->selectedCampaign();
    $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-campaign-selector'], 'aria-label' => 'Campaign workspace']];
    foreach ($this->workspace->all() as $key => $campaign) {
      $build['campaign_' . $key] = ['#type' => 'link', '#title' => $campaign['name'] . ' · ' . $campaign['status'], '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => $tab], ['query' => ['campaign' => $key]]), '#attributes' => ['class' => ['button', $selected === $key ? 'button--primary' : 'button--secondary'], 'aria-current' => $selected === $key ? 'page' : 'false']];
    }
    $build['create'] = ['#type' => 'link', '#title' => 'New campaign draft', '#url' => Url::fromRoute('famtastic_pipeline.campaign_add'), '#attributes' => ['class' => ['button']]];
    $campaign = $this->workspace->get($selected);
    foreach (['edit' => 'Edit plan', 'duplicate' => 'Duplicate as draft', ($campaign['status'] === 'archived' ? 'restore' : 'archive') => ($campaign['status'] === 'archived' ? 'Restore draft' : 'Archive')] as $action => $label) {
      if (empty($campaign['id']) && in_array($action, ['archive', 'restore'], TRUE)) continue;
      if ($campaign['status'] === 'archived' && $action === 'edit') continue;
      $build[$action] = ['#type' => 'link', '#title' => $label, '#url' => Url::fromRoute('famtastic_pipeline.campaign_manage', ['campaign_key' => $selected, 'action' => $action]), '#attributes' => ['class' => ['button']]];
    }
    return $build;
  }

  /** The same projection powers every campaign screen, without invented assets. */
  private function campaignItems(string $title): array {
    $key = $this->selectedCampaign();
    $rows = [];
    foreach ($this->workspace->items($key) as $item) {
      $review = ['#type' => 'container'];
      if ($item['source'] === 'Imported legacy manifest') {
        foreach (['content', 'media', 'publish'] as $gate) {
          $approved = !empty($item['approval_' . $gate]);
          $review[$gate] = ['#type' => 'link', '#title' => ($approved ? 'Revoke ' : 'Review ') . $gate, '#url' => Url::fromRoute('famtastic_pipeline.social_record_gate', ['content_id' => $item['content_id'], 'gate' => $gate, 'direction' => $approved ? 'revoke' : 'approve']), '#attributes' => ['class' => ['button']]];
        }
        foreach (['4x5', '9x16'] as $format) $review[$format] = ['#type' => 'link', '#title' => 'View ' . $format . ' asset', '#url' => Url::fromRoute('famtastic_pipeline.marketing.asset', ['filename' => $item['content_id'] . '.' . $format . '.png']), '#attributes' => ['class' => ['button']]];
      }
      elseif ($item['source'] === 'CLI schedule') {
        $variants = array_keys((array) ($item['surface_assets'] ?? []));
        foreach (['primary_media', 'supporting_media'] as $variant) if (!empty($item[$variant])) $variants[] = $variant;
        foreach ($variants as $variant) $review['media_' . $variant] = ['#type' => 'link', '#title' => 'View ' . str_replace('_', ' ', $variant), '#url' => Url::fromRoute('famtastic_pipeline.campaign_media', ['campaign_key' => $key, 'content_id' => $item['content_id'], 'variant' => $variant]), '#attributes' => ['class' => ['button']]];

        $review['provider'] = ['#type' => 'link', '#title' => 'Review provider record', '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'drops'], ['query' => ['campaign' => $key]]), '#attributes' => ['class' => ['button']]];
      }
      else $review['plan'] = ['#type' => 'link', '#title' => 'Edit draft plan', '#url' => Url::fromRoute('famtastic_pipeline.campaign_manage', ['campaign_key' => $key, 'action' => 'edit']), '#attributes' => ['class' => ['button']]];
      $content = ['#type' => 'container', 'theme' => ['#plain_text' => (string) ($item['theme'] ?? '')]];
      if (!empty($item['copy'])) {
        $content['copy'] = ['#type' => 'details', '#title' => 'Read prepared copy'];
        foreach ((array) $item['copy'] as $channel => $copy) if (is_scalar($copy)) $content['copy']['channel_' . $channel] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => Html::escape((string) $copy)];
      }
      if (!empty($item['primary_media'])) $content['media'] = ['#type' => 'details', '#title' => 'Media source', 'path' => ['#plain_text' => (string) $item['primary_media']]];
      $rows[] = [(string) ($item['content_id'] ?? ''), (string) ($item['scheduled_time'] ?: 'Not scheduled'), ['data' => $content], implode(', ', (array) ($item['channels'] ?? [])), (string) ($item['state'] ?? 'draft'), $item['source'], ['data' => $review]];
    }
    return $this->table($title . ' — ' . $key, 'Planning notes are editable Drupal drafts. CLI schedules and imported manifest records retain their original IDs and review controls. A recorded schedule or provider ID is not delivery proof.', ['Content ID', 'Date / time', 'Content', 'Channels', 'State', 'Source', 'Next action'], $rows, 'No content yet. Use Edit plan to add ideas and dates.');
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
