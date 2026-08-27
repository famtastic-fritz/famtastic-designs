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
   * Sends up to ten exact owner-approved public preview invitations.
   *
   * This is the only CLI entry point for a held public-preview invitation. It
   * never invokes the broad lifecycle runner or scans the general outbox.
   */
  #[CLI\Command(name: 'famtastic:preview-delivery-dispatch', aliases: ['fpdd'])]
  #[CLI\Option(name: 'ids', description: 'Comma-separated, exact preview-delivery numeric IDs (maximum 10).')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the normalized comma-separated IDs.')]
  #[CLI\Usage(name: 'drush fpdd --ids=41,42 --confirm=41,42', description: 'Deliver two already-held preview invitations and no other mail.')]
  public function previewDeliveryDispatch(array $options = ['ids' => '', 'confirm' => '']): int {
    try {
      $ids = $this->exactPositiveIds((string) $options['ids'], 10);
    }
    catch (\InvalidArgumentException $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $expected = implode(',', $ids);
    if (!hash_equals($expected, trim((string) $options['confirm']))) {
      $this->logger()->error('Targeted preview delivery requires --confirm=' . $expected . '.');
      return self::EXIT_FAILURE;
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService $previews */
      $previews = \Drupal::service('famtastic_pipeline.public_preview_deliveries');
      $result = $previews->dispatchApproved($ids);
      $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      if ($result['failed'] > 0 || $result['receipt_unknown'] > 0) {
        $this->logger()->error(dt('@failed targeted preview delivery attempt(s) failed and @unknown accepted-provider receipt(s) require reconciliation; no retry was automatically queued.', [
          '@failed' => $result['failed'],
          '@unknown' => $result['receipt_unknown'],
        ]));
        return self::EXIT_FAILURE;
      }
      $this->logger()->success(dt('Accepted @count exact public preview invitation(s). No general outbox messages were considered.', ['@count' => $result['sent']]));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Quarantines only queued proof jobs for one exact historical campaign.
   *
   * This is intentionally one-way: it records a reason and a ledger event,
   * then removes only matching queued `proof.generate` jobs from claimability.
   * It cannot send, rebuild, or alter an unrelated campaign.
   */
  #[CLI\Command(name: 'famtastic:campaign-proof-quarantine', aliases: ['fcpq'])]
  #[CLI\Option(name: 'campaign', description: 'Exact campaign attribution key whose queued proof jobs should be quarantined.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the campaign key.')]
  #[CLI\Option(name: 'reason', description: 'Required concise operational reason to preserve with the quarantine event.')]
  #[CLI\Usage(name: 'drush fcpq --campaign=cold-260-aug-2026 --confirm=cold-260-aug-2026 --reason="Superseded by owner-gated public preview flow"', description: 'Safely quarantine only the stale campaign proof jobs.')]
  public function campaignProofQuarantine(array $options = ['campaign' => '', 'confirm' => '', 'reason' => '']): int {
    $campaign = trim((string) $options['campaign']);
    $reason = trim((string) $options['reason']);
    if ($campaign === '' || !hash_equals($campaign, trim((string) $options['confirm']))) {
      $this->logger()->error('Quarantine requires --campaign and --confirm=<exact-campaign-key>.');
      return self::EXIT_FAILURE;
    }
    if ($reason === '' || mb_strlen($reason) > 1000) {
      $this->logger()->error('Quarantine requires a concise --reason (1-1000 characters).');
      return self::EXIT_FAILURE;
    }
    $prospectIds = array_map('intval', \Drupal::entityQuery('famtastic_prospect')
      ->accessCheck(FALSE)
      ->condition('campaign', $campaign)
      ->execute());
    if ($prospectIds === []) {
      $this->logger()->error('No prospects match the exact campaign key. Nothing changed.');
      return self::EXIT_FAILURE;
    }
    $database = \Drupal::database();
    $jobs = $database->select('famtastic_job', 'j')->fields('j')
      ->condition('job_type', 'proof.generate')
      ->condition('status', 'queued')
      // The historical campaign importer uses this exact generic job-key
      // family. Do not quarantine account-owned or paid-project proof jobs
      // that merely share a prospect ID with the campaign.
      ->condition('job_key', 'proof.generate:prospect:%', 'LIKE')
      ->condition('prospect_id', $prospectIds, 'IN')
      ->orderBy('id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $now = \Drupal::time()->getRequestTime();
    $reasonHash = hash('sha256', $reason);
    /** @var \Drupal\famtastic_pipeline\Service\OperationalLedger $ledger */
    $ledger = \Drupal::service('famtastic_pipeline.operational_ledger');
    $quarantined = [];
    $transaction = $database->startTransaction();
    try {
      foreach ($jobs as $job) {
        $result = [
          'status' => 'quarantined',
          'campaign_key' => $campaign,
          'reason' => $reason,
          'quarantined_at' => gmdate(DATE_ATOM, $now),
        ];
        $updated = $database->update('famtastic_job')->fields([
          'status' => 'quarantined',
          'locked_at' => NULL,
          'result' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
          'last_error' => 'quarantined: ' . $reason,
          'changed' => $now,
        ])->condition('id', (int) $job['id'])
          ->condition('job_type', 'proof.generate')
          ->condition('status', 'queued')
          ->condition('job_key', 'proof.generate:prospect:%', 'LIKE')
          ->execute();
        if ($updated !== 1) {
          throw new \RuntimeException('A queued proof job changed before it could be quarantined. No partial quarantine was retained.');
        }
        $ledger->recordEvent(
          'proof.generate.quarantined:' . (int) $job['id'] . ':' . $reasonHash,
          'proof.generate.quarantined',
          ['job_id' => (int) $job['id'], 'campaign_key' => $campaign, 'reason_hash' => $reasonHash],
          (int) $job['prospect_id'],
        );
        $quarantined[] = (int) $job['id'];
      }
      $ledger->recordEvent(
        'campaign.proof_jobs_quarantined:' . $campaign . ':' . $reasonHash,
        'campaign.proof_jobs_quarantined',
        ['campaign_key' => $campaign, 'job_count' => count($quarantined), 'reason_hash' => $reasonHash],
      );
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'campaign_key' => $campaign,
      'status' => 'quarantined',
      'job_ids' => $quarantined,
      'count' => count($quarantined),
      'reason_hash' => $reasonHash,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success(dt('Quarantined @count queued proof job(s) for exactly @campaign. No emails or proof generation ran.', [
      '@count' => count($quarantined),
      '@campaign' => $campaign,
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
   * Exports the current account-owned request brief without requiring payment.
   */
  #[CLI\Command(name: 'famtastic:website-request-proof-export', aliases: ['fwrpe'])]
  #[CLI\Argument(name: 'requestReference', description: 'Website request numeric id or public UUID.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the request public UUID.')]
  public function websiteRequestProofExport(string $requestReference, array $options = ['confirm' => '']): int {
    $database = \Drupal::database();
    $query = $database->select('famtastic_project_request', 'r')->fields('r');
    ctype_digit($requestReference) ? $query->condition('id', (int) $requestReference) : $query->condition('public_id', $requestReference);
    $request = $query->execute()->fetchAssoc();
    if (!$request || !hash_equals((string) $request['public_id'], trim((string) $options['confirm']))) {
      $this->logger()->error('Export requires --confirm=<exact-request-public-uuid>.');
      return self::EXIT_FAILURE;
    }
    if ($request['status'] === 'draft' || !empty($request['proof_campaign_id'])) {
      $this->logger()->error('Request must be submitted and must not already have an attached proof set.');
      return self::EXIT_FAILURE;
    }
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->load((int) $request['prospect_id']);
    if (!$prospect) {
      $this->logger()->error('Request prospect does not exist.');
      return self::EXIT_FAILURE;
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $proofs */
      $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
      $campaign = $proofs->createLocalHandoff($prospect);
      /** @var \Drupal\famtastic_pipeline\Service\CustomerPortalService $portal */
      $portal = \Drupal::service('famtastic_pipeline.customer_portal');
      $context = $portal->websiteRequestProofContext((int) $request['id']);
      $assets = $database->select('famtastic_request_asset', 'a')->fields('a')
        ->condition('website_request_id', (int) $request['id'])->condition('status', 'active')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $fileStorage = \Drupal::entityTypeManager()->getStorage('file');
      $assetManifest = [];
      foreach ($assets as $asset) {
        $file = $fileStorage->load((int) $asset['file_id']);
        $assetManifest[] = [
          'name' => (string) $asset['original_name'],
          'mime_type' => (string) $asset['mime_type'],
          'size_bytes' => (int) $asset['size_bytes'],
          'sha256' => (string) $asset['sha256'],
          'ai_use_consent' => (bool) $asset['ai_use_consent'],
          'private_uri' => $file ? (string) $file->getFileUri() : '',
        ];
      }
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'schema_version' => 2,
      'routine' => 'website_proof.generate.v1',
      'transport' => 'offline_ssh_bundle',
      'website_request_id' => (int) $request['id'],
      'website_request_public_id' => (string) $request['public_id'],
      'prospect_id' => (int) $request['prospect_id'],
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'job_id' => (string) $campaign->get('studio_job_id')->value,
      'website_discovery_v3' => $context['website_discovery_v3'],
      'reference_assets' => $assetManifest,
      'required_directions' => [
        'a' => ['name' => 'Safe'],
        'b' => ['name' => 'Wild'],
        'c' => ['name' => 'OMG'],
      ],
      'customer_delivery_authorized' => FALSE,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('Account-owned website proof handoff exported; customer delivery remains locked.');
    return self::EXIT_SUCCESS;
  }

  /**
   * Exports an optional three-direction FAMtastic showcase expansion.
   */
  #[CLI\Command(name: 'famtastic:website-request-proof-showcase-export', aliases: ['fwrpse'])]
  #[CLI\Argument(name: 'requestReference', description: 'Website request numeric id or public UUID.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the request public UUID.')]
  public function websiteRequestProofShowcaseExport(string $requestReference, array $options = ['confirm' => '']): int {
    $database = \Drupal::database();
    $query = $database->select('famtastic_project_request', 'r')->fields('r');
    ctype_digit($requestReference) ? $query->condition('id', (int) $requestReference) : $query->condition('public_id', $requestReference);
    $request = $query->execute()->fetchAssoc();
    $confirmation = trim((string) $options['confirm']);
    if (!$request || !hash_equals((string) $request['public_id'], $confirmation)) {
      $this->logger()->error('Showcase export requires --confirm=<exact-request-public-uuid>.');
      return self::EXIT_FAILURE;
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $proofs */
      $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
      $campaign = $proofs->prepareWebsiteRequestShowcase((int) $request['id'], $confirmation);
      /** @var \Drupal\famtastic_pipeline\Service\CustomerPortalService $portal */
      $portal = \Drupal::service('famtastic_pipeline.customer_portal');
      $context = $portal->websiteRequestProofContext((int) $request['id']);
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'schema_version' => 1,
      'routine' => 'website_proof.showcase.v1',
      'transport' => 'offline_ssh_bundle_showcase',
      'website_request_id' => (int) $request['id'],
      'website_request_public_id' => (string) $request['public_id'],
      'prospect_id' => (int) $request['prospect_id'],
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'job_id' => (string) $campaign->get('studio_job_id')->value,
      'website_discovery_v3' => $context['website_discovery_v3'],
      'required_directions' => [
        'd' => ['name' => 'Royal Current'],
        'e' => ['name' => 'Crownverse'],
        'f' => ['name' => 'Shay Live'],
      ],
      'resulting_direction_count' => 6,
      'customer_delivery_authorized' => FALSE,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('FAMtastic showcase handoff exported; customer delivery remains locked.');
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
    if (!is_file($path) || !is_readable($path) || filesize($path) > \Drupal\famtastic_pipeline\Service\ProofAssetContract::MAX_CALLBACK_BYTES) {
      $this->logger()->error('Payload is missing, unreadable, or larger than the signed proof asset limit.');
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
   * Imports one finalized verified-cold proof callback through its own narrow
   * lane. This command is intentionally local/private-file only: it neither
   * promotes a proof, stages an invitation, dispatches email, nor uses the
   * generic local-proof promoter.
   */
  #[CLI\Command(name: 'famtastic:verified-cold-proof-import', aliases: ['fvcpi'])]
  #[CLI\Argument(name: 'callbackPath', description: 'Absolute private path to one signed verified-cold callback JSON payload.')]
  #[CLI\Argument(name: 'buildDnaPath', description: 'Absolute private path to the matching finalized Build DNA JSON.')]
  #[CLI\Option(name: 'delivery', description: 'Exact numeric verified-cold public preview delivery ID.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the canonical public campaign ID.')]
  #[CLI\Option(name: 'callback-checksum', description: 'Exact SHA-256 of the callback payload file.')]
  #[CLI\Option(name: 'build-dna-checksum', description: 'Exact SHA-256 of the Build DNA file.')]
  #[CLI\Option(name: 'callback-signature', description: 'sha256=<HMAC of the callback file with SITE_STUDIO_CALLBACK_SECRET>.')]
  public function verifiedColdProofImport(string $callbackPath, string $buildDnaPath, array $options = [
    'delivery' => 0,
    'confirm' => '',
    'callback-checksum' => '',
    'build-dna-checksum' => '',
    'callback-signature' => '',
  ]): int {
    $deliveryId = (int) ($options['delivery'] ?? 0);
    if ($deliveryId < 1) {
      $this->logger()->error('Verified-cold import requires --delivery=<exact-numeric-delivery-id>.');
      return self::EXIT_FAILURE;
    }
    try {
      $callback = $this->privateRegularFile($callbackPath, \Drupal\famtastic_pipeline\Service\ProofAssetContract::MAX_CALLBACK_BYTES, 'Callback payload');
      $buildDna = $this->privateRegularFile($buildDnaPath, 10 * 1024 * 1024, 'Build DNA');
      $callbackChecksum = $this->verifiedFileChecksum($callback, (string) ($options['callback-checksum'] ?? ''), 'callback-checksum');
      $buildDnaChecksum = $this->verifiedFileChecksum($buildDna, (string) ($options['build-dna-checksum'] ?? ''), 'build-dna-checksum');
      $this->verifyCallbackSignature((string) file_get_contents($callback), (string) ($options['callback-signature'] ?? ''));
      $payload = json_decode((string) file_get_contents($callback), TRUE, 512, JSON_THROW_ON_ERROR);
      $dna = json_decode((string) file_get_contents($buildDna), TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($payload) || ($payload['schema'] ?? '') !== 'famtastic.verified-cold-proof-callback.v1') {
        throw new \InvalidArgumentException('Callback payload has an unsupported verified-cold schema.');
      }
      if (!is_array($dna) || ($dna['schema'] ?? '') !== 'famtastic.build-dna.v1') {
        throw new \InvalidArgumentException('Build DNA has an unsupported schema.');
      }
      $delivery = \Drupal::database()->select('famtastic_preview_delivery', 'p')->fields('p')
        ->condition('id', $deliveryId)->condition('source_lane', 'verified_cold')->range(0, 1)->execute()->fetchAssoc();
      if (!$delivery || (int) ($delivery['proof_campaign_id'] ?? 0) < 1) {
        throw new \RuntimeException('Verified-cold import requires one delivery-bound proof campaign.');
      }
      /** @var \Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService $previews */
      $previews = \Drupal::service('famtastic_pipeline.public_preview_deliveries');
      $runtime = $previews->verifiedColdCallbackContractForCampaign((int) $delivery['prospect_id'], (int) $delivery['proof_campaign_id']);
      $profile = $previews->proofProfileForCampaign((int) $delivery['prospect_id'], (int) $delivery['proof_campaign_id']);
      // The shipped receipt-backed Beauty finalizer/assembler is deliberately
      // the canonical a/b/c adapter. Profile configuration remains 1--6 at
      // ingress, but a different shape must provide a compatible asset
      // finalizer/importer instead of silently dropping directions here.
      if (!is_array($profile) || array_keys((array) ($profile['directions'] ?? [])) !== ['a', 'b', 'c']) {
        throw new \InvalidArgumentException('This verified-cold import adapter supports only the frozen a/b/c signed-media profile. Use a compatible finalizer/importer for another configured proof count.');
      }
      $run = is_array($dna['run'] ?? NULL) ? $dna['run'] : [];
      $campaignId = (string) ($payload['campaign_id'] ?? '');
      $payloadBindingHash = strtolower(trim((string) ($payload['runtime_binding_sha256'] ?? '')));
      $runBindingHash = strtolower(trim((string) ($run['binding_sha256'] ?? '')));
      if (!$runtime
        || !hash_equals($campaignId, (string) ($options['confirm'] ?? ''))
        || !hash_equals($campaignId, (string) $runtime['build_dna_run']['campaign_id'])
        || !hash_equals($buildDnaChecksum, strtolower(trim((string) ($payload['build_dna_sha256'] ?? ''))))
        || (int) ($payload['prospect_id'] ?? 0) !== (int) $delivery['prospect_id']
        || (int) ($payload['proof_campaign_id'] ?? 0) !== (int) $delivery['proof_campaign_id']
        || (int) ($payload['public_preview_delivery_id'] ?? 0) !== $deliveryId
        || !hash_equals('verified_cold', (string) ($payload['source_lane'] ?? ''))
        || !hash_equals((string) $runtime['job_id'], (string) ($payload['job_id'] ?? ''))
        || !hash_equals((string) $runtime['callback_event_id'], (string) ($payload['event_id'] ?? ''))
        || !hash_equals((string) $runtime['run_started_at'], (string) ($payload['run_started_at'] ?? ''))
        || (int) ($run['prospect_id'] ?? 0) !== (int) $delivery['prospect_id']
        || (int) ($run['proof_campaign_id'] ?? 0) !== (int) $delivery['proof_campaign_id']
        || (int) ($run['public_preview_delivery_id'] ?? 0) !== $deliveryId
        || !hash_equals($campaignId, (string) ($run['campaign_id'] ?? ''))
        || !hash_equals('verified_cold', (string) ($run['source_lane'] ?? ''))
        || !hash_equals((string) $runtime['job_id'], (string) ($run['job_id'] ?? ''))
        || !hash_equals((string) $runtime['callback_event_id'], (string) ($run['callback_event_id'] ?? ''))
        || !hash_equals((string) $runtime['run_started_at'], (string) ($run['started_at'] ?? $run['run_started_at'] ?? ''))
        || preg_match('/^[a-f0-9]{64}$/', $payloadBindingHash) !== 1
        || !hash_equals($payloadBindingHash, $runBindingHash)
      ) {
        throw new \InvalidArgumentException('Callback, Build DNA, and verified-cold delivery do not share one exact immutable runtime binding.');
      }
      // A malformed callback must not leave a Build DNA projection claiming a
      // completed proof set. Keep the immutable projection and callback
      // persistence in the same local database transaction; the callback
      // validates all variants before writing its proof entities/files.
      $transaction = \Drupal::database()->startTransaction();
      try {
        /** @var \Drupal\famtastic_pipeline\Service\BuildTelemetryService $telemetry */
        $telemetry = \Drupal::service('famtastic_pipeline.build_telemetry');
        $buildRunId = $telemetry->recordBuildDna($dna);
        /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $proofs */
        $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
        $result = $proofs->acceptCallback(
          (string) ($payload['event_id'] ?? ''),
          $campaignId,
          (string) ($payload['job_id'] ?? ''),
          is_array($payload['variants'] ?? NULL) ? $payload['variants'] : [],
        );
      }
      catch (\Throwable $error) {
        $transaction->rollBack();
        throw $error;
      }
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode([
      'delivery_id' => $deliveryId,
      'campaign_id' => $campaignId,
      'build_run_id' => $buildRunId,
      'newly_processed' => (bool) $result['newly_processed'],
      'variant_count' => count($result['variants']),
      'callback_checksum' => $callbackChecksum,
      'status' => 'proofs_imported_owner_review_required',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('Verified-cold proof imported. Build DNA and signed assets are registered; owner staging and delivery remain locked.');
    return self::EXIT_SUCCESS;
  }

  /**
   * Runs protection, automation, and notification dispatch as one observable cycle.
   */
  #[CLI\Command(name: 'famtastic:lifecycle-run', aliases: ['flr'])]
  #[CLI\Option(name: 'limit', description: 'Maximum automation jobs and notifications to process (1-100).')]
  public function lifecycleRun(array $options = ['limit' => 25]): int {
    $limit = max(1, min(100, (int) $options['limit']));
    try {
      /** @var \Drupal\famtastic_pipeline\Service\LifecycleOperationsService $operations */
      $operations = \Drupal::service('famtastic_pipeline.lifecycle_operations');
      $result = [
        'protection' => $operations->runProtection(),
        'automation' => $operations->runAutomation(min(50, $limit)),
        'notifications' => $operations->dispatchNotifications($limit),
      ];
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success('FAMtastic lifecycle cycle completed.');
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

  /**
   * Validates or ingresses a source-backed cold cohort into owner-gated proof
   * delivery. It never sends email, invokes the generic outreach lane, or
   * executes a proof provider.
   */
  #[CLI\Command(name: 'famtastic:cold-proof-ingress', aliases: ['fcpi'])]
  #[CLI\Argument(name: 'path', description: 'Absolute path to a famtastic.cold_proof_campaign_seed.v1 JSON file.')]
  #[CLI\Option(name: 'dry-run', description: 'Validate/import-score only; write nothing (default: true).')]
  #[CLI\Option(name: 'confirm', description: 'For a write, must exactly repeat cohort.cohort_key from the seed.')]
  #[CLI\Usage(name: 'drush fcpi /private/cold.json --dry-run', description: 'Validate a source-backed seed without writing or sending.')]
  #[CLI\Usage(name: 'drush fcpi /private/cold.json --dry-run=0 --confirm=atlanta-braiders-2026-08', description: 'Create public-preview proof jobs only; owner review/send remain separate.')]
  public function coldProofIngress(string $path, array $options = ['dry-run' => TRUE, 'confirm' => '']): int {
    $dryRun = filter_var($options['dry-run'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $dryRun = $dryRun !== FALSE;
    if (!$dryRun) {
      try {
        $seed = json_decode((string) file_get_contents($path), TRUE, 32, JSON_THROW_ON_ERROR);
        $key = is_array($seed) ? trim((string) ($seed['cohort']['cohort_key'] ?? '')) : '';
      }
      catch (\Throwable) {
        $key = '';
      }
      if ($key === '' || !hash_equals($key, trim((string) $options['confirm']))) {
        $this->logger()->error('Cold-proof ingress write requires --confirm=<exact cohort.cohort_key>.');
        return self::EXIT_FAILURE;
      }
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ColdProofIngressService $ingress */
      $ingress = \Drupal::service('famtastic_pipeline.cold_proof_ingress');
      $result = $ingress->importSeed($path, $dryRun);
      $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Exports exact verified-cold campaign bindings for a proof runner.
   *
   * The default is stdout-only. A file export requires explicit exact-ID
   * confirmation, writes one new private file at mode 0600, and never invokes
   * a provider, callback, mailer, owner approval, or dispatcher.
   */
  #[CLI\Command(name: 'famtastic:cold-proof-handoff-export', aliases: ['fcphe'])]
  #[CLI\Option(name: 'ids', description: 'Comma-separated exact verified-cold preview-delivery IDs (1-10).')]
  #[CLI\Option(name: 'output', description: 'Optional new absolute private file path for the JSON handoff bundle.')]
  #[CLI\Option(name: 'confirm', description: 'Required only with --output; exactly repeat normalized IDs.')]
  #[CLI\Usage(name: 'drush fcphe --ids=41', description: 'Print a read-only verified-cold runner handoff bundle.')]
  #[CLI\Usage(name: 'drush fcphe --ids=41,42 --output=/private/famtastic/cold-handoff.json --confirm=41,42', description: 'Write one explicit private handoff bundle; no provider or mail action occurs.')]
  public function coldProofHandoffExport(array $options = ['ids' => '', 'output' => '', 'confirm' => '']): int {
    try {
      $ids = $this->exactPositiveIds((string) $options['ids'], 10);
    }
    catch (\InvalidArgumentException $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
    $output = trim((string) $options['output']);
    if ($output !== '') {
      $expected = implode(',', $ids);
      if (!hash_equals($expected, trim((string) $options['confirm']))) {
        $this->logger()->error('Private handoff export requires --confirm=' . $expected . '.');
        return self::EXIT_FAILURE;
      }
      $privateRoot = \Drupal::service('file_system')->realpath('private://');
      $rawParent = rtrim(dirname($output), DIRECTORY_SEPARATOR);
      $parent = realpath($rawParent);
      $privateRoot = is_string($privateRoot) ? rtrim($privateRoot, DIRECTORY_SEPARATOR) : '';
      $parent = is_string($parent) ? rtrim($parent, DIRECTORY_SEPARATOR) : '';
      $target = $parent === '' ? '' : $parent . DIRECTORY_SEPARATOR . basename($output);
      $insidePrivate = $privateRoot !== '' && ($parent === $privateRoot || str_starts_with($parent, $privateRoot . DIRECTORY_SEPARATOR));
      // A handoff can include commercially sensitive source context. Never
      // follow an output symlink or write outside Drupal's configured private
      // filesystem, even when the caller supplied a valid absolute path.
      if (
        !str_starts_with($output, '/')
        || $parent === ''
        || $rawParent !== $parent
        || !$insidePrivate
        || !is_writable($parent)
        || file_exists($output)
        || is_link($output)
        || $target === ''
        || str_contains($output, '..' . DIRECTORY_SEPARATOR)
      ) {
        $this->logger()->error('--output must be a new non-symlink regular file below Drupal\'s configured writable private directory.');
        return self::EXIT_FAILURE;
      }
    }
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ColdProofBuildHandoffService $handoffs */
      $handoffs = \Drupal::service('famtastic_pipeline.cold_proof_build_handoff');
      $bundle = $handoffs->export($ids);
      $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
      if ($output !== '') {
        if (file_put_contents($output, $json, LOCK_EX) === FALSE) {
          throw new \RuntimeException('Could not write the requested cold-proof handoff file.');
        }
        @chmod($output, 0600);
      }
      $this->io()->writeln($json);
      if ($output !== '') {
        $this->logger()->success('Wrote a private read-only cold-proof handoff bundle. No provider, callback, mail, or dispatch action occurred.');
      }
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Checks only due, owner-approved verified-cold deliveries.
   *
   * The command defaults to dry-run. Its explicit execute token delegates
   * exact due IDs to the existing public-preview dispatcher, never lifecycle.
   */
  #[CLI\Command(name: 'famtastic:cold-proof-scheduled-release', aliases: ['fcpsr'])]
  #[CLI\Option(name: 'limit', description: 'Maximum due approved deliveries to inspect/release (1-10).')]
  #[CLI\Option(name: 'execute', description: 'Must equal scheduled-owner-approved-cold-preview to dispatch exact due held IDs.')]
  #[CLI\Usage(name: 'drush fcpsr --limit=10', description: 'List due owner-approved cold preview deliveries only.')]
  #[CLI\Usage(name: 'drush fcpsr --limit=10 --execute=scheduled-owner-approved-cold-preview', description: 'Targeted scheduled release after owner approval; never general lifecycle mail.')]
  public function coldProofScheduledRelease(array $options = ['limit' => 10, 'execute' => '']): int {
    $limit = (int) $options['limit'];
    if ($limit < 1 || $limit > 10) {
      $this->logger()->error('--limit must be between 1 and 10.');
      return self::EXIT_FAILURE;
    }
    $execute = hash_equals('scheduled-owner-approved-cold-preview', trim((string) $options['execute']));
    try {
      /** @var \Drupal\famtastic_pipeline\Service\ColdProofScheduledReleaseService $release */
      $release = \Drupal::service('famtastic_pipeline.cold_proof_scheduled_release');
      $result = $release->releaseDue($limit, !$execute);
      $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /** Resolves one non-symlink regular file below Drupal's private filesystem. */
  private function privateRegularFile(string $path, int $maximumBytes, string $label): string {
    $path = trim($path);
    $privateRoot = \Drupal::service('file_system')->realpath('private://');
    $root = is_string($privateRoot) ? rtrim($privateRoot, DIRECTORY_SEPARATOR) : '';
    $real = $path !== '' && str_starts_with($path, '/') && !is_link($path) ? realpath($path) : FALSE;
    if (!is_string($real) || $root === '' || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real) || is_link($real)) {
      throw new \InvalidArgumentException($label . ' must be a non-symlink regular file under Drupal\'s configured private filesystem.');
    }
    $bytes = filesize($real);
    if ($bytes === FALSE || $bytes < 1 || $bytes > $maximumBytes) {
      throw new \InvalidArgumentException($label . ' is empty or exceeds its bounded import limit.');
    }
    return $real;
  }

  /** Verifies an operator-provided SHA without printing the private file path. */
  private function verifiedFileChecksum(string $path, string $expected, string $optionName): string {
    $expected = strtolower(trim($expected));
    $actual = hash_file('sha256', $path);
    if ($actual === FALSE || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1 || !hash_equals($expected, $actual)) {
      throw new \InvalidArgumentException('--' . $optionName . ' must exactly match its private file SHA-256.');
    }
    return $actual;
  }

  /** Requires the same HMAC boundary used by the Site Studio callback route. */
  private function verifyCallbackSignature(string $body, string $provided): void {
    $secret = getenv('SITE_STUDIO_CALLBACK_SECRET') ?: \Drupal\Core\Site\Settings::get('site_studio_callback_secret');
    if (!is_string($secret) || $secret === '') {
      throw new \RuntimeException('Verified-cold import requires the configured Site Studio callback secret.');
    }
    $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
    if (!hash_equals($expected, trim($provided))) {
      throw new \InvalidArgumentException('Verified-cold callback signature is invalid.');
    }
  }

  /** Parses exact, unique numeric IDs for an operator-confirmed bounded action. */
  private function exactPositiveIds(string $raw, int $maximum): array {
    $tokens = array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== '');
    if ($tokens === [] || count($tokens) > $maximum) {
      throw new \InvalidArgumentException('Provide between one and ' . $maximum . ' comma-separated delivery IDs.');
    }
    $ids = [];
    foreach ($tokens as $token) {
      if (!ctype_digit($token) || (int) $token < 1) {
        throw new \InvalidArgumentException('Each preview-delivery ID must be a positive integer.');
      }
      $id = (int) $token;
      if (isset($ids[$id])) {
        throw new \InvalidArgumentException('Preview-delivery IDs must be unique.');
      }
      $ids[$id] = $id;
    }
    $ids = array_values($ids);
    sort($ids, SORT_NUMERIC);
    return $ids;
  }

}
