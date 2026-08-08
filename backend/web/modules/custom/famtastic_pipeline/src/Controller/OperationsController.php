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
use Drupal\famtastic_pipeline\Service\GoogleAnalyticsReportingService;
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
    private readonly GoogleAnalyticsReportingService $googleAnalytics,
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
      ['Commerce', $this->count('famtastic_order', ['payment_status' => 'paid']) . ' paid orders', 'Products, orders, payment status, subscriptions, and fulfillment.', Url::fromUserInput('/admin/commerce'), 'commerce'],
      ['Support', $openSupport . ' open conversations', 'Customer requests, project questions, replies, and service issues.', Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'support']), 'support'],
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
      'campaign_heading' => ['#markup' => '<h2 id="campaigns">Campaigns</h2>'],
      'campaigns' => [
        '#type' => 'table',
        '#header' => ['Campaign', 'Status', 'Source', 'Prospects', 'Proofs', 'Sent', 'Clicks', 'Sales', 'Builds'],
        '#rows' => $rows,
        '#empty' => $this->t('No campaigns have been recorded.'),
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ],
      'pager' => ['#type' => 'pager'],
    ], 'Campaign Operations');
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
      'proofs-ready' => $this->proofMetric(),
      'emails-sent' => $this->eventMetric('email.sent', 'Emails Sent', 'Every recorded campaign send event.'),
      'clicks' => $this->eventMetric('email.clicked', 'Proof-Link Clicks', 'Every recorded proof-link click event.'),
      'paid-orders' => $this->paidOrderMetric(),
      'open-jobs' => $this->jobMetric(),
      'open-exceptions' => $this->exceptionMetric(),
      'support' => $this->supportMetric(),
      'referrals' => $this->referralMetric(),
      'services' => $this->serviceMetric(),
      default => throw new NotFoundHttpException('Operations metric not found.'),
    };
  }

  private function supportMetric(): array {
    $query = $this->database->select('famtastic_portal_thread', 't')->extend(PagerSelectExtender::class);
    $query->leftJoin('famtastic_organization', 'o', 'o.id = t.organization_id');
    $query->fields('t', ['kind', 'subject', 'status', 'created', 'changed'])->addField('o', 'name', 'organization');
    $rows = [];
    foreach ($query->orderBy('t.changed', 'DESC')->limit(50)->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
      $rows[] = [$record['organization'] ?: 'Individual', $record['subject'], ['data' => ['#markup' => $this->badge($record['kind'])]], ['data' => ['#markup' => $this->badge($record['status'])]], $this->date((int) $record['changed'])];
    }
    return $this->recordsPage('Customer Support', 'Customer-visible project, service, billing, and support conversations.', ['Customer', 'Subject', 'Area', 'Status', 'Updated'], $rows, 'No support conversations have been recorded.');
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
      $rows[] = [
        'business' => $this->linkCell($prospect->toLink($prospect->label() ?: '(no name)')),
        'status' => ['data' => ['#markup' => $this->badge((string) $prospect->get('status')->value)]],
        'campaign' => $this->campaignLinkCell((string) $prospect->get('campaign')->value),
        'category' => (string) ($prospect->get('business_category')->value ?: '—'),
        'email' => (string) ($prospect->get('public_email')->value ?: '—'),
        'created' => $this->date((int) $prospect->get('created')->value),
      ];
    }
    return $this->recordsPage(
      'Prospects',
      'Every discovered business in the pipeline, including its source campaign and current lifecycle state.',
      ['Business', 'Status', 'Campaign', 'Category', 'Public Email', 'Created'],
      $rows,
      'No prospects have been recorded.',
    );
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
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $empty,
        '#attributes' => ['class' => ['famtastic-ops__table']],
      ],
      'pager' => ['#type' => 'pager'],
    ], $title);
  }

  /**
   * Wraps operator content in the shared page presentation.
   */
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
