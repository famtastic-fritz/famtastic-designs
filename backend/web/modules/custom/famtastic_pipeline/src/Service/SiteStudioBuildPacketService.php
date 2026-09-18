<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Project;

/**
 * Registers outbound build packets and consumes signed Site Studio results.
 */
final class SiteStudioBuildPacketService {

  private const BUILD_SCHEMA = 'famtastic.site-studio.build-packet.v1';
  private const SUCCESS_SCHEMA = 'site-studio.build-success.v1';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Stores the exact outbound packet on its owned project idempotently.
   */
  public function resolveSelectedRecords(array $row, array $dna, array $intent, array $artifacts): ?array {
    $installation = $this->configFactory->get('famtastic_pipeline.settings')->get('selected_staging');
    if (!is_array($installation)) return NULL;
    $project = $this->loadProject((string) $row['project_id']);
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE);
    return SelectedRecordResolver::resolve($row, $dna, $intent, $artifacts, $installation, dirname(\Drupal::root()), $studio['selected_source_mapping'] ?? NULL);
  }

  /** Worker-only original bytes: no preview rewriting or arbitrary path input. */
  public function readSelectedArtifact(string $requestId, int $revision, string $hash, string $authorization, string $secret, int $now): string {
    if ($secret === '' || !preg_match('/^FAMtastic-Artifact ([0-9]{10}):([a-f0-9]{64})$/', $authorization, $m) || abs($now - (int) $m[1]) > 300
      || !preg_match('/^[a-f0-9]{64}$/', $hash) || !hash_equals(hash_hmac('sha256', "selected-artifact.v1\n$requestId\n$revision\n$hash\n" . $m[1], $secret), $m[2])) throw new \InvalidArgumentException('artifact_authorization_invalid');
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $requestId)->execute()->fetchAssoc();
    if (!$row || $row['public_id'] !== $requestId) throw new \InvalidArgumentException('artifact_request_missing');
    $project = $this->loadProject((string) $row['project_id']);
    $studio = json_decode((string) $project->get('studio_json')->value, TRUE, 512, JSON_THROW_ON_ERROR);
    $packet = $studio['selected_dispatch_packet'] ?? [];
    $this->assertActiveSelectedPacket($packet);
    if ((string) ($packet['project_id'] ?? '') !== (string) $row['project_id'] || (string) ($packet['continuation']['customer']['id'] ?? '') !== (string) $row['customer_id'] || ($packet['continuation']['selection_revision'] ?? 0) !== $revision) throw new \InvalidArgumentException('artifact_selection_changed');
    $matches = array_values(array_filter($packet['artifacts'] ?? [], static fn(array $a): bool => $a['sha256'] === $hash));
    if (count($matches) !== 1) throw new \InvalidArgumentException('artifact_not_declared');
    $a = $matches[0]; $root = realpath(\Drupal::root() . '/proofs');
    $path = realpath(dirname(\Drupal::root()) . '/' . $a['path']);
    if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) throw new \InvalidArgumentException('artifact_path_invalid');
    $bytes = file_get_contents($path);
    if (strlen($bytes) !== $a['bytes'] || hash('sha256', $bytes) !== $hash) throw new \InvalidArgumentException('artifact_bytes_changed');
    return $bytes;
  }

  public function registerSourceExport(array $envelope): array {
    $transaction = $this->database->startTransaction();
    try {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', (string) ($envelope['request_id'] ?? ''))->forUpdate()->execute()->fetchAssoc();
    if (!$row || (string) $row['project_id'] !== (string) ($envelope['project_id'] ?? '') || (string) $row['customer_id'] !== (string) ($envelope['customer_id'] ?? '') || (string) $row['public_id'] !== (string) ($envelope['request_id'] ?? '')) throw new \InvalidArgumentException('selected_continuation_source_mapping_identity_mismatch');
    $project = $this->loadProject((string) ($envelope['project_id'] ?? ''));
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE) ?: [];
    $authority = $studio['selected_source_mapping'] ?? $studio['selected_source_authority'] ?? [];
    if ((string) ($authority['project_id'] ?? '') !== (string) $project->id() || (string) ($authority['customer_id'] ?? '') !== (string) ($envelope['customer_id'] ?? '') || ($authority['request_id'] ?? '') !== ($envelope['request_id'] ?? '')) throw new \InvalidArgumentException('selected_continuation_source_mapping_identity_mismatch');
    $export = $envelope['source_export'] ?? [];
    SelectedFinalizedSource::validate($export, $authority);
    $prior = $studio['next_source_export'] ?? NULL;
    if (isset($studio['selected_source_mapping'])) {
      $studio['selected_source_mapping']['handoff_initiator'] = 'studio';
      $project->set('studio_json', json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
    }
    if ($prior === $export) return ['newly_processed' => FALSE];
    if ($prior !== NULL) $studio['next_source_export_history'][] = $prior;
    $studio['next_source_export'] = $export;
    $project->set('studio_json', json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
    return ['newly_processed' => TRUE];
    } catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  public function registerPacket(array $packet): array {
    $this->validatePacket($packet);
    $transaction = $this->database->startTransaction();
    try {
      $selectionRequest = NULL;
      if (isset($packet['continuation'])) {
        $selectionRequest = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', (int) ($packet['continuation']['website_request_id'] ?? 0))->forUpdate()->execute()->fetchAssoc();
        if (!$selectionRequest || (string) $selectionRequest['customer_id'] !== (string) ($packet['continuation']['customer']['id'] ?? '') || (string) $selectionRequest['project_id'] !== (string) $packet['project_id'] || (string) $selectionRequest['public_id'] !== (string) $packet['request_id'] || !empty($selectionRequest['commerce_order_id'])) {
          throw new \InvalidArgumentException('Selected build packet must match the unpaid account-owned project request.');
        }
      }
      $project = $this->loadProject((string) $packet['project_id']);
      $current = $this->projectPacket($project);
      if ($current !== NULL) {
        if (json_encode($current, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) === json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) {
          return ['newly_registered' => FALSE, 'project' => $project];
        }
        $state = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE) ?: [];
        $intent = $state['selected_source_intent'] ?? [];
        $boundIntent = ($intent['request_id'] ?? '') === $packet['request_id'] && ($intent['project_id'] ?? '') === $packet['project_id']
          && ($intent['customer_id'] ?? '') === ($packet['continuation']['customer']['id'] ?? '')
          && ($intent['selection']['direction_id'] ?? '') === substr($packet['selected_direction_ids'][0], strlen('direction-'));
        SelectedStagingContinuation::assertSuccessor($current, $packet, $boundIntent ? (int) $intent['selection']['revision'] : NULL);
      }
      $request = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE);
      $request = is_array($request) ? $request : [];
      $existingRequestId = $this->requestIdFrom($request);
      if ($existingRequestId !== NULL && !hash_equals($existingRequestId, (string) $packet['request_id'])) {
        throw new \InvalidArgumentException('Build packet request does not match the project request.');
      }
      if ($current !== NULL) {
        $request['site_studio_build_packet_history'][] = $current;
      }
      if ($selectionRequest !== NULL) {
        if (!empty($selectionRequest['staging_receipt_json'])) {
          $request['site_studio_staging_history'][] = [
            'receipt_json' => $selectionRequest['staging_receipt_json'],
            'receipt_hash' => $selectionRequest['staging_receipt_hash'],
            'review_status' => $selectionRequest['staging_review_status'],
            'reviewed_at' => $selectionRequest['staging_reviewed_at'],
          ];
        }
        $this->database->update('famtastic_project_request')->fields([
          'staging_status' => 'queued', 'staging_review_status' => 'not_started',
          'staging_reviewed_at' => NULL, 'staging_receipt_json' => NULL,
          'staging_receipt_hash' => '', 'staging_locked_at' => NULL, 'staging_deployed_at' => NULL,
        ])->condition('id', (int) $selectionRequest['id'])->execute();
      }
      if ($selectionRequest !== NULL) $this->supersedeReviewNotifications((int) $selectionRequest['id']);
      unset($request['selected_staging_exception']);
      $request['site_studio_build_packet'] = $packet;
      $request['selected_dispatch_packet'] = $packet;
      $project
        ->set('studio_json', json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        ->set('delivery_status', 'submitted')
        ->save();
      $this->ledger->recordEvent(
        'site-studio.packet:' . $packet['packet_id'],
        'site_studio.build_packet_registered',
        [
          'packet_id' => $packet['packet_id'],
          'idempotency_key' => $packet['idempotency_key'],
          'request_id' => $packet['request_id'],
          'selected_direction_ids' => $packet['selected_direction_ids'],
          'build_class' => $packet['build_class'],
        ],
        projectId: (int) $project->id(),
      );
      unset($transaction);
      return ['newly_registered' => TRUE, 'project' => $project];
    } catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function registerPlanningPacket(array $packet): void {
    $transaction = $this->database->startTransaction();
    try {
    $intent = $packet['intent'] ?? [];
    if (SelectedPlanningPacket::create($intent, $packet['dispatch_issue'] ?? NULL) !== $packet) throw new \InvalidArgumentException('Selected planning packet is not canonical.');
    $project = $this->loadProject((string) $packet['project_id']);
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE) ?: [];
    if (($studio['selected_source_intent'] ?? NULL) !== $intent) throw new \InvalidArgumentException('selected_staging_packet_superseded');
    if (($studio['selected_dispatch_packet'] ?? NULL) === $packet) return;
    if (($studio['selected_dispatch_packet']['packet_id'] ?? NULL) === $packet['packet_id']) throw new \InvalidArgumentException('Planning packet is immutable for a revision.');
    $this->recordSelectedException((int) $intent['website_request_id'], $intent['selection']['direction_id'], 'selected_continuation_planning: remaining work and authority issues are being planned.');
    $studio = json_decode((string) $project->get('studio_json')->value, TRUE);
    if (isset($studio['selected_dispatch_packet'])) $studio['selected_dispatch_history'][] = $studio['selected_dispatch_packet'];
    if (isset($studio['selected_source_plan'])) $studio['selected_source_plan_history'][] = $studio['selected_source_plan'];
    unset($studio['selected_source_plan']);
    $studio['selected_dispatch_packet'] = $packet;
    $project->set('studio_json', json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
    $this->database->update('famtastic_project_request')->fields(['staging_status' => 'planning'])->condition('id', (int) $intent['website_request_id'])->execute();
    } catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Planning is a private operational result, never a review-ready receipt. */
  public function acceptPlanningResult(array $result): array {
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', (int) ($result['website_request_id'] ?? 0))->forUpdate()->execute()->fetchAssoc();
      if (!$row || !empty($row['commerce_order_id']) || (string) $row['id'] !== (string) ($result['website_request_id'] ?? '') || (string) $row['project_id'] !== (string) ($result['project_id'] ?? '') || (string) $row['customer_id'] !== (string) ($result['customer_id'] ?? '') || $row['public_id'] !== ($result['request_id'] ?? '')) throw new \InvalidArgumentException('Planning result identity mismatch.');
      $project = $this->loadProject((string) $result['project_id']);
      $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE) ?: [];
      $packet = $studio['selected_dispatch_packet'] ?? [];
      $this->assertActiveSelectedPacket($packet);
      if (($packet['schema'] ?? '') !== 'famtastic.site-studio.planning-packet.v1') throw new \InvalidArgumentException('Planning result does not match active work.');
      foreach (['packet_id', 'idempotency_key', 'request_id', 'project_id', 'artifact_manifest_sha256'] as $field) {
        if (($result[$field] ?? NULL) !== ($packet[$field] ?? NULL)) throw new \InvalidArgumentException('Planning result binding mismatch: ' . $field);
      }
      $intent = $packet['intent'];
      if (($result['selected_direction_id'] ?? '') !== $packet['selected_direction_ids'][0] || ($result['selected_artifact_sha256'] ?? '') !== $packet['selected_artifacts'][0]['source_artifact_sha256']) throw new \InvalidArgumentException('Planning selected source mismatch.');
      $intentJson = $packet['intent_payload_json'] ?? '';
      $intentHash = hash('sha256', $intentJson);
      if (($packet['intent_digest_strategy'] ?? '') !== 'sha256-json-utf8-bytes.v1' || $intentHash !== ($packet['intent_sha256'] ?? '') || json_decode($intentJson, TRUE, 512, JSON_THROW_ON_ERROR) !== $intent) throw new \InvalidArgumentException('Planning intent wire changed.');
      if (($result['schema'] ?? '') !== 'famtastic.site-studio.planning-result.v1' || !in_array($result['status'] ?? '', ['planning_complete', 'planning_failed'], TRUE)
        || ($result['intent_id'] ?? '') !== $intent['intent_id'] || ($result['intent_sha256'] ?? '') !== $intentHash
        || ($result['selection_revision'] ?? 0) !== $intent['selection']['revision'] || empty($result['event_id'])
        || ($result['ready'] ?? TRUE) !== FALSE || ($result['customer_accepted'] ?? TRUE) !== FALSE || ($result['checkout_eligible'] ?? TRUE) !== FALSE || ($result['final_launch'] ?? TRUE) !== FALSE) throw new \InvalidArgumentException('Planning result cannot establish readiness.');
      foreach (['staging_url', 'artifact_sha256', 'qa', 'hosting_verified'] as $field) if (isset($result[$field])) throw new \InvalidArgumentException('Planning result cannot contain staging evidence.');
      if ($result['status'] === 'planning_complete' && (($result['plan']['schema'] ?? '') !== 'famtastic.selected-source-plan.v1' || ($result['plan']['operation'] ?? '') !== 'continue_build' || ($result['plan']['source_artifacts'] ?? NULL) !== $intent['source']['artifacts'] || ($result['plan']['requested_scope'] ?? NULL) !== $intent['scope'] || ($result['plan']['intent_id'] ?? '') !== $intent['intent_id'] || ($result['plan']['ready'] ?? TRUE) !== FALSE || ($result['plan']['executable'] ?? TRUE) !== FALSE || !is_array($result['plan']['issues'] ?? NULL))) throw new \InvalidArgumentException('Planning result is not a remaining-work plan.');
      $prior = $studio['selected_source_plan'] ?? NULL;
      if ($prior !== NULL) {
        if ($prior !== $result) throw new \InvalidArgumentException('Planning result changed for the same revision.');
        return ['newly_processed' => FALSE, 'ready' => FALSE];
      }
      $studio['selected_source_plan'] = $result;
      $project->set('studio_json', json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
      $this->database->update('famtastic_project_request')->fields(['staging_status' => $result['status'] === 'planning_complete' ? 'planning_blocked' : 'planning_failed'])->condition('id', (int) $intent['website_request_id'])->execute();
      $this->ledger->recordEvent('site-studio.plan:' . $result['event_id'], 'site_studio.selected_source_planned', ['intent_id' => $intent['intent_id'], 'status' => $result['status']], projectId: (int) $project->id());
      return ['newly_processed' => TRUE, 'ready' => FALSE];
    } catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Preserves selected intent and invalidates readiness when evidence is incomplete. */
  public function recordSelectedException(int $requestId, string $direction, string $detail, ?string $notes = NULL): void {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->forUpdate()->execute()->fetchAssoc();
    if (!$row || !empty($row['commerce_order_id']) || empty($row['project_id'])) throw new \InvalidArgumentException('Selected exception requires the existing unpaid project binding.');
    $project = $this->loadProject((string) $row['project_id']);
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE) ?: [];
    if (!empty($row['staging_receipt_json'])) $studio['site_studio_staging_history'][] = ['receipt_json' => $row['staging_receipt_json'], 'receipt_hash' => $row['staging_receipt_hash'], 'review_status' => $row['staging_review_status'], 'reviewed_at' => $row['staging_reviewed_at'] ?? NULL];
    $exception = ['code' => explode(':', $detail, 2)[0], 'detail' => $detail, 'website_request_id' => $requestId, 'selected_direction' => $direction, 'requested_changes' => $notes, 'created_at' => gmdate(DATE_ATOM, $this->time->getRequestTime())];
    $studio['selected_staging_exception'] = $exception;
    $project->set('studio_json', json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
    $this->database->update('famtastic_project_request')->fields(['staging_status' => 'failed', 'staging_review_status' => 'not_started', 'staging_reviewed_at' => NULL, 'staging_receipt_hash' => '', 'staging_receipt_json' => NULL, 'changed' => $this->time->getRequestTime()])->condition('id', $requestId)->execute();
    $this->supersedeReviewNotifications($requestId);
    $this->ledger->recordEvent('site-studio.selected-exception:' . $requestId . ':' . hash('sha256', json_encode($exception)), 'site_studio.selected_build_exception', $exception, projectId: (int) $project->id());
  }

  private function supersedeReviewNotifications(int $requestId): void {
    $this->database->update('famtastic_notification_outbox')->fields(['status' => 'superseded', 'changed' => $this->time->getRequestTime()])
      ->condition('notification_key', 'website-request:' . $requestId . ':staging-review-ready:%', 'LIKE')
      ->condition('status', ['queued', 'retry'], 'IN')->execute();
  }

  /** Prevents old queued selections from being dispatched after a newer revision. */
  public function assertActiveSelectedPacket(array $packet): void {
    $project = $this->loadProject((string) ($packet['project_id'] ?? ''));
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE) ?: [];
    if (($packet['schema'] ?? '') === 'famtastic.site-studio.planning-packet.v1') {
      if (($studio['selected_dispatch_packet'] ?? NULL) !== $packet || ($studio['selected_source_intent'] ?? NULL) !== ($packet['intent'] ?? NULL)) throw new \InvalidArgumentException('selected_staging_packet_superseded');
      return;
    }
    if (isset($studio['selected_dispatch_packet']) && $studio['selected_dispatch_packet'] !== $packet) throw new \InvalidArgumentException('selected_staging_packet_superseded');
    if (!empty($studio['selected_staging_exception'])) throw new \InvalidArgumentException('selected_staging_evidence_pending');
    $current = $this->projectPacket($project);
    if ($current === NULL || json_encode($current, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !== json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) {
      throw new \InvalidArgumentException('selected_staging_packet_superseded: only the current registered revision can dispatch.');
    }
  }

  /**
   * Validates a success packet and advances only the matching owned project.
   */
  public function acceptSuccess(array $success): array {
    $this->validateSuccess($success);
    $project = $this->loadProject((string) $success['project_id']);
    $packet = $this->projectPacket($project);
    if ($packet === NULL) {
      throw new \InvalidArgumentException('Project has no registered Site Studio build packet.');
    }
    foreach (['packet_id', 'idempotency_key', 'request_id', 'project_id'] as $field) {
      if (!hash_equals((string) $packet[$field], (string) $success[$field])) {
        throw new \InvalidArgumentException(sprintf('Site Studio success %s does not match the registered packet.', $field));
      }
    }
    $expectedDirections = array_values($packet['selected_direction_ids']);
    $returnedDirections = array_map(static fn (array $artifact): string => (string) $artifact['direction_id'], $success['artifacts']);
    sort($expectedDirections);
    sort($returnedDirections);
    if ($expectedDirections !== $returnedDirections) {
      throw new \InvalidArgumentException('Returned Site Studio artifacts do not match the selected directions.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $isNew = $this->ledger->recordEvent(
      'site-studio.success:' . $success['event_id'],
      'site_studio.build_succeeded',
      [
        'packet_id' => $success['packet_id'],
        'build_id' => $success['build_id'],
        'artifacts' => $success['artifacts'],
        'stage_ledger' => $success['stage_ledger'],
      ],
      projectId: (int) $project->id(),
      provider: 'site_studio',
      providerEventId: (string) $success['event_id'],
      );
      if (!$isNew) {
        unset($transaction);
        return ['newly_processed' => FALSE, 'project' => $project];
      }

      $firstUri = (string) ($success['artifacts'][0]['uri'] ?? '');
      $project
        ->set('studio_job_id', (string) $success['build_id'])
        ->set('delivery_status', 'proof_delivered')
        ->set('artifact_checksum', hash('sha256', json_encode($success['artifacts'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
      if (preg_match('#^https://#', $firstUri)) {
        $project->set('proof_url', $firstUri);
      }
      $project->save();

      $organizationId = $this->organizationForProject((int) $project->id());
      if ($organizationId !== NULL) {
        $this->database->insert('famtastic_portal_activity')->fields([
          'organization_id' => $organizationId,
          'event_type' => 'site_studio_build_ready',
          'summary' => 'Your selected website direction has completed the Site Studio build stage.',
          'metadata' => json_encode(['project_id' => (int) $project->id(), 'build_id' => $success['build_id']], JSON_THROW_ON_ERROR),
          'created' => $this->time->getRequestTime(),
        ])->execute();
        $email = $this->organizationEmail($organizationId);
        if ($email !== NULL) {
          $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
          $url = $base . '/portal/?section=projects&project=' . $project->uuid();
          $now = $this->time->getRequestTime();
          $key = 'site-studio-build-ready:' . $success['event_id'];
          $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
            'notification_key' => $key,
            'category' => 'project_build_ready',
            'recipient' => mb_strtolower($email),
            'subject' => 'Your FAMtastic website build is ready for review',
            'body' => "Your selected website direction has completed the Site Studio build stage. Review it in your project workspace:\n" . $url,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => 5,
            'available_at' => $now,
            'created' => $now,
            'changed' => $now,
          ])->execute();
        }
      }
      unset($transaction);
      return ['newly_processed' => TRUE, 'project' => $project];
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Validates the immutable outbound packet contract.
   */
  private function validatePacket(array $packet): void {
    if (($packet['schema'] ?? '') !== self::BUILD_SCHEMA) {
      throw new \InvalidArgumentException('Unsupported Site Studio build packet schema.');
    }
    foreach (['packet_id', 'idempotency_key', 'request_id', 'project_id', 'build_class'] as $field) {
      if (trim((string) ($packet[$field] ?? '')) === '') {
        throw new \InvalidArgumentException(sprintf('Build packet %s is required.', $field));
      }
    }
    $directions = $packet['selected_direction_ids'] ?? [];
    if (!is_array($directions) || count($directions) < 1 || count($directions) > 2 || count($directions) !== count(array_unique($directions))) {
      throw new \InvalidArgumentException('Build packet must select one or two unique directions.');
    }
    foreach ($directions as $direction) {
      if (!preg_match('/^direction-[a-f]$/', (string) $direction)) {
        throw new \InvalidArgumentException('Build packet contains an invalid direction.');
      }
    }
    if (!is_array($packet['artifacts'] ?? NULL) || !$packet['artifacts']) {
      throw new \InvalidArgumentException('Build packet artifact manifest is required.');
    }
    foreach (['artifact_manifest_sha256', 'selected_artifacts'] as $field) {
      if (empty($packet[$field])) {
        throw new \InvalidArgumentException(sprintf('Build packet %s is required.', $field));
      }
    }
    if (!preg_match('/^[a-f0-9]{64}$/', (string) $packet['artifact_manifest_sha256'])) {
      throw new \InvalidArgumentException('Build packet artifact_manifest_sha256 must be a SHA-256 digest.');
    }
    $paths = [];
    foreach ($packet['artifacts'] as $artifact) {
      if (!is_array($artifact)
        || !in_array((string) ($artifact['role'] ?? ''), ['source_material', 'selected_preview', 'render_evidence'], TRUE)
        || !is_string($artifact['path'] ?? NULL)
        || trim((string) $artifact['path']) === ''
        || str_starts_with((string) $artifact['path'], '/')
        || str_contains((string) $artifact['path'], '..')
        || !preg_match('/^[a-f0-9]{64}$/', (string) ($artifact['sha256'] ?? ''))
        || !is_int($artifact['bytes'] ?? NULL)
        || $artifact['bytes'] < 0
        || isset($paths[(string) $artifact['path']])) {
        throw new \InvalidArgumentException('Build packet contains an invalid immutable artifact record.');
      }
      $paths[(string) $artifact['path']] = TRUE;
    }
    if (!hash_equals((string) $packet['artifact_manifest_sha256'], self::artifactManifestDigest($packet['artifacts']))) {
      throw new \InvalidArgumentException('Build packet artifact manifest digest does not match its file records.');
    }
    if (!is_array($packet['selected_artifacts']) || count($packet['selected_artifacts']) !== count($directions)) {
      throw new \InvalidArgumentException('Build packet must bind one source artifact to each selected direction.');
    }
    $boundDirections = [];
    foreach ($packet['selected_artifacts'] as $selected) {
      if (!is_array($selected)
        || !in_array((string) ($selected['direction_id'] ?? ''), $directions, TRUE)
        || isset($boundDirections[(string) ($selected['direction_id'] ?? '')])
        || !isset($paths[(string) ($selected['source_artifact_path'] ?? '')])
        || !preg_match('/^[a-f0-9]{64}$/', (string) ($selected['source_artifact_sha256'] ?? ''))
        || !is_int($selected['source_artifact_bytes'] ?? NULL)) {
        throw new \InvalidArgumentException('Build packet selected artifact binding is invalid.');
      }
      $matches = array_values(array_filter($packet['artifacts'], static fn (array $artifact): bool =>
        (string) $artifact['role'] === 'selected_preview'
        && (string) $artifact['path'] === (string) $selected['source_artifact_path']
        && (string) $artifact['sha256'] === (string) $selected['source_artifact_sha256']
        && (int) $artifact['bytes'] === (int) $selected['source_artifact_bytes']
      ));
      if (count($matches) !== 1) {
        throw new \InvalidArgumentException('Build packet selected artifact does not match its immutable manifest.');
      }
      $boundDirections[(string) $selected['direction_id']] = TRUE;
    }
  }

  /**
   * Canonical digest for the complete source-file manifest.
   *
   * Key sorting mirrors autonomous_pipeline.py's canonical() helper. It is
   * intentionally distinct from a generated output artifact checksum.
   */
  public static function artifactManifestDigest(array $artifacts): string {
    $manifest = [];
    foreach ($artifacts as $artifact) {
      if (!is_array($artifact)) {
        throw new \InvalidArgumentException('Artifact manifest must contain objects.');
      }
      $manifest[] = [
        'bytes' => (int) ($artifact['bytes'] ?? -1),
        'path' => (string) ($artifact['path'] ?? ''),
        'role' => (string) ($artifact['role'] ?? ''),
        'sha256' => (string) ($artifact['sha256'] ?? ''),
      ];
    }
    usort($manifest, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
    return hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  /**
   * Validates the immutable successful return contract.
   */
  private function validateSuccess(array $success): void {
    if (($success['schema'] ?? '') !== self::SUCCESS_SCHEMA || ($success['status'] ?? '') !== 'succeeded') {
      throw new \InvalidArgumentException('A successful Site Studio result packet is required.');
    }
    foreach (['event_id', 'packet_id', 'idempotency_key', 'request_id', 'project_id', 'build_id', 'completed_at'] as $field) {
      if (trim((string) ($success[$field] ?? '')) === '') {
        throw new \InvalidArgumentException(sprintf('Site Studio success %s is required.', $field));
      }
    }
    if (!is_array($success['artifacts'] ?? NULL) || !$success['artifacts'] || !is_array($success['stage_ledger'] ?? NULL) || !$success['stage_ledger']) {
      throw new \InvalidArgumentException('Site Studio artifacts and stage ledger are required.');
    }
    foreach ($success['artifacts'] as $artifact) {
      if (!is_array($artifact)
        || !preg_match('/^direction-[a-f]$/', (string) ($artifact['direction_id'] ?? ''))
        || trim((string) ($artifact['uri'] ?? '')) === ''
        || !preg_match('/^[a-f0-9]{64}$/', (string) ($artifact['sha256'] ?? ''))) {
        throw new \InvalidArgumentException('Site Studio returned an invalid artifact record.');
      }
    }
    foreach ($success['stage_ledger'] as $stage) {
      if (!is_array($stage) || ($stage['status'] ?? '') !== 'passed' || trim((string) ($stage['stage'] ?? '')) === '') {
        throw new \InvalidArgumentException('Every returned Site Studio stage must be identified and passed.');
      }
    }
  }

  /**
   * Loads a project by numeric entity ID or UUID.
   */
  private function loadProject(string $identifier): Project {
    $storage = $this->entities->getStorage('famtastic_project');
    if (ctype_digit($identifier)) {
      $project = $storage->load((int) $identifier);
    }
    else {
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uuid', $identifier)->range(0, 1)->execute();
      $project = $ids ? $storage->load(reset($ids)) : NULL;
    }
    if (!$project instanceof Project) {
      throw new \InvalidArgumentException('Unknown Site Studio project.');
    }
    return $project;
  }

  /**
   * Returns the packet registered on a project, when present.
   */
  private function projectPacket(Project $project): ?array {
    $value = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE);
    $packet = is_array($value) ? ($value['site_studio_build_packet'] ?? NULL) : NULL;
    return is_array($packet) ? $packet : NULL;
  }

  /**
   * Resolves the owning customer workspace for a project.
   */
  private function organizationForProject(int $projectId): ?int {
    $value = $this->database->select('famtastic_customer_resource', 'r')
      ->fields('r', ['organization_id'])
      ->condition('resource_type', 'project')
      ->condition('resource_id', $projectId)
      ->execute()->fetchField();
    return $value ? (int) $value : NULL;
  }

  /**
   * Returns the first active member email for a workspace.
   */
  private function organizationEmail(int $organizationId): ?string {
    $query = $this->database->select('famtastic_membership', 'm');
    $query->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $value = $query->fields('c', ['email'])
      ->condition('m.organization_id', $organizationId)
      ->condition('m.status', 'active')
      ->orderBy('m.id', 'ASC')
      ->range(0, 1)
      ->execute()->fetchField();
    return $value ? (string) $value : NULL;
  }

  /**
   * Finds the first request ID in a bounded packet/request tree.
   */
  private function requestIdFrom(array $value, int $depth = 0): ?string {
    if ($depth > 5) {
      return NULL;
    }
    if (isset($value['request_id']) && trim((string) $value['request_id']) !== '') {
      return (string) $value['request_id'];
    }
    foreach ($value as $child) {
      if (is_array($child)) {
        $found = $this->requestIdFrom($child, $depth + 1);
        if ($found !== NULL) {
          return $found;
        }
      }
    }
    return NULL;
  }

}
