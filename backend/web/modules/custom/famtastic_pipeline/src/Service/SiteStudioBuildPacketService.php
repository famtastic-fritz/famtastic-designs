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
  public function registerPacket(array $packet): array {
    $this->validatePacket($packet);
    $project = $this->loadProject((string) $packet['project_id']);
    $current = $this->projectPacket($project);
    if ($current !== NULL) {
      if (!hash_equals((string) $current['packet_id'], (string) $packet['packet_id'])
        || !hash_equals((string) $current['idempotency_key'], (string) $packet['idempotency_key'])
        || !hash_equals(
          hash('sha256', json_encode($current, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
          hash('sha256', json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        )) {
        throw new \InvalidArgumentException('Project already has a different active Site Studio build packet.');
      }
      return ['newly_registered' => FALSE, 'project' => $project];
    }
    $request = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE);
    $request = is_array($request) ? $request : [];
    $existingRequestId = $this->requestIdFrom($request);
    if ($existingRequestId !== NULL && !hash_equals($existingRequestId, (string) $packet['request_id'])) {
      throw new \InvalidArgumentException('Build packet request does not match the project request.');
    }
    $request['site_studio_build_packet'] = $packet;
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
    return ['newly_registered' => TRUE, 'project' => $project];
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
