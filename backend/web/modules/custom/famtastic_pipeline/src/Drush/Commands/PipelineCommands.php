<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drupal\famtastic_pipeline\Entity\Prospect;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the FAMtastic pipeline.
 */
class PipelineCommands extends DrushCommands {

  /**
   * Prints campaign, source, funnel, revenue, launch, and renewal metrics.
   */
  #[CLI\Command(name: 'famtastic:analytics-report', aliases: ['far'])]
  public function analyticsReport(): int {
    /** @var \Drupal\famtastic_pipeline\Service\PipelineAnalyticsService $analytics */
    $analytics = \Drupal::service('famtastic_pipeline.analytics');
    $this->io()->writeln(json_encode($analytics->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * Explicitly approves a campaign and queues its staged messages.
   */
  #[CLI\Command(name: 'famtastic:campaign-approve', aliases: ['fca'])]
  #[CLI\Argument(name: 'campaignKey', description: 'Exact campaign attribution key.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the campaign key.')]
  public function campaignApprove(string $campaignKey, array $options = ['confirm' => '']): int {
    if (!hash_equals($campaignKey, (string) $options['confirm'])) {
      $this->logger()->error('Approval requires --confirm=<exact-campaign-key>.');
      return self::EXIT_FAILURE;
    }
    $database = \Drupal::database();
    $updated = $database->update('famtastic_campaign')
      ->fields(['status' => 'approved', 'changed' => \Drupal::time()->getRequestTime()])
      ->condition('campaign_key', $campaignKey)
      ->condition('status', ['draft', 'paused'], 'IN')
      ->execute();
    if (!$updated) {
      $status = $database->select('famtastic_campaign', 'c')
        ->fields('c', ['status'])
        ->condition('campaign_key', $campaignKey)
        ->execute()
        ->fetchField();
      if ($status !== 'approved') {
        $this->logger()->error('Campaign does not exist or cannot be approved.');
        return self::EXIT_FAILURE;
      }
    }
    /** @var \Drupal\famtastic_pipeline\Service\CampaignMessageService $messages */
    $messages = \Drupal::service('famtastic_pipeline.campaign_messages');
    $count = $messages->queueApprovedCampaign($campaignKey);
    $this->logger()->success(dt('Campaign @campaign approved; queued @count staged message(s).', [
      '@campaign' => $campaignKey,
      '@count' => $count,
    ]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Backfills exact recipient/from/body/proof snapshots for a named campaign.
   */
  #[CLI\Command(name: 'famtastic:campaign-snapshot-backfill', aliases: ['fcsb'])]
  #[CLI\Argument(name: 'campaignKey', description: 'Exact campaign attribution key.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the campaign key.')]
  #[CLI\Option(name: 'postal-address', description: 'Physical postal address rendered in the sent message.')]
  public function campaignSnapshotBackfill(string $campaignKey, array $options = [
    'confirm' => '',
    'postal-address' => '',
  ]): int {
    if (!hash_equals($campaignKey, (string) $options['confirm'])) {
      $this->logger()->error('Snapshot backfill requires --confirm=<exact-campaign-key>.');
      return self::EXIT_FAILURE;
    }
    $postalAddress = trim((string) $options['postal-address']);
    if ($postalAddress === '') {
      $this->logger()->error('Snapshot backfill requires --postal-address.');
      return self::EXIT_FAILURE;
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\CampaignMessageService $messages */
      $messages = \Drupal::service('famtastic_pipeline.campaign_messages');
      $count = $messages->backfillCampaignSnapshots($campaignKey, $postalAddress);
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('Backfilled @count exact message snapshot(s) for @campaign.', [
      '@count' => $count,
      '@campaign' => $campaignKey,
    ]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Exports an offline Site Studio job for a prospect.
   */
  #[CLI\Command(name: 'famtastic:proof-local-export', aliases: ['fple'])]
  #[CLI\Argument(name: 'prospectId', description: 'Exact prospect entity id.')]
  public function proofLocalExport(int $prospectId): int {
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      $this->logger()->error('Prospect does not exist.');
      return self::EXIT_FAILURE;
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $proofs */
      $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
      $campaign = $proofs->createLocalHandoff($prospect);
      /** @var \Drupal\famtastic_pipeline\Service\StudioRequestGenerator $generator */
      $generator = \Drupal::service('famtastic_pipeline.studio_generator');
      $request = $generator->generate($prospect);
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'schema_version' => 1,
      'transport' => 'offline_ssh_bundle',
      'prospect_id' => $prospectId,
      'project_id' => (int) $request['project']->id(),
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'job_id' => (string) $campaign->get('studio_job_id')->value,
      'request_location' => (string) ($request['handoff']['location'] ?? ''),
      'required_directions' => ['a', 'b', 'c'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('Local Site Studio handoff exported.');
    return self::EXIT_SUCCESS;
  }

  /**
   * Exports a local refresh while an image-free pilot remains publicly usable.
   */
  #[CLI\Command(name: 'famtastic:proof-local-refresh-export', aliases: ['fplre'])]
  #[CLI\Argument(name: 'prospectId', description: 'Exact prospect entity id.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the current public proof campaign id.')]
  public function proofLocalRefreshExport(int $prospectId, array $options = ['confirm' => '']): int {
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      $this->logger()->error('Prospect does not exist.');
      return self::EXIT_FAILURE;
    }
    $confirmedCampaign = trim((string) $options['confirm']);
    if ($confirmedCampaign === '') {
      $this->logger()->error('Refresh export requires --confirm=<exact-current-campaign-id>.');
      return self::EXIT_FAILURE;
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $proofs */
      $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
      $campaign = $proofs->prepareLocalRefresh($prospect, $confirmedCampaign);
      /** @var \Drupal\famtastic_pipeline\Service\StudioRequestGenerator $generator */
      $generator = \Drupal::service('famtastic_pipeline.studio_generator');
      $request = $generator->generate($prospect);
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'schema_version' => 1,
      'transport' => 'offline_ssh_bundle_refresh',
      'prospect_id' => $prospectId,
      'project_id' => (int) $request['project']->id(),
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'job_id' => (string) $campaign->get('studio_job_id')->value,
      'request_location' => (string) ($request['handoff']['location'] ?? ''),
      'required_directions' => ['a', 'b', 'c'],
      'public_proof_remains_live' => TRUE,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('Local Site Studio pilot refresh handoff exported.');
    return self::EXIT_SUCCESS;
  }

  /**
   * Imports an SSH-delivered local proof payload through callback validation.
   */
  #[CLI\Command(name: 'famtastic:proof-local-import', aliases: ['fpli'])]
  #[CLI\Argument(name: 'path', description: 'Absolute private path to the callback JSON payload.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat payload campaign_id.')]
  #[CLI\Option(name: 'checksum', description: 'Expected SHA-256 of the payload file.')]
  public function proofLocalImport(string $path, array $options = [
    'confirm' => '',
    'checksum' => '',
  ]): int {
    if (!is_file($path) || !is_readable($path) || filesize($path) > 8 * 1024 * 1024) {
      $this->logger()->error('Payload is missing, unreadable, or larger than 8 MB.');
      return self::EXIT_FAILURE;
    }
    $expectedChecksum = strtolower(trim((string) $options['checksum']));
    $actualChecksum = hash_file('sha256', $path);
    if (!preg_match('/^[a-f0-9]{64}$/', $expectedChecksum) || !hash_equals($expectedChecksum, $actualChecksum)) {
      $this->logger()->error('Payload checksum does not match.');
      return self::EXIT_FAILURE;
    }
    try {
      $payload = json_decode((string) file_get_contents($path), TRUE, flags: JSON_THROW_ON_ERROR);
      $campaignId = (string) ($payload['campaign_id'] ?? '');
      if ($campaignId === '' || !hash_equals($campaignId, (string) $options['confirm'])) {
        throw new \InvalidArgumentException('Import requires --confirm=<exact-campaign-id>.');
      }
      /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $proofs */
      $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
      $result = $proofs->acceptCallback(
        (string) ($payload['event_id'] ?? ''),
        $campaignId,
        (string) ($payload['job_id'] ?? ''),
        is_array($payload['variants'] ?? NULL) ? $payload['variants'] : [],
      );
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'campaign_id' => $campaignId,
      'newly_processed' => (bool) $result['newly_processed'],
      'variant_count' => count($result['variants']),
      'payload_checksum' => $actualChecksum,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('Local Site Studio proof bundle imported.');
    return self::EXIT_SUCCESS;
  }

  /**
   * Runs a bounded batch of durable automation jobs.
   */
  #[CLI\Command(name: 'famtastic:jobs-run', aliases: ['fjr'])]
  #[CLI\Option(name: 'limit', description: 'Maximum jobs to process (1-100).')]
  #[CLI\Option(name: 'type', description: 'Optional exact job type filter.')]
  #[CLI\Option(name: 'prospect', description: 'Optional exact prospect id filter.')]
  #[CLI\Option(name: 'campaign', description: 'Optional exact campaign key filter.')]
  public function jobsRun(array $options = [
    'limit' => 25,
    'type' => '',
    'prospect' => 0,
    'campaign' => '',
  ]): int {
    if ((int) $options['prospect'] > 0 && (string) $options['campaign'] !== '') {
      $this->logger()->error('--prospect and --campaign are mutually exclusive.');
      return self::EXIT_FAILURE;
    }
    $prospectIds = NULL;
    if ((int) $options['prospect'] > 0) {
      $prospectIds = [(int) $options['prospect']];
    }
    elseif ((string) $options['campaign'] !== '') {
      $prospectIds = array_map('intval', \Drupal::entityQuery('famtastic_prospect')
        ->accessCheck(FALSE)
        ->condition('campaign', (string) $options['campaign'])
        ->execute());
      if ($prospectIds === []) {
        $this->logger()->error(dt('No prospects belong to campaign @campaign.', [
          '@campaign' => (string) $options['campaign'],
        ]));
        return self::EXIT_FAILURE;
      }
    }
    /** @var \Drupal\famtastic_pipeline\Service\AutomationWorker $worker */
    $worker = \Drupal::service('famtastic_pipeline.automation_worker');
    $results = $worker->run(
      (int) $options['limit'],
      $options['type'] !== '' ? (string) $options['type'] : NULL,
      $prospectIds,
    );
    $this->io()->writeln(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $failures = array_filter($results, fn (array $result) => $result['status'] === 'failed');
    if ($failures) {
      $this->logger()->error(dt('@count job(s) exhausted retries.', ['@count' => count($failures)]));
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('Processed @count automation job(s).', ['@count' => count($results)]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Imports, normalizes, deduplicates, suppresses, and scores a lead CSV.
   */
  #[CLI\Command(name: 'famtastic:leads-import', aliases: ['fli'])]
  #[CLI\Argument(name: 'path', description: 'Absolute or current-working-directory CSV path.')]
  #[CLI\Option(name: 'source', description: 'Lawful public or licensed source identifier.')]
  #[CLI\Option(name: 'campaign', description: 'Campaign attribution key.')]
  #[CLI\Option(name: 'dry-run', description: 'Validate and score without writing records.')]
  #[CLI\Usage(name: 'drush fli leads.csv --source=licensed-directory --campaign=az-launch-01 --dry-run', description: 'Preview a bounded lead import.')]
  public function leadsImport(string $path, array $options = [
    'source' => '',
    'campaign' => '',
    'dry-run' => FALSE,
  ]): int {
    if ($options['source'] === '' || $options['campaign'] === '') {
      $this->logger()->error('--source and --campaign are required.');
      return self::EXIT_FAILURE;
    }
    /** @var \Drupal\famtastic_pipeline\Service\LeadIngestionService $ingestion */
    $ingestion = \Drupal::service('famtastic_pipeline.lead_ingestion');
    try {
      $result = $ingestion->importCsv(
        $path,
        (string) $options['source'],
        (string) $options['campaign'],
        (bool) $options['dry-run'],
      );
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success(dt(
      'Processed @total row(s): @counts.',
      [
        '@total' => $result['total'],
        '@counts' => json_encode($result['counts']),
      ],
    ));
    return self::EXIT_SUCCESS;
  }

  /**
   * Creates a prospect from publicly discovered info and issues a secure link.
   */
  #[CLI\Command(name: 'famtastic:prospect-create', aliases: ['fpc'])]
  #[CLI\Option(name: 'business-name', description: 'Business name (required).')]
  #[CLI\Option(name: 'category', description: 'Business category.')]
  #[CLI\Option(name: 'description', description: 'Business description.')]
  #[CLI\Option(name: 'address', description: 'Address.')]
  #[CLI\Option(name: 'service-area', description: 'Service area.')]
  #[CLI\Option(name: 'phone', description: 'Public phone.')]
  #[CLI\Option(name: 'email', description: 'Public email.')]
  #[CLI\Option(name: 'website', description: 'Existing website URL.')]
  #[CLI\Option(name: 'hours', description: 'Hours.')]
  #[CLI\Option(name: 'social', description: 'Social links (JSON or newline list).')]
  #[CLI\Option(name: 'campaign', description: 'Campaign identifier.')]
  #[CLI\Option(name: 'source', description: 'Discovery source (google, directory, referral, social).')]
  #[CLI\Option(name: 'notes', description: 'Internal discovery notes (never shown to the prospect).')]
  #[CLI\Usage(name: 'drush fpc --business-name="Joe\'s Plumbing" --category="Plumber" --source=google', description: 'Create a prospect and print its secure link.')]
  public function prospectCreate(array $options = [
    'business-name' => '',
    'category' => '',
    'description' => '',
    'address' => '',
    'service-area' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'hours' => '',
    'social' => '',
    'campaign' => '',
    'source' => '',
    'notes' => '',
  ]): int {
    if (empty($options['business-name'])) {
      $this->logger()->error('--business-name is required.');
      return self::EXIT_FAILURE;
    }

    /** @var \Drupal\famtastic_pipeline\Service\TokenManager $tokenManager */
    $tokenManager = \Drupal::service('famtastic_pipeline.token_manager');
    $token = $tokenManager->generate();

    $prospect = Prospect::create([
      'business_name' => $options['business-name'],
      'business_category' => $options['category'],
      'business_description' => $options['description'],
      'address' => $options['address'],
      'service_area' => $options['service-area'],
      'public_phone' => $options['phone'],
      'public_email' => $options['email'],
      'website_url' => $options['website'],
      'hours' => $options['hours'],
      'social_links' => $options['social'],
      'campaign' => $options['campaign'],
      'source' => $options['source'],
      'discovery_notes' => $options['notes'],
      'token_hash' => $token['hash'],
      'token_expires' => $token['expires'],
      'token_revoked' => FALSE,
      'status' => 'new',
    ]);
    $prospect->save();

    $link = $tokenManager->link($token['raw']);
    $this->logger()->success(dt('Prospect #@id created.', ['@id' => $prospect->id()]));
    $this->io()->writeln('');
    $this->io()->writeln('  Prospect ID : ' . $prospect->id());
    $this->io()->writeln('  Secure link : ' . $link);
    $this->io()->writeln('  Raw token   : ' . $token['raw']);
    $this->io()->writeln('');
    $this->io()->writeln('<comment>Only the SHA-256 hash of the token is stored. Send the link once.</comment>');

    return self::EXIT_SUCCESS;
  }

  /**
   * Expires all active proof campaigns past their expiry timestamp.
   */
  #[CLI\Command(name: 'proof-campaign:expire', aliases: ['pce'])]
  #[CLI\Usage(name: 'drush proof-campaign:expire', description: 'Mark expired proof campaigns and print the count.')]
  public function proofCampaignExpire(): int {
    /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $service */
    $service = \Drupal::service('famtastic_pipeline.proof_campaign_service');
    $count = $service->expireActive();
    $this->logger()->success(dt('Expired @count proof campaign(s).', ['@count' => $count]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Generates the Site Studio request (brief + JSON) for a prospect.
   */
  #[CLI\Command(name: 'famtastic:studio-generate', aliases: ['fsg'])]
  #[CLI\Argument(name: 'prospectId', description: 'The prospect entity id.')]
  #[CLI\Usage(name: 'drush fsg 1', description: 'Generate the Site Studio request for prospect 1.')]
  public function studioGenerate(int $prospectId): int {
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      $this->logger()->error(dt('Prospect @id not found.', ['@id' => $prospectId]));
      return self::EXIT_FAILURE;
    }
    /** @var \Drupal\famtastic_pipeline\Service\StudioRequestGenerator $generator */
    $generator = \Drupal::service('famtastic_pipeline.studio_generator');
    $result = $generator->generate($prospect);
    $this->logger()->success(dt('Project #@id generated.', ['@id' => $result['project']->id()]));
    $this->io()->writeln('  Exported to : ' . ($result['handoff']['location'] ?? 'n/a'));
    return self::EXIT_SUCCESS;
  }

  /**
   * Registers an exact FAMtastic preview-to-Site-Studio build packet.
   */
  #[CLI\Command(name: 'famtastic:site-studio-packet-register', aliases: ['fsspr'])]
  #[CLI\Argument(name: 'path', description: 'Absolute path to site-studio-build-packet.json.')]
  public function siteStudioPacketRegister(string $path): int {
    if (!is_file($path)) {
      $this->logger()->error('Build packet file does not exist.');
      return self::EXIT_FAILURE;
    }
    try {
      $packet = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
      /** @var \Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService $service */
      $service = \Drupal::service('famtastic_pipeline.site_studio_build_packets');
      $result = $service->registerPacket($packet);
      $this->io()->writeln(json_encode([
        'ok' => TRUE,
        'newly_registered' => $result['newly_registered'],
        'project_id' => (int) $result['project']->id(),
        'packet_id' => $packet['packet_id'],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Imports a private Site Studio success packet through the same service.
   */
  #[CLI\Command(name: 'famtastic:site-studio-success-import', aliases: ['fsssi'])]
  #[CLI\Argument(name: 'path', description: 'Absolute private path to a Site Studio success packet.')]
  public function siteStudioSuccessImport(string $path): int {
    if (!is_file($path)) {
      $this->logger()->error('Success packet file does not exist.');
      return self::EXIT_FAILURE;
    }
    try {
      $success = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
      /** @var \Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService $service */
      $service = \Drupal::service('famtastic_pipeline.site_studio_build_packets');
      $result = $service->acceptSuccess($success);
      $this->io()->writeln(json_encode([
        'ok' => TRUE,
        'newly_processed' => $result['newly_processed'],
        'project_id' => (int) $result['project']->id(),
        'status' => 'site_studio_build_succeeded',
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

}
