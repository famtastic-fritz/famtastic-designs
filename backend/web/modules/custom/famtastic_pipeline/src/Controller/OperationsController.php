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
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\GoogleAnalyticsReportingService;
use Drupal\famtastic_pipeline\Service\PostizChannelsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Operator-first campaign, message, proof, job, and build telemetry pages.
 */
final class OperationsController extends ControllerBase {

  /** Queue age in seconds after which the notification banner demands attention. */
  private const NOTIFICATION_QUEUE_ATTENTION_SECONDS = 1800;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $pipelineEntityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly GoogleAnalyticsReportingService $googleAnalytics,
    private readonly PostizChannelsService $postizChannels,
  ) {}

  /**
   * Creates the controller from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('famtastic_pipeline.google_analytics_reporting'),
      $container->get('famtastic_pipeline.postiz_channels'),
    );
  }

  /**
   * Renders the staff operating home without mixing campaign and GA reports.
   */
  public function hub(): array {
    $analytics = $this->googleAnalytics->dashboardReport();
    $published = (int) $this->database->select('node_field_data', 'n')
      ->condition('status', 1)->countQuery()->execute()->fetchField();
    $openSupport = $this->count('famtastic_portal_thread', ['status' => 'open']);
    $cards = [
      ['Website Analytics', !empty($analytics['available']) ? 'Connected · 30-day reporting ready' : 'Connection needs attention', 'Traffic, engagement, top pages, and acquisition channels.', Url::fromRoute('famtastic_pipeline.analytics'), 'analytics'],
      ['Customers', $this->count('famtastic_customer') . ' customer accounts', 'Customer identity, contact details, consent, and business workspaces.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'customers']), 'customers'],
      ['Website Requests', $this->countIn('famtastic_project_request', 'status', ['draft', 'submitted', 'checkout_started']) . ' active requests', 'Pre-purchase interviews, recommendations, private-offer candidates, and Commerce conversion.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'website-requests']), 'prospects'],
      ['Commerce', $this->count('famtastic_order', ['payment_status' => 'paid']) . ' paid orders', 'Products, orders, payment status, subscriptions, and fulfillment.', Url::fromUserInput('/admin/commerce'), 'commerce'],
      ['Support', $openSupport . ' open conversations', 'Customer requests, project questions, replies, and service issues.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'support']), 'support'],
      ['Notifications', $this->countIn('famtastic_notification_outbox', 'status', ['queued', 'retry', 'dead_letter']) . ' need attention', 'Receipts, acknowledgments, reminders, delivery attempts, and failures.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'notifications']), 'emails-sent'],
      ['Automation', $this->count('famtastic_worker_heartbeat') . ' monitored workers', 'Scheduled protection, last runs, retries, and worker health.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'workers']), 'open-jobs'],
      ['Grant Codes', $this->count('famtastic_grant_code', ['status' => 'active']) . ' active private grants', 'Owner comps, named customer grants, credits, partner benefits, redemption scope, and audit.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'grant-codes']), 'commerce'],
      ['Launch Approval', 'Owner decision record', 'Review exact product promises, provisional terms, Stripe evidence, and activation gates.', Url::fromRoute('famtastic_pipeline.launch_approval'), 'commerce'],
      ['Content', $published . ' published items', 'Website pages, articles, FAQs, services, and packages.', Url::fromUserInput('/admin/content'), 'content'],
      ['Services', $this->count('famtastic_entitlement', ['status' => 'active']) . ' active entitlements', 'Hosting, domains, analytics, websites, and customer capabilities.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'services']), 'services'],
      ['Referrals', $this->count('famtastic_referral') . ' customer referrals', 'Introductions, privacy-safe status, and reward readiness.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'referrals']), 'referrals'],
      ['Campaign Operations', $this->count('famtastic_campaign') . ' campaigns', 'Prospects, proof builds, outreach, clicks, and campaign sales.', Url::fromRoute('famtastic_pipeline.campaign_operations'), 'campaigns'],
    ];
    $cardBuild = [];
    foreach ($cards as [$title, $status, $description, $url, $icon]) {
      $cardBuild[] = [
        '#type' => 'link', '#title' => [
          '#markup' => '<span class="famtastic-hub__icon famtastic-hub__icon--' . Html::escape($icon) . '" aria-hidden="true"></span><span class="famtastic-hub__copy"><strong>' . Html::escape($title) . '</strong><em>' . Html::escape($status) . '</em><span>' . Html::escape($description) . '</span><b>Open →</b></span>',
        ], '#url' => $url, '#attributes' => ['class' => ['famtastic-hub__card']],
      ];
    }
    return $this->page([
      'hero' => ['#markup' => '<section class="famtastic-hub__hero"><span>FAMtastic Designs</span><h2>Run the business from one place.</h2><p>Choose the area you need. Website analytics and campaign operations remain focused, separate workspaces.</p></section>'],
      'attention' => ['#markup' => '<div class="famtastic-hub__attention"><strong>Needs attention</strong><span>' . $openSupport . ' open support conversation' . ($openSupport === 1 ? '' : 's') . '</span></div>'],
      'heading' => ['#markup' => '<h2 class="famtastic-hub__heading">Operations</h2>'],
      'cards' => ['#type' => 'container', '#attributes' => ['class' => ['famtastic-hub__grid']], 'items' => $cardBuild],
    ], 'Operations Home');
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
      'campaigns' => ['label' => 'Campaigns', 'value' => $campaignTotal],
      'prospects' => ['label' => 'Prospects', 'value' => $this->count('famtastic_prospect')],
      'customers' => ['label' => 'Customers', 'value' => $this->count('famtastic_customer')],
      'proofs-ready' => [
        'label' => 'Proofs Ready',
        'value' => $this->count('proof_campaign', ['generation_status' => 'ready']),
      ],
      'emails-sent' => [
        'label' => 'Emails Sent',
        'value' => $this->count('famtastic_event', ['event_type' => 'email.sent']),
      ],
      'clicks' => ['label' => 'Clicks', 'value' => $this->count('famtastic_event', ['event_type' => 'email.clicked'])],
      'paid-orders' => [
        'label' => 'Paid Orders',
        'value' => $this->count('famtastic_order', ['payment_status' => 'paid']),
      ],
      'open-jobs' => [
        'label' => 'Open Jobs',
        'value' => $this->countIn('famtastic_job', 'status', ['queued', 'retry', 'running']),
      ],
      'open-exceptions' => [
        'label' => 'Open Exceptions',
        'value' => $this->countIn('famtastic_exception', 'status', ['open', 'retry']),
      ],
      'support' => [
        'label' => 'Open Support',
        'value' => $this->countIn('famtastic_support_case', 'status', ['new', 'assigned', 'waiting_on_customer', 'waiting_on_famtastic']),
      ],
      'services' => ['label' => 'Active Services', 'value' => $this->count('famtastic_entitlement', ['status' => 'active'])],
      'notifications' => ['label' => 'Notification Issues', 'value' => $this->countIn('famtastic_notification_outbox', 'status', ['retry', 'dead_letter'])],
      'workers' => ['label' => 'Monitored Workers', 'value' => $this->count('famtastic_worker_heartbeat')],
    ];

    $socialEvents = $this->socialCampaignEventCounts();
    $planned = 68;
    $approved = $socialEvents['approved'];
    $scheduled = $socialEvents['scheduled'];
    $publishedSocial = $socialEvents['published'];
    $failedSocial = $socialEvents['failed'];
    $socialVisits = $socialEvents['visits'];
    $socialLeads = $socialEvents['leads'];
    $socialSales = $socialEvents['sales'];
    $conversionRate = $socialVisits > 0 ? round(($socialLeads / $socialVisits) * 100, 1) . '%' : '—';
    $campaignDays = $this->fiftyFiveCentCampaignDays();
    $channelCards = $this->channelHealthCards();

    $todayCards = [
      ['Planned moments', (string) $planned, '17 days × four distinct content moments', 'neutral'],
      ['Awaiting approval', (string) max(0, $planned - $approved), 'Content, media, and publish approval remain separate', $approved < $planned ? 'attention' : 'good'],
      ['Scheduled', (string) $scheduled, 'Provider-confirmed jobs in the publishing queue', 'neutral'],
      ['Published', (string) $publishedSocial, 'Verified provider deliveries—not attempted sends', 'good'],
      ['Needs attention', (string) $failedSocial, 'Failed, rejected, or unverified social deliveries', $failedSocial > 0 ? 'danger' : 'good'],
      ['Website visits', (string) $socialVisits, 'Social-attributed sessions joined by stable content ID', 'neutral'],
      ['Leads', (string) $socialLeads, 'Quote, contact, registration, and intake conversions', 'good'],
      ['Lead conversion', $conversionRate, 'Attributed leads divided by social visits', 'neutral'],
      ['Purchases', (string) $socialSales, 'Paid orders attributed to campaign content', 'good'],
    ];

    $attentionItems = [];
    if ($approved === 0) {
      $attentionItems[] = ['Approve the first batch', 'Days 1–3 sit as queued Postiz drafts. Review content/media/publish gates per record.', 'Open approval queue', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'social-records'])];
    }
    if ($scheduled === 0) {
      $attentionItems[] = ['Queue and verify a provider event', 'Facebook is connected in Postiz; days 1–3 sit as drafts awaiting your queued-week review before any scheduling.', 'Open Postiz scheduler', Url::fromUri('http://127.0.0.1:4007')];
    }
    if ($failedSocial > 0) {
      $attentionItems[] = ['Resolve delivery failures', $failedSocial . ' social item(s) failed or remain unverified.', 'Open failures', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'open-exceptions'])];
    }
    if ($socialVisits === 0) {
      $attentionItems[] = ['Prove attribution', 'Publish one private or controlled post and verify its UTM content ID reaches GA4 and Drupal.', 'Open analytics', Url::fromRoute('famtastic_pipeline.analytics')];
    }

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
      'hero' => ['#markup' => '<section class="famtastic-command__hero"><div><span>FAMtastic Marketing Command Center</span><h2>Know what is ready, what needs you, and what makes money.</h2><p>Review the 17-day campaign, approve creative, monitor publishing, respond to engagement, and connect every post to visits, leads, and sales.</p></div><div class="famtastic-command__hero-status"><b>Draft-first safety</b><strong>PUBLIC PUBLISHING OFF</strong><small>Nothing goes live without explicit publish approval.</small></div></section>'],
      'actions' => [
        '#type' => 'container', '#attributes' => ['class' => ['famtastic-ops__actions', 'famtastic-command__actions']],
        'campaign-add' => ['#type' => 'link', '#title' => $this->t('＋ New campaign'), '#url' => Url::fromRoute('famtastic_pipeline.campaign_add'), '#attributes' => ['class' => ['button', 'button--primary']]],
        'scheduler' => ['#type' => 'link', '#title' => $this->t('Open Postiz Scheduler ↗'), '#url' => Url::fromUri('http://127.0.0.1:4007'), '#attributes' => ['class' => ['button', 'button--primary'], 'target' => '_blank', 'rel' => 'noopener noreferrer']],
        'analytics' => ['#type' => 'link', '#title' => $this->t('Website Analytics'), '#url' => Url::fromRoute('famtastic_pipeline.analytics'), '#attributes' => ['class' => ['button']]],
        'content' => ['#type' => 'link', '#title' => $this->t('Content Library'), '#url' => Url::fromUserInput('/admin/content'), '#attributes' => ['class' => ['button']]],
      ],
      'today_heading' => ['#markup' => '<div class="famtastic-command__section-heading"><div><span>Owner view</span><h2>Campaign pulse</h2></div><p>Provider and business outcomes update as verified events arrive.</p></div>'],
      'today' => $this->commandCards($todayCards),
      'channels_heading' => ['#markup' => '<div class="famtastic-command__section-heading"><div><span>Publishing</span><h2>Channel health</h2></div><p>Live connection state per platform, read from the Postiz API.</p></div>'],
      'channels' => $this->commandCards($channelCards),
      'attention_heading' => ['#markup' => '<div class="famtastic-command__section-heading"><div><span>Next actions</span><h2>Needs your attention</h2></div></div>'],
      'attention' => $this->attentionList($attentionItems),
      'calendar_heading' => ['#markup' => '<div class="famtastic-command__section-heading"><div><span>17-day launch</span><h2>Content calendar</h2></div><p>Teach · Challenge · Prove · Invite every day, adapted per channel.</p></div>'],
      'calendar' => $this->campaignCalendar($campaignDays),
      'operations_heading' => ['#markup' => '<div class="famtastic-command__section-heading"><div><span>Lifecycle evidence</span><h2>Campaign operations</h2></div></div>'],
      'summary' => $this->metricCards($summary),
      'campaign_heading' => ['#markup' => '<h2 id="campaigns">Campaigns</h2>'],
      'campaigns' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__table-scroll']],
        'table' => [
          '#type' => 'table',
          '#header' => ['Campaign', 'Status', 'Source', 'Prospects', 'Proofs', 'Sent', 'Clicks', 'Sales', 'Builds'],
          '#rows' => $rows,
          '#empty' => $this->t('No campaigns have been recorded.'),
          '#attributes' => ['class' => ['famtastic-ops__table']],
        ],
      ],
      'pager' => ['#type' => 'pager'],
    ], 'Campaign Operations');
  }

  /** Returns verified social/content events without treating attempts as proof. */
  private function socialCampaignEventCounts(): array {
    $types = [
      'approved' => ['social.content.approved', 'social.media.approved', 'social.publish.approved'],
      'scheduled' => ['social.post.scheduled'],
      'published' => ['social.post.verified', 'social.post.published'],
      'failed' => ['social.post.failed', 'social.post.rejected', 'social.post.unverified'],
      'visits' => ['social.visit.attributed'],
      'leads' => ['social.lead.attributed'],
      'sales' => ['social.sale.attributed'],
    ];
    $counts = [];
    foreach ($types as $key => $eventTypes) {
      $counts[$key] = $this->countIn('famtastic_event', 'event_type', $eventTypes);
    }
    return $counts;
  }

  /** Builds compact, mobile-first owner KPI cards. */
  private function commandCards(array $items): array {
    $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-command__pulse']]];
    foreach ($items as $index => [$label, $value, $detail, $tone]) {
      $build['item_' . $index] = ['#markup' => '<article class="famtastic-command__pulse-card famtastic-command__pulse-card--' . Html::getClass($tone) . '"><span>' . Html::escape($label) . '</span><strong>' . Html::escape($value) . '</strong><p>' . Html::escape($detail) . '</p></article>'];
    }
    return $build;
  }

  /**
   * Builds per-platform publishing channel cards from the Postiz API.
   */
  private function channelHealthCards(): array {
    $snapshot = $this->postizChannels->channels();
    if (!$snapshot['configured']) {
      return [
        ['Channel health', 'Not configured', 'Set FAMTASTIC_POSTIZ_API_KEY (settings or env) to show live channel state.', 'neutral'],
      ];
    }
    if (!$snapshot['reachable']) {
      return [
        ['Channel health', 'Unreachable', $snapshot['error'] ?: 'Postiz API did not respond.', 'danger'],
      ];
    }
    $toneByState = ['connected' => 'good', 'expiring' => 'attention', 'disabled' => 'attention', 'error' => 'danger'];
    $cards = [];
    foreach ($snapshot['platforms'] as $platform) {
      $cards[] = [
        ucfirst($platform['identifier']) . ' · ' . $platform['name'],
        ucfirst($platform['state']),
        $platform['detail'],
        $toneByState[$platform['state']] ?? 'neutral',
      ];
    }
    if ($cards === []) {
      $cards[] = ['Channel health', 'No channels connected', 'Postiz is reachable but no platform OAuth has completed.', 'neutral'];
    }
    return $cards;
  }

  /** Builds the prioritized owner action queue. */
  private function attentionList(array $items): array {
    if ($items === []) {
      return ['#markup' => '<div class="famtastic-command__all-clear"><strong>All clear.</strong><span>No campaign exception currently needs owner action.</span></div>'];
    }
    $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-command__attention-list']]];
    foreach ($items as $index => $item) {
      [$title, $detail, $action] = $item;
      $url = $item[3] ?? NULL;
      $actionMarkup = $url instanceof Url
        ? Link::fromTextAndUrl($action . ' →', $url->setOption('attributes', ['class' => ['famtastic-command__attention-action']]))->toRenderable()
        : ['#markup' => '<b>' . Html::escape($action) . ' →</b>'];
      $build['item_' . $index] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-command__attention-item']],
        '#children' => [],
        'lead' => ['#markup' => '<span aria-hidden="true">!</span><div><strong>' . Html::escape($title) . '</strong><p>' . Html::escape($detail) . '</p></div>'],
        'action' => $actionMarkup,
      ];
    }
    return $build;
  }

  /**
   * Live per-record command surface for the 17-day campaign: every record,
   * its gates, and one-click approve/revoke per gate.
   */
  private function socialRecordsMetric(): array {
    $rows = [];
    foreach ($this->database->select('famtastic_social_record', 'r')->fields('r')
      ->orderBy('r.day')->orderBy('r.id')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $cells = [];
      foreach (['content' => 'approval_content', 'media' => 'approval_media', 'publish' => 'approval_publish'] as $gate => $col) {
        $on = (int) $record[$col] === 1;
        $cells[] = ['data' => [
          '#markup' => '<span class="famtastic-ops__badge famtastic-ops__badge--' . ($on ? 'good' : 'off') . '">' . ($on ? '✓' : '—') . '</span> ',
          'link' => ['data' => $this->linkCell(Link::fromTextAndUrl($on ? 'revoke' : 'approve', Url::fromRoute('famtastic_pipeline.social_record_gate',
            ['content_id' => $record['content_id'], 'gate' => $gate, 'direction' => $on ? 'revoke' : 'approve'])))],
        ]];
      }
      $draft = $record['postiz_draft_id'] !== ''
        ? $this->linkCell(Link::fromTextAndUrl('draft ↗', Url::fromUri('http://127.0.0.1:4007', ['attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer']])))
        : ['#markup' => '—'];
      $rows[] = [
        (int) $record['day'],
        (string) $record['moment'],
        (string) $record['promise'],
        (string) $record['scheduled_time_et'],
        ['data' => ['#markup' => $this->badge((string) $record['state'])]],
        $cells[0], $cells[1], $cells[2],
        ['data' => $draft],
      ];
    }
    $page = $this->recordsPage(
      'Campaign Record Gates',
      'Per-record approval state for the 17-day campaign, stored in your operations database. Approving content/media here is the owner decision the pipeline reads; publishing remains a separate bounded-batch decision.',
      ['Day', 'Moment', 'Promise', 'ET', 'State', 'Content', 'Media', 'Publish', 'Postiz'],
      $rows,
      'No campaign records are imported yet. Run backend/scripts/import-social-records.php against the campaign manifest.',
    );
    $page['import'] = ['#markup' => '<p class="famtastic-ops__lede"><code>drush php:script backend/scripts/import-social-records.php</code> syncs manifest state; approvals stored here always win.</p>'];
    return $page;
  }

  /** Renders the canonical 17-day campaign spine. */
  private function campaignCalendar(array $days): array {
    $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-command__calendar']]];
    // Live per-record state; days with no imported records show an honest
    // unknown instead of invented numbers. Missing table = not yet updatedb'd.
    if (!$this->database->schema()->tableExists('famtastic_social_record')) {
      $build = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-command__calendar']]];
      foreach ($days as $day => [$theme, $promise]) {
        $build['day_' . $day] = ['#markup' => '<article class="famtastic-command__calendar-day is-unknown"><div class="famtastic-command__day"><span>Day</span><strong>' . $day . '</strong></div><div><span>' . Html::escape((string) $theme) . '</span><h3>' . Html::escape((string) $promise) . '</h3><p>Record table pending module update.</p></div></article>'];
      }
      return $build;
    }
    $records = $this->database->select('famtastic_social_record', 'r')
      ->fields('r', ['content_id', 'day', 'moment', 'scheduled_time_et', 'state',
                     'approval_content', 'approval_media', 'approval_publish'])
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $byDay = [];
    foreach ($records as $record) {
      $byDay[(int) $record['day']][] = $record;
    }
    $queueUrl = Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'social-records']);
    foreach ($days as $day => [$theme, $promise]) {
      if (!isset($byDay[$day])) {
        $build['day_' . $day] = ['#markup' => '<article class="famtastic-command__calendar-day is-unknown"><div class="famtastic-command__day"><span>Day</span><strong>' . $day . '</strong></div><div><span>' . Html::escape((string) $theme) . '</span><h3>' . Html::escape((string) $promise) . '</h3><p>Record state not imported yet — no invented numbers.</p></div><em>' . Link::fromTextAndUrl('Import records', $queueUrl)->toString() . '</em></article>'];
        continue;
      }
      $lines = [];
      $gatesOn = 0;
      $gatesTotal = 0;
      foreach ($byDay[$day] as $record) {
        $on = ((int) $record['approval_content']) + ((int) $record['approval_media']) + ((int) $record['approval_publish']);
        $gatesOn += $on;
        $gatesTotal += 3;
        $lines[] = sprintf('%s %s · %s', $record['scheduled_time_et'] ?: '—', ucfirst((string) $record['moment']),
          '<span class="famtastic-ops__badge famtastic-ops__badge--' . ($on === 3 ? 'good' : ($on ? 'partial' : 'off')) . '">' . $on . '/3</span>');
      }
      $build['day_' . $day] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-command__calendar-day']],
        '#children' => [],
        'body' => ['#markup' => '<article><div class="famtastic-command__day"><span>Day</span><strong>' . $day . '</strong></div><div><span>' . Html::escape((string) $theme) . '</span><h3>' . Html::escape((string) $promise) . '</h3><p>' . implode('<br>', $lines) . '</p></div><em>' . \Drupal::service('renderer')->renderRoot($this->linkCell(Link::fromTextAndUrl('Review gates →', $queueUrl))) . '</em></article>'],
      ];
    }
    return $build;
  }

  /** Canonical themes mirror the stable campaign manifest. */
  private function fiftyFiveCentCampaignDays(): array {
    return [
      1 => ['Declaration', 'What 55 cents a day means'], 2 => ['Excuses', 'Why owners delay getting a website'],
      3 => ['Trust', 'What customers see when a business has no website'], 4 => ['Ownership', 'A website is a business home—not another rented profile'],
      5 => ['Discovery', 'How customers decide who to trust'], 6 => ['Domain', 'What a domain is and why your business needs one'],
      7 => ['Hosting', 'What hosting does and what the first year includes'], 8 => ['Offer', 'What the $199 Web Basics Bundle includes'],
      9 => ['Scope', 'Who the Web Basics Bundle is—and is not—for'], 10 => ['Mobile', 'Why the customer experience starts on a phone'],
      11 => ['Proof', 'From business idea to a useful online presence'], 12 => ['Action', 'Make it easy for customers to contact you'],
      13 => ['Objections', 'Your business is doing fine—until the customer cannot verify it'], 14 => ['Growth', 'A basic website can be the beginning, not the ceiling'],
      15 => ['Investment', 'FAMtastic invests in the first year with you'], 16 => ['Urgency', 'The cost objection has been removed'],
      17 => ['Invitation', 'Cost is not one of them. Period.'],
    ];
  }

  /**
   * Renders the standalone website analytics dashboard.
   */
  public function analytics(): array {
    $propertyId = (string) Settings::get('famtastic_google_analytics_property_id', '');
    $googleAnalyticsUrl = $propertyId === ''
      ? 'https://analytics.google.com/'
      : 'https://analytics.google.com/analytics/web/#/p' . rawurlencode($propertyId) . '/reports/intelligenthome';

    return $this->page([
      'intro' => [
        '#markup' => '<p class="famtastic-ops__lede">A focused 30-day view of website traffic and engagement. Use Google Analytics for realtime data, custom date ranges, comparisons, explorations, and account settings.</p>',
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__actions']],
        'google_analytics' => [
          '#type' => 'link',
          '#title' => $this->t('Open Google Analytics'),
          '#url' => Url::fromUri($googleAnalyticsUrl),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ],
        ],
        'campaigns' => [
          '#type' => 'link',
          '#title' => $this->t('View Campaign Operations'),
          '#url' => Url::fromRoute('famtastic_pipeline.campaign_operations'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'heading' => ['#markup' => '<h2>Website Analytics <small>(last 30 days)</small></h2>'],
      'report' => $this->webAnalytics($this->googleAnalytics->dashboardReport()),
    ], 'Website Analytics');
  }

  /**
   * Builds the cached Google Analytics summary and detail tables.
   */
  private function webAnalytics(array $report): array {
    if (empty($report['available'])) {
      return ['#markup' => '<p class="famtastic-ops__empty">' . Html::escape((string) ($report['message'] ?? 'Analytics unavailable.')) . '</p>'];
    }
    $build = ['metrics' => $this->metricCards($report['metrics'])];
    foreach (['pages' => ['Top Pages', ['Page', 'Views']], 'sources' => ['Traffic Channels', ['Channel', 'Sessions']]] as $key => [$title, $header]) {
      $build[$key . '_heading'] = ['#markup' => '<h3>' . Html::escape($title) . '</h3>'];
      $build[$key] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => array_values($report[$key] ?? []),
        '#empty' => $this->t('No Analytics data has been recorded yet.'),
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ];
    }
    return $build;
  }

  /**
   * Renders the exact records behind one dashboard metric.
   */
  public function metric(string $metric): array {
    return match ($metric) {
      'campaigns' => $this->campaignMetric(),
      'prospects' => $this->prospectMetric(),
      'customers' => $this->customerMetric(),
      'website-requests' => $this->websiteRequestMetric(),
      'proofs-ready' => $this->proofMetric(),
      'emails-sent' => $this->eventMetric('email.sent', 'Emails Sent', 'Every recorded campaign send event.'),
      'clicks' => $this->eventMetric('email.clicked', 'Proof-Link Clicks', 'Every recorded proof-link click event.'),
      'paid-orders' => $this->paidOrderMetric(),
      'open-jobs' => $this->jobMetric(),
      'open-exceptions' => $this->exceptionMetric(),
      'support' => $this->supportMetric(),
      'referrals' => $this->referralMetric(),
      'services' => $this->serviceMetric(),
      'notifications' => $this->notificationMetric(),
      'replies' => $this->replyMetric(),
      'support-drafts' => $this->supportDraftMetric(),
      'social-records' => $this->socialRecordsMetric(),
      'workers' => $this->workerMetric(),
      'grant-codes' => $this->grantCodeMetric(),
      default => throw new NotFoundHttpException('Operations metric not found.'),
    };
  }

  private function websiteRequestMetric(): array {
    $query = $this->database->select('famtastic_project_request', 'r')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_customer', 'c', 'c.id = r.customer_id');
    $query->leftJoin('famtastic_organization', 'o', 'o.id = r.organization_id');
    $query->fields('r', ['id', 'project_name', 'business_name', 'project_type', 'domain_choice', 'existing_domain', 'recommendation_requested', 'status', 'proof_review_status', 'proof_campaign_id', 'prospect_id', 'commerce_order_id', 'intake_data', 'submitted_at', 'changed']);
    $query->addField('c', 'display_name', 'customer_name');
    $query->addField('c', 'email', 'customer_email');
    $query->addField('o', 'name', 'organization_name');
    $rows = [];
    foreach ($query->orderBy('r.changed', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $intake = json_decode((string) $record['intake_data'], TRUE) ?: [];
      $summary = array_filter([
        'Goal: ' . ($intake['primary_goal'] ?? ''),
        'Offers: ' . ($intake['products_services'] ?? ''),
        'Features: ' . ($intake['required_features'] ?? ''),
        'Timing: ' . ($intake['launch_timing'] ?? ''),
        'Notes: ' . ($intake['notes'] ?? ''),
      ], static fn(string $line): bool => !str_ends_with($line, ': '));
      $briefText = Html::escape(implode(' · ', $summary));
      if (mb_strlen($briefText) > 180) {
        $briefText = mb_substr($briefText, 0, 177) . '…';
      }
      $prospect = $record['prospect_id'] ? $this->linkCell(Link::fromTextAndUrl('#' . $record['prospect_id'], Url::fromUserInput('/admin/famtastic/prospect/' . $record['prospect_id'] . '/edit'))) : ['#markup' => '—'];
      $rows[] = [
        $record['project_name'], $record['organization_name'] ?: $record['business_name'],
        $record['customer_name'] . ' · ' . $record['customer_email'], ucwords(str_replace('_', ' ', $record['project_type'])),
        ['data' => ['#markup' => $this->badge($record['status']) . ' ' . $this->badge($record['proof_review_status'])]],
        ['data' => ['#theme' => 'item_list', '#items' => [
          $this->linkCell(Link::fromTextAndUrl('Proof review', Url::fromRoute('famtastic_pipeline.website_request_proof_review', ['website_request' => $record['id']]))),
          $this->linkCell(Link::fromTextAndUrl('Package / special price', Url::fromRoute('famtastic_pipeline.website_request_offer', ['website_request' => $record['id']]))),
        ]]],
        ['data' => $prospect], ['data' => ['#markup' => $briefText ?: 'Draft details not added yet']], $this->date((int) $record['changed']),
      ];
    }
    return $this->recordsPage('Website Requests', 'Customer-owned, resumable website interviews. Proofs remain owner-only until the explicit customer-send gate is approved.', ['Request', 'Business', 'Customer', 'Type', 'Status', 'Actions', 'Lead', 'Brief', 'Updated'], $rows, 'No website requests have been recorded.');
  }

  private function supportMetric(): array {
    $query = $this->database->select('famtastic_portal_thread', 't')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_organization', 'o', 'o.id = t.organization_id');
    $query->leftJoin('famtastic_support_case', 's', 's.thread_id = t.id');
    $query->fields('t', ['kind', 'subject', 'created', 'changed'])->fields('s', ['case_number', 'priority', 'status', 'response_due'])->addField('o', 'name', 'organization');
    $rows = [];
    foreach ($query->orderBy('t.changed', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $rows[] = [$record['case_number'] ?: 'Legacy', $record['organization'] ?: 'Individual', $record['subject'], ['data' => ['#markup' => $this->badge($record['priority'] ?: $record['kind'])]], ['data' => ['#markup' => $this->badge($record['status'] ?: 'open')]], $this->date((int) $record['response_due']), $this->date((int) $record['changed'])];
    }
    foreach ($rows as $i => $row) {
      $rows[$i][] = ['data' => $this->linkCell(Link::fromTextAndUrl('Reply', Url::fromRoute('famtastic_pipeline.support_reply', ['case_number' => (string) $row[0]])))];
    }
    return $this->recordsPage('Customer Support', 'Customer-visible cases with ownership, priority, response targets, and conversation history.', ['Case', 'Customer', 'Subject', 'Priority', 'Status', 'Response due', 'Updated', 'Action'], $rows, 'No support conversations have been recorded.');
  }

  private function notificationMetric(): array {
    $rows = [];
    $query = $this->database->select('famtastic_notification_outbox', 'n')->extend(PagerSelectExtender::class);
    foreach ($query->fields('n', ['category', 'recipient', 'subject', 'status', 'attempts', 'last_error', 'changed'])->orderBy('changed', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $age = max(0, \Drupal::time()->getRequestTime() - (int) $record['changed']);
      $rows[] = [$record['category'], $record['recipient'], $record['subject'], ['data' => ['#markup' => $this->badge($record['status'])]], $record['attempts'], $record['last_error'] ?: '—', $age > 300 && in_array($record['status'], ['queued', 'retry'], TRUE) ? round($age / 60) . ' minutes' : 'Current', $this->date((int) $record['changed'])];
    }
    return $this->recordsPage('Notifications', 'Transactional and operational delivery state, retries, queue age, and dead letters.', ['Category', 'Recipient', 'Subject', 'Status', 'Attempts', 'Last error', 'Queue age', 'Updated'], $rows, 'No notifications have been queued.');
  }

  /**
   * Renders validated inbound mailbox messages behind the replies metric.
   */
  private function replyMetric(): array {
    $messages = $this->database->select('famtastic_inbound_message', 'i')
      ->extend(PagerSelectExtender::class)
      ->fields('i', ['thread_public_id', 'sender_hash', 'subject', 'status', 'rejection_reason', 'received_at'])
      ->orderBy('i.received_at', 'DESC')
      ->orderBy('i.id', 'DESC')
      ->limit(50)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    // Inbound senders are stored as hashes; resolve them against the durable
    // customer directory so operators see who replied without new PII storage.
    $senders = [];
    foreach ($this->database->select('famtastic_customer', 'c')->fields('c', ['email', 'display_name'])->execute()->fetchAll(\PDO::FETCH_ASSOC) as $customer) {
      $senders[hash('sha256', mb_strtolower((string) $customer['email']))] = trim((string) $customer['display_name']) . ' · ' . $customer['email'];
    }
    $rows = [];
    foreach ($messages as $message) {
      $reason = (string) $message['rejection_reason'];
      $matchCell = $this->badge((string) $message['status']) . ($reason !== '' ? '<br><small>' . Html::escape(str_replace('_', ' ', $reason)) . '</small>' : '');
      $rows[] = [
        'sender' => $senders[(string) $message['sender_hash']] ?? ('Unknown sender (' . substr((string) $message['sender_hash'], 0, 12) . '…)'),
        'subject' => (string) $message['subject'],
        'match' => ['data' => ['#markup' => $matchCell]],
        'thread' => (string) ($message['thread_public_id'] ?: '—'),
        'received' => $this->date((int) $message['received_at']),
      ];
    }
    return $this->recordsPage(
      'Customer Replies',
      'Every validated inbound customer email with its thread match result, so no reply can be silently lost.',
      ['Sender', 'Subject', 'Match status', 'Thread', 'Received'],
      $rows,
      'No inbound messages have been received.',
    );
  }

  /**
   * L0 support draft queue: every inbound reply, its classification, and the
   * owner decision state. Nothing sends without a decision here (step B2).
   */
  private function supportDraftMetric(): array {
    $now = \Drupal::time()->getRequestTime();
    $query = $this->database->select('famtastic_support_draft', 'd')->extend(PagerSelectExtender::class);
    $query->join('famtastic_inbound_message', 'm', 'm.id = d.message_id');
    $query->fields('d', ['id', 'intent', 'confidence', 'escalate', 'status', 'body', 'created', 'decided_at', 'sla_target_seconds'])
      ->fields('m', ['subject', 'received_at', 'thread_public_id']);
    $rows = [];
    foreach ($query->orderBy('d.created', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $age = $now - (int) $record['created'];
      $target = (int) $record['sla_target_seconds'] ?: 86400;
      $slaState = $record['status'] === 'pending' && $age > $target
        ? $this->badge('breached') . ' +' . round(($age - $target) / 60) . 'min'
        : $this->badge('within_sla');
      $decision = $record['status'] === 'pending'
        ? $this->linkCell(Link::fromTextAndUrl('Review', Url::fromRoute('famtastic_pipeline.support_draft_decision', ['id' => $record['id']])))
        : $this->date((int) $record['decided_at']);
      $rows[] = [
        $this->date((int) $record['received_at']),
        (string) $record['subject'],
        ['data' => ['#markup' => $this->badge((string) $record['intent'])]],
        ((int) round(((float) $record['confidence']) * 100)) . '%',
        (int) $record['escalate'] ? $this->badge('escalate') : $this->badge('normal'),
        ['data' => ['#markup' => $slaState]],
        ['data' => ['#markup' => $this->badge((string) $record['status'])]],
        mb_strimwidth(strip_tags((string) $record['body']), 0, 90, '…'),
        ['data' => $decision],
      ];
    }
    return $this->recordsPage(
      'Support Drafts (L0)',
      'Every inbound message gets one deterministic draft. Nothing sends without an owner decision — approving queues the reply through the reviewed outbox path.',
      ['Received', 'Subject', 'Intent', 'Confidence', 'Triage', 'SLA', 'Status', 'Draft preview', 'Decision'],
      $rows,
      'No support drafts have been generated yet. They appear automatically as inbound messages arrive.',
    );
  }

  private function workerMetric(): array {    $rows = [];
    $query = $this->database->select('famtastic_worker_heartbeat', 'w')->extend(PagerSelectExtender::class);
    foreach ($query->fields('w')->orderBy('worker_key')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $late = (int) $record['next_due'] > 0 && (int) $record['next_due'] < \Drupal::time()->getRequestTime();
      $status = $late ? 'stale' : $record['status'];
      $rows[] = [$record['worker_key'], ['data' => ['#markup' => $this->badge($status)]], $this->date((int) $record['last_started']), $this->date((int) $record['last_finished']), $this->date((int) $record['next_due']), $record['processed'], $record['failed'], $record['last_error'] ?: ($late ? 'Worker missed its expected interval.' : '—')];
    }
    return $this->recordsPage('Automation Workers', 'Worker heartbeat, schedules, processing totals, and failures.', ['Worker', 'Status', 'Started', 'Finished', 'Next due', 'Processed', 'Failed', 'Last error'], $rows, 'No worker heartbeat has been recorded.');
  }

  private function grantCodeMetric(): array {
    $rows = [];
    $query = $this->database->select('famtastic_grant_code', 'g')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_customer', 'c', 'c.id = g.customer_id');
    $query->leftJoin('famtastic_project_request', 'r', 'r.id = g.website_request_id');
    $query->fields('g', ['code_prefix', 'label', 'grant_class', 'sku', 'discount_type', 'discount_value', 'redemptions', 'max_redemptions', 'expires_at', 'covers_renewal', 'status', 'created']);
    $query->addField('c', 'email', 'customer_email');
    $query->addField('r', 'project_name', 'request_name');
    foreach ($query->orderBy('g.created', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $discount = $record['discount_type'] === 'free' ? 'Free initial SKU' : ($record['discount_type'] === 'percent' ? (((int) $record['discount_value']) / 100) . '%' : $this->formatCurrency((int) $record['discount_value'], 'usd'));
      $rows[] = [$record['code_prefix'] . '-••••••', $record['label'], $record['grant_class'], $record['customer_email'] ?: 'Unscoped', $record['request_name'] ?: 'Any allowed request', $record['sku'], $discount, $record['redemptions'] . ' / ' . $record['max_redemptions'], $record['covers_renewal'] ? 'Yes' : 'Initial term only', ['data' => ['#markup' => $this->badge($record['status'])]], $this->date((int) $record['expires_at'])];
    }
    $page = $this->recordsPage('Private Grant Codes', 'Raw codes are never stored or shown again. Every redemption is account, request, SKU, limit, and expiry checked.', ['Code', 'Label', 'Class', 'Customer', 'Request', 'SKU', 'Benefit', 'Uses', 'Renewal', 'Status', 'Expires'], $rows, 'No grant codes exist.');
    $page['create'] = ['#type' => 'link', '#title' => $this->t('Create private grant code'), '#url' => Url::fromRoute('famtastic_pipeline.grant_code_create'), '#attributes' => ['class' => ['button', 'button--primary']]];
    $page['create']['#weight'] = -20;
    return $page;
  }

  private function referralMetric(): array {
    $query = $this->database->select('famtastic_referral', 'r')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_customer', 'c', 'c.id = r.customer_id');
    $query->fields('r', ['friend_name', 'status', 'reward_status', 'created'])->addField('c', 'display_name', 'customer');
    $rows = [];
    foreach ($query->orderBy('r.created', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $rows[] = [$record['customer'] ?: 'Unknown', $record['friend_name'], ['data' => ['#markup' => $this->badge($record['status'])]], ['data' => ['#markup' => $this->badge($record['reward_status'])]], $this->date((int) $record['created'])];
    }
    return $this->recordsPage('Customer Referrals', 'Permission-confirmed referrals without exposing referred-customer activity.', ['Referred by', 'Friend', 'Status', 'Reward', 'Created'], $rows, 'No customer referrals have been recorded.');
  }

  private function serviceMetric(): array {
    $query = $this->database->select('famtastic_entitlement', 'e')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_organization', 'o', 'o.id = e.organization_id');
    $query->fields('e', ['entitlement_type', 'status', 'included_until', 'renews_at', 'amount_minor', 'billing_interval'])->addField('o', 'name', 'organization');
    $rows = [];
    foreach ($query->orderBy('e.changed', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $rows[] = [$record['organization'] ?: 'Individual', ucwords(str_replace('_', ' ', $record['entitlement_type'])), ['data' => ['#markup' => $this->badge($record['status'])]], $this->date((int) $record['included_until']), $this->date((int) $record['renews_at']), $record['amount_minor'] ? $this->formatCurrency((int) $record['amount_minor'], 'usd') . ' / ' . $record['billing_interval'] : 'Included'];
    }
    return $this->recordsPage('Customer Services', 'Commerce-controlled capabilities, coverage, and renewal timing.', ['Customer', 'Service', 'Status', 'Included through', 'Renews', 'Renewal'], $rows, 'No customer services have been granted.');
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
          $proofLink = Link::fromTextAndUrl('Open Proof', Url::fromUri((string) $preview, [
            'attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
          ]))->toRenderable();
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
            $links[] = Link::fromTextAndUrl(
              strtoupper((string) $variant->get('direction_id')->value),
              Url::fromUri($url, [
                'attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
              ]),
            )->toString();
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
      'back' => Link::fromTextAndUrl('← All Campaigns', Url::fromRoute('famtastic_pipeline.campaign_operations'))->toRenderable(),
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
    $factFields = [
      'build_key',
      'campaign_key',
      'flow_key',
      'task_key',
      'provider',
      'agent_name',
      'status',
      'source_sha',
      'artifact_checksum',
    ];
    foreach ($factFields as $field) {
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

  /**
   * Renders the campaign records behind the dashboard total.
   */
  private function campaignMetric(): array {
    $records = $this->database->select('famtastic_campaign', 'c')
      ->extend(PagerSelectExtender::class)
      ->fields('c')
      ->orderBy('created', 'DESC')
      ->limit(50)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $rows = [];
    foreach ($records as $record) {
      $key = (string) $record['campaign_key'];
      $rows[] = [
        'campaign' => $this->linkCell(Link::fromTextAndUrl($key, Url::fromRoute('famtastic_pipeline.operations_campaign', ['campaign_key' => $key]))),
        'status' => ['data' => ['#markup' => $this->badge((string) $record['status'])]],
        'source' => $this->sourceLabel((string) ($record['source_filter'] ?? '')),
        'prospects' => count($this->prospectIds($key)),
        'created' => $this->date((int) $record['created']),
      ];
    }
    return $this->recordsPage(
      'Campaigns',
      'Every tracked outreach campaign. Open a campaign to inspect its recipients, messages, proofs, builds, and outcomes.',
      ['Campaign', 'Status', 'Source', 'Prospects', 'Created'],
      $rows,
      'No campaigns have been recorded.',
    );
  }

  /**
   * Renders the prospect records behind the dashboard total.
   */
  private function prospectMetric(): array {
    $storage = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->pager(50)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $prospect) {
      $workspace = Url::fromRoute('famtastic_pipeline.prospect_workspace', ['famtastic_prospect' => $prospect->id()]);
      $needsReply = $this->prospectNeedsHumanReply($prospect);
      $rows[] = [
        'business' => $this->linkCell(Link::fromTextAndUrl($prospect->label() ?: '(no name)', $workspace)),
        'status' => ['data' => ['#markup' => $this->badge((string) $prospect->get('status')->value)]],
        'campaign' => $this->campaignLinkCell((string) $prospect->get('campaign')->value),
        'category' => (string) ($prospect->get('business_category')->value ?: '—'),
        'email' => (string) ($prospect->get('public_email')->value ?: '—'),
        'next_action' => $needsReply
          ? ['data' => ['#markup' => '<strong>Review today</strong><br><small>Prepare a specific next step</small>']]
          : ['data' => ['#markup' => '<strong>Open workspace</strong><br><small>Review the lead record</small>']],
        'actions' => ['data' => Link::fromTextAndUrl('Open lead workspace', $workspace)->toRenderable()],
        'created' => $this->date((int) $prospect->get('created')->value),
      ];
    }
    $replyCount = $this->countProspectsNeedingHumanReply();
    return $this->page([
      'back' => Link::fromTextAndUrl('← Operations Dashboard', Url::fromRoute('famtastic_pipeline.operations'))->toRenderable(),
      'intro' => ['#markup' => '<p class="famtastic-ops__lede">Open a lead workspace to see the request, facts, history, related customer work, and the next owner-approved move. Acknowledgment is not a sales reply; no message, price, proof, domain, or deployment is sent automatically.</p>'],
      'priority' => ['#markup' => '<section class="famtastic-leads__priority"><div><span>Needs a human response</span><strong>' . $replyCount . '</strong><p>Public quote requests with no recorded first response.</p></div><div><span>How this queue works</span><p>AI may organize facts and prepare a draft. A person approves the message, scope, price, proof, and launch.</p></div></section>'],
      'records' => [
        '#type' => 'table',
        '#header' => ['Business', 'Status', 'Campaign', 'Category', 'Public Email', 'Next action', 'Open', 'Created'],
        '#rows' => $rows,
        '#empty' => $this->t('No prospects have been recorded.'),
        '#attributes' => ['class' => ['famtastic-ops__table', 'famtastic-leads__table']],
      ],
      'pager' => ['#type' => 'pager'],
    ], 'Prospects');
  }

  /**
   * Renders the operator workspace for one lead instead of a generic entity page.
   */
  public function prospectWorkspace(Prospect $famtastic_prospect): array {
    $prospect = $famtastic_prospect;
    $prospectId = (int) $prospect->id();
    $requestFields = ['id', 'public_id', 'project_name', 'status', 'changed'];
    // Keep the workspace usable while a pre-existing site is waiting for its
    // module update. New installations and current production include it.
    if ($this->database->schema()->fieldExists('famtastic_project_request', 'proof_review_status')) {
      $requestFields[] = 'proof_review_status';
    }
    $relatedRequest = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', $requestFields)
      ->condition('prospect_id', $prospectId)
      ->orderBy('changed', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() ?: NULL;
    $events = $this->database->select('famtastic_event', 'e')
      ->fields('e', ['event_type', 'provider', 'occurred_at', 'recorded_at'])
      ->condition('prospect_id', $prospectId)
      ->orderBy('occurred_at', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 12)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $business = $prospect->label() ?: '(no name)';
    $status = (string) $prospect->get('status')->value;
    $firstResponse = (int) $prospect->get('first_responded_at')->value;
    $nextAction = $this->prospectNeedsHumanReply($prospect)
      ? ['Review the lead and prepare a tailored first reply.', 'The automated acknowledgment is not the sales conversation. Confirm the request, then approve a helpful next step.']
      : ['Review the lead record and keep the next action current.', 'Use this workspace to confirm what happened before advancing the relationship.'];
    $facts = array_filter([
      'Business category' => (string) $prospect->get('business_category')->value,
      'Contact name' => (string) $prospect->get('contact_name')->value,
      'Email' => (string) $prospect->get('public_email')->value,
      'Phone' => (string) $prospect->get('public_phone')->value,
      'Existing website' => (string) $prospect->get('website_url')->value,
      'Service area' => (string) $prospect->get('service_area')->value,
      'Description' => (string) $prospect->get('business_description')->value,
    ], static fn(string $value): bool => trim($value) !== '');
    $factRows = [];
    foreach ($facts as $label => $value) {
      $factRows[] = [$label, $value];
    }
    $eventRows = array_map(fn(array $event): array => [
      ucwords(str_replace(['.', '_'], ' ', (string) $event['event_type'])),
      (string) ($event['provider'] ?: 'FAMtastic pipeline'),
      $this->date((int) ($event['occurred_at'] ?: $event['recorded_at'])),
    ], $events);
    $requestPanel = ['#markup' => '<p class="famtastic-ops__empty">No account-based website request is connected yet. A concise, owner-approved reply should invite the customer to register and complete the guided website intake before a proof or price is proposed.</p>'];
    if ($relatedRequest) {
      $requestPanel = [
        '#type' => 'table',
        '#header' => ['Request', 'Status', 'Proof review', 'Updated'],
        '#rows' => [[
          $this->linkCell(Link::fromTextAndUrl((string) ($relatedRequest['project_name'] ?: $relatedRequest['public_id']), Url::fromRoute('famtastic_pipeline.website_request_proof_review', ['website_request' => $relatedRequest['id']]))),
          ['data' => ['#markup' => $this->badge((string) $relatedRequest['status'])]],
          ['data' => ['#markup' => $this->badge((string) ($relatedRequest['proof_review_status'] ?? 'not started'))]],
          $this->date((int) $relatedRequest['changed']),
        ]],
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ];
    }

    return $this->page([
      'back' => Link::fromTextAndUrl('← Back to prospects', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'prospects']))->toRenderable(),
      'hero' => ['#markup' => '<section class="famtastic-lead__hero"><div><span>Lead workspace</span><h2>' . Html::escape($business) . '</h2><p>Source: ' . Html::escape((string) ($prospect->get('campaign')->value ?: 'direct')) . ' · Created ' . Html::escape($this->date((int) $prospect->get('created')->value)) . '</p></div><div><span>Current state</span>' . $this->badge($status) . '</div></section>'],
      'next' => ['#markup' => '<section class="famtastic-lead__next"><span>Next owner action</span><h3>' . Html::escape($nextAction[0]) . '</h3><p>' . Html::escape($nextAction[1]) . '</p><small>' . ($firstResponse ? 'First response recorded ' . Html::escape($this->date($firstResponse)) . '.' : 'No first response has been recorded.') . '</small></section>'],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__actions']],
        'edit' => ['#type' => 'link', '#title' => $this->t('Edit lead facts'), '#url' => $prospect->toUrl('edit-form'), '#attributes' => ['class' => ['button', 'button--primary']]],
        'request' => ['#type' => 'link', '#title' => $this->t('View website requests'), '#url' => Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'website-requests']), '#attributes' => ['class' => ['button']]],
      ],
      'facts_heading' => ['#markup' => '<h3>What we know</h3>'],
      'facts' => ['#type' => 'table', '#header' => ['Field', 'Value'], '#rows' => $factRows, '#empty' => $this->t('No usable lead details have been recorded yet.'), '#attributes' => ['class' => ['famtastic-ops__table']]],
      'request_heading' => ['#markup' => '<h3>Customer continuation</h3>'],
      'request' => $requestPanel,
      'history_heading' => ['#markup' => '<h3>Recorded activity</h3>'],
      'history' => ['#type' => 'table', '#header' => ['Event', 'Source', 'When'], '#rows' => $eventRows, '#empty' => $this->t('No timeline events have been recorded yet.'), '#attributes' => ['class' => ['famtastic-ops__table']]],
      'boundary' => ['#markup' => '<aside class="famtastic-lead__boundary"><strong>Automation boundary</strong><p>Research, qualification notes, follow-up reminders, and a tailored draft can be prepared here. A person still approves every outgoing message, quote, proof, domain, payment, and deployment.</p></aside>'],
    ], $business . ' lead workspace');
  }

  /** Determines whether a prospect needs a human first response. */
  private function prospectNeedsHumanReply(Prospect $prospect): bool {
    $campaign = (string) $prospect->get('campaign')->value;
    $status = (string) $prospect->get('status')->value;
    return $campaign === 'public_quote'
      && (int) $prospect->get('first_responded_at')->value === 0
      && !in_array($status, ['lost', 'paid', 'launched'], TRUE);
  }

  /** Counts the public quote leads that still need a human first response. */
  private function countProspectsNeedingHumanReply(): int {
    $ids = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign', 'public_quote')
      ->notExists('first_responded_at')
      ->execute();
    return count($ids);
  }

  /**
   * Staff customer lookup across identity, acquisition, and retention state.
   */
  private function customerMetric(): array {
    $query = $this->database->select('famtastic_customer', 'c')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_membership', 'm', 'm.customer_id = c.id AND m.status = :member_status', [':member_status' => 'active']);
    $query->leftJoin('famtastic_organization', 'o', 'o.id = m.organization_id');
    $query->fields('c', ['display_name', 'email', 'phone', 'acquisition_source', 'marketing_status', 'verified_at', 'created']);
    $query->addField('o', 'name', 'organization_name');
    $query->addField('m', 'role');
    $records = $query->orderBy('c.created', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $rows = [];
    foreach ($records as $record) {
      $rows[] = [
        $record['display_name'], $record['organization_name'] ?: 'Individual', $record['email'],
        $record['phone'] ?: '—', $record['role'] ?: '—', $record['acquisition_source'],
        ['data' => ['#markup' => $this->badge($record['verified_at'] ? 'verified' : 'unverified')]],
        ['data' => ['#markup' => $this->badge((string) $record['marketing_status'])]],
        $this->date((int) $record['created']),
      ];
    }
    return $this->recordsPage(
      'Customers',
      'Durable customer accounts and their business workspaces, searchable by the browser table filter.',
      ['Customer', 'Business', 'Email', 'Phone', 'Role', 'Source', 'Identity', 'Marketing', 'Created'],
      $rows,
      'No customer accounts have been created.',
    );
  }

  /**
   * Renders the ready-proof records behind the dashboard total.
   */
  private function proofMetric(): array {
    $proofStorage = $this->pipelineEntityTypeManager->getStorage('proof_campaign');
    $prospectStorage = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect');
    $ids = $proofStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('generation_status', 'ready')
      ->sort('ready_at', 'DESC')
      ->sort('id', 'DESC')
      ->pager(50)
      ->execute();
    $rows = [];
    foreach ($proofStorage->loadMultiple($ids) as $proof) {
      $prospect = $prospectStorage->load((int) $proof->get('prospect_id')->target_id);
      $selected = (string) ($proof->get('selected_variant')->value ?: '—');
      $package = (string) ($proof->get('selected_package')->value ?: '—');
      $rows[] = [
        'business' => (string) ($proof->get('business_name')->value ?: '—'),
        'proof' => $this->linkCell($proof->toLink((string) $proof->get('campaign_id')->value)),
        'campaign' => $this->campaignLinkCell($prospect ? (string) $prospect->get('campaign')->value : ''),
        'status' => ['data' => ['#markup' => $this->badge((string) $proof->get('status')->value)]],
        'selection' => $selected . ' / ' . $package,
        'ready' => $this->date((int) ($proof->get('ready_at')->value ?? 0)),
      ];
    }
    return $this->recordsPage(
      'Proofs Ready',
      'Every proof campaign with a completed three-direction proof set.',
      ['Business', 'Proof Campaign', 'Lead Campaign', 'Status', 'Selection / Package', 'Ready'],
      $rows,
      'No ready proofs have been recorded.',
    );
  }

  /**
   * Renders event records for one dashboard event metric.
   */
  private function eventMetric(string $eventType, string $title, string $description): array {
    $events = $this->database->select('famtastic_event', 'e')
      ->extend(PagerSelectExtender::class)
      ->fields('e')
      ->condition('event_type', $eventType)
      ->orderBy('occurred_at', 'DESC')
      ->limit(50)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $prospectStorage = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect');
    $rows = [];
    foreach ($events as $event) {
      $prospect = $prospectStorage->load((int) $event['prospect_id']);
      $payload = json_decode((string) $event['payload'], TRUE);
      $messageId = (int) ($payload['message_id'] ?? 0);
      $message = $messageId > 0 ? $this->database->select('famtastic_email_message', 'm')
        ->fields('m', ['id', 'subject'])
        ->condition('id', $messageId)
        ->execute()
        ->fetchAssoc() : FALSE;
      $messageCell = $message
        ? $this->linkCell(Link::fromTextAndUrl((string) $message['subject'], Url::fromRoute('famtastic_pipeline.operations_message', ['message' => (int) $message['id']])))
        : '—';
      $rows[] = [
        'business' => $prospect ? $this->linkCell($prospect->toLink($prospect->label())) : 'Missing prospect',
        'campaign' => $this->campaignIdLinkCell((int) $event['campaign_id']),
        'message' => $messageCell,
        'provider' => (string) ($event['provider'] ?: '—'),
        'occurred' => $this->date((int) $event['occurred_at']),
      ];
    }
    return $this->recordsPage(
      $title,
      $description,
      ['Business', 'Campaign', 'Message', 'Provider', 'Occurred'],
      $rows,
      'No matching events have been recorded.',
    );
  }

  /**
   * Renders the verified paid orders behind the dashboard total.
   */
  private function paidOrderMetric(): array {
    $orderStorage = $this->pipelineEntityTypeManager->getStorage('famtastic_order');
    $prospectStorage = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect');
    $ids = $orderStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('payment_status', 'paid')
      ->sort('paid_at', 'DESC')
      ->sort('id', 'DESC')
      ->pager(50)
      ->execute();
    $rows = [];
    foreach ($orderStorage->loadMultiple($ids) as $order) {
      $prospect = $prospectStorage->load((int) $order->get('prospect_ref')->target_id);
      $rows[] = [
        'order' => $this->linkCell($order->toLink('Order #' . $order->id())),
        'business' => $prospect ? $this->linkCell($prospect->toLink($prospect->label())) : 'Missing prospect',
        'campaign' => $this->campaignLinkCell($prospect ? (string) $prospect->get('campaign')->value : ''),
        'package' => (string) $order->get('package')->value,
        'amount' => $this->formatCurrency((int) $order->get('amount')->value, (string) $order->get('currency')->value),
        'status' => ['data' => ['#markup' => $this->badge((string) $order->get('payment_status')->value)]],
        'paid' => $this->date((int) ($order->get('paid_at')->value ?? 0)),
      ];
    }
    return $this->recordsPage(
      'Paid Orders',
      'Every order whose payment was verified by the server-side payment lifecycle.',
      ['Order', 'Business', 'Campaign', 'Package', 'Amount', 'Status', 'Paid'],
      $rows,
      'No paid orders have been recorded.',
    );
  }

  /**
   * Renders jobs that still need processing.
   */
  private function jobMetric(): array {
    $jobs = $this->database->select('famtastic_job', 'j')
      ->extend(PagerSelectExtender::class)
      ->fields('j')
      ->condition('status', ['queued', 'retry', 'running'], 'IN')
      ->orderBy('changed', 'DESC')
      ->limit(50)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $prospectStorage = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect');
    $rows = [];
    foreach ($jobs as $job) {
      $prospect = $prospectStorage->load((int) $job['prospect_id']);
      $rows[] = [
        'job' => (string) $job['job_key'],
        'type' => (string) $job['job_type'],
        'business' => $prospect ? $this->linkCell($prospect->toLink($prospect->label())) : 'Missing prospect',
        'status' => ['data' => ['#markup' => $this->badge((string) $job['status'])]],
        'attempts' => (int) $job['attempts'] . ' / ' . (int) $job['max_attempts'],
        'available' => $this->date((int) $job['available_at']),
        'error' => $this->truncate((string) ($job['last_error'] ?? '')),
      ];
    }
    return $this->recordsPage(
      'Open Jobs',
      'Queued, retrying, or currently running automation work that still needs completion.',
      ['Job', 'Type', 'Business', 'Status', 'Attempts', 'Available', 'Last Error'],
      $rows,
      'No open jobs are waiting.',
    );
  }

  /**
   * Renders actionable exceptions that are still open.
   */
  private function exceptionMetric(): array {
    $exceptions = $this->database->select('famtastic_exception', 'e')
      ->extend(PagerSelectExtender::class)
      ->fields('e')
      ->condition('status', ['open', 'retry'], 'IN')
      ->orderBy('severity', 'DESC')
      ->orderBy('created', 'DESC')
      ->limit(50)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $prospectStorage = $this->pipelineEntityTypeManager->getStorage('famtastic_prospect');
    $rows = [];
    foreach ($exceptions as $exception) {
      $prospect = $prospectStorage->load((int) $exception['prospect_id']);
      $rows[] = [
        'category' => (string) $exception['category'],
        'severity' => ['data' => ['#markup' => $this->badge((string) $exception['severity'])]],
        'status' => ['data' => ['#markup' => $this->badge((string) $exception['status'])]],
        'business' => $prospect ? $this->linkCell($prospect->toLink($prospect->label())) : 'Missing prospect',
        'summary' => (string) $exception['summary'],
        'retry' => $this->date((int) ($exception['retry_after'] ?? 0)),
        'created' => $this->date((int) $exception['created']),
      ];
    }
    return $this->recordsPage(
      'Open Exceptions',
      'Actionable failures that still require an automated retry or operator decision.',
      ['Category', 'Severity', 'Status', 'Business', 'Summary', 'Retry After', 'Created'],
      $rows,
      'No open exceptions require attention.',
    );
  }

  /**
   * Builds a standard paginated metric records page.
   */
  private function recordsPage(string $title, string $description, array $header, array $rows, string $empty): array {
    return $this->page([
      'back' => Link::fromTextAndUrl('← Operations Dashboard', Url::fromRoute('famtastic_pipeline.operations'))->toRenderable(),
      'intro' => ['#markup' => '<p class="famtastic-ops__lede">' . Html::escape($description) . '</p>'],
      'records' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__table-scroll', 'famtastic-ops__table-scroll--wide']],
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => $empty,
          '#attributes' => ['class' => ['famtastic-ops__table', 'famtastic-ops__table--readable']],
        ],
      ],
      'pager' => ['#type' => 'pager'],
    ], $title);
  }

  /**
   * Wraps operator content in the shared page presentation.
   */
  private function page(array $content, string $title): array {
    $banner = $this->notificationAttentionBanner();
    return [
      '#title' => $title,
      '#attached' => ['library' => ['famtastic_pipeline/operations']],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops']],
      ] + ($banner === [] ? [] : ['attention_banner' => $banner]) + $content,
    ];
  }

  /**
   * Builds the notification queue banner, empty while delivery stays healthy.
   */
  private function notificationAttentionBanner(): array {
    $now = (int) \Drupal::time()->getRequestTime();
    $retrying = $this->countIn('famtastic_notification_outbox', 'status', ['retry']);
    $deadLetters = $this->countIn('famtastic_notification_outbox', 'status', ['dead_letter']);
    $oldestQueued = (int) ($this->database->select('famtastic_notification_outbox', 'n')
      ->fields('n', ['created'])
      ->condition('status', 'queued')
      ->orderBy('n.created', 'ASC')
      ->range(0, 1)
      ->execute()
      ->fetchField() ?: 0);
    $waitingMinutes = $oldestQueued > 0 ? (int) round(max(0, $now - $oldestQueued) / 60) : 0;
    if ($retrying === 0 && $deadLetters === 0 && $waitingMinutes * 60 < self::NOTIFICATION_QUEUE_ATTENTION_SECONDS) {
      return [];
    }
    $reasons = [];
    if ($deadLetters > 0) {
      $reasons[] = '<b class="famtastic-ops__attention-banner-danger">' . $deadLetters . ' dead-lettered</b>';
    }
    if ($retrying > 0) {
      $reasons[] = '<b>' . $retrying . ' retrying</b>';
    }
    if ($waitingMinutes * 60 >= self::NOTIFICATION_QUEUE_ATTENTION_SECONDS) {
      $reasons[] = '<b>oldest queued item waiting ' . $waitingMinutes . ' minutes</b>';
    }
    $link = Link::fromTextAndUrl('Open notifications →', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'notifications']))->toString();
    return ['#markup' => '<section class="famtastic-ops__attention-banner" role="status"><strong>Needs attention</strong><span>Notification delivery: ' . implode(' · ', $reasons) . '</span>' . $link . '</section>'];
  }

  /**
   * Builds dashboard metric cards, with optional record links.
   */
  private function metricCards(array $metrics): array {
    $cards = ['#type' => 'container', '#attributes' => ['class' => ['famtastic-ops__metrics']]];
    foreach ($metrics as $key => $metric) {
      $linked = is_array($metric);
      $label = $linked ? (string) $metric['label'] : (string) $key;
      $value = $linked ? (int) $metric['value'] : $metric;
      $content = [
        'value' => ['#markup' => '<strong>' . Html::escape((string) $value) . '</strong>'],
        'label' => ['#markup' => '<span>' . Html::escape($label) . '</span>'],
      ];
      if ($linked) {
        $content['action'] = ['#markup' => '<span class="famtastic-ops__metric-action">View records <span aria-hidden="true">→</span></span>'];
        $cards[Html::getClass((string) $key)] = [
          '#type' => 'link',
          '#title' => $content,
          '#url' => Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => (string) $key]),
          '#attributes' => [
            'class' => ['famtastic-ops__metric', 'famtastic-ops__metric--link'],
            'aria-label' => $this->t('View @label records (@count)', [
              '@label' => $label,
              '@count' => $value,
            ]),
          ],
        ];
        continue;
      }
      $cards[Html::getClass((string) $key)] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['famtastic-ops__metric']],
      ] + $content;
    }
    return $cards;
  }

  /**
   * Wraps a Drupal link render array for use in a table cell.
   */
  private function linkCell(Link $link): array {
    return ['data' => $link->toRenderable()];
  }

  /**
   * Links a campaign key to its operator detail page when available.
   */
  private function campaignLinkCell(string $campaignKey): array|string {
    if ($campaignKey === '') {
      return '—';
    }
    return $this->linkCell(Link::fromTextAndUrl(
      $campaignKey,
      Url::fromRoute('famtastic_pipeline.operations_campaign', ['campaign_key' => $campaignKey]),
    ));
  }

  /**
   * Resolves an internal campaign id to its operator detail link.
   */
  private function campaignIdLinkCell(int $campaignId): array|string {
    if ($campaignId <= 0) {
      return '—';
    }
    $campaignKey = (string) $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['campaign_key'])
      ->condition('id', $campaignId)
      ->execute()
      ->fetchField();
    return $this->campaignLinkCell($campaignKey);
  }

  /**
   * Formats a stored minor-unit amount for an operator table.
   */
  private function formatCurrency(int $minorAmount, string $currency): string {
    $currency = strtoupper($currency ?: 'USD');
    if (class_exists(\NumberFormatter::class)) {
      $locale = class_exists(\Locale::class) ? (\Locale::getDefault() ?: 'en_US') : 'en_US';
      $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
      $formatted = $formatter->formatCurrency($minorAmount / 100, $currency);
      if ($formatted !== FALSE) {
        return $formatted;
      }
    }
    return $currency . ' ' . number_format($minorAmount / 100, 2);
  }

  /**
   * Keeps long job errors readable in the drill-down table.
   */
  private function truncate(string $text, int $limit = 160): string {
    $text = trim($text);
    if ($text === '') {
      return '—';
    }
    return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1) . '…';
  }

  /**
   * Builds a safely escaped snapshot block.
   */
  private function snapshot(string $text, string $empty): array {
    if ($text === '') {
      return ['#markup' => '<p class="famtastic-ops__empty">' . Html::escape($empty) . '</p>'];
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => Html::escape($text),
      '#attributes' => ['class' => ['famtastic-ops__snapshot']],
    ];
  }

  /**
   * Formats a lifecycle status as a presentation badge.
   */
  private function badge(string $status): string {
    return '<span class="famtastic-ops__badge famtastic-ops__badge--' . Html::getClass($status) . '">' .
      Html::escape($status ?: 'unknown') . '</span>';
  }

  /**
   * Formats a timestamp for the operator interface.
   */
  private function date(int $timestamp): string {
    return $timestamp > 0 ? $this->dateFormatter->format($timestamp, 'short') : '—';
  }

  /**
   * Extracts a readable source from a stored source filter.
   */
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

  /**
   * Counts records matching equality conditions.
   */
  private function count(string $table, array $conditions = []): int {
    $query = $this->database->select($table, 't');
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts records whose field is in a set of values.
   */
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

  /**
   * Groups matching records into status counts.
   */
  private function groupCounts(string $table, string $groupField, array $conditions = []): array {
    $query = $this->database->select($table, 't');
    $query->addField('t', $groupField);
    $query->addExpression('COUNT(*)', 'total');
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return array_map('intval', $query->groupBy($groupField)->execute()->fetchAllKeyed());
  }

  /**
   * Groups records selected through an IN condition.
   */
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

  /**
   * Counts one lifecycle event type for a campaign.
   */
  private function eventCount(int $campaignId, string $type): int {
    return $this->count('famtastic_event', ['campaign_id' => $campaignId, 'event_type' => $type]);
  }

  /**
   * Returns prospect ids attributed to a campaign key.
   */
  private function prospectIds(string $campaignKey): array {
    return array_map('intval', array_values($this->pipelineEntityTypeManager->getStorage('famtastic_prospect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign', $campaignKey)
      ->execute()));
  }

  /**
   * Loads a proof by id, or the latest proof for a prospect.
   */
  private function loadProof(int $proofId, int $prospectId): mixed {
    $storage = $this->pipelineEntityTypeManager->getStorage('proof_campaign');
    if ($proofId > 0 && ($proof = $storage->load($proofId))) {
      return $proof;
    }
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('prospect_id', $prospectId)->sort('id', 'DESC')->range(0, 1)->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Loads the ordered variants for one proof campaign.
   */
  private function loadProofVariants(int $proofId): array {
    $storage = $this->pipelineEntityTypeManager->getStorage('proof_variant');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('campaign_id', $proofId)->sort('direction_id')->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  /**
   * Builds operator table rows for campaign build telemetry.
   */
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

  /**
   * Builds lifecycle event rows for one exact message.
   */
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
      $rows[] = [
        (string) $record['event_type'],
        (string) ($record['provider'] ?: '—'),
        $this->date((int) $record['recorded_at']),
      ];
    }
    return $rows;
  }

}
