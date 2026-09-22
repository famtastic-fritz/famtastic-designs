<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;

/** Staff attaches one finished review without selecting, accepting or sending. */
final class FullSiteReviewService {

  public function __construct(
    private readonly Connection $database,
    private readonly FileSystemInterface $fileSystem,
    private readonly TimeInterface $time,
    private readonly AccountProxyInterface $account,
    private readonly OperationalLedger $ledger,
    private readonly UuidInterface $uuid,
  ) {}

  /** Creates an explicitly staff-authored draft and attaches the review atomically. */
  public function createAndAttach(int $customerId, int $organizationId, string $projectName, string $requestKey, string $sourceDirectory, array $input, string $actor, string $authority): array {
    $this->assertStaffAuthority($actor, $authority);
    $projectName = trim(strip_tags($projectName));
    if ($customerId < 1 || $organizationId < 1 || $projectName === '' || mb_strlen($projectName) > 255 || !preg_match('/^[a-z0-9][a-z0-9._-]{2,119}$/', $requestKey)) throw new \InvalidArgumentException('Exact customer, organization, project name and unique staff request key are required.');
    $binding = ['customer_id' => $customerId, 'organization_id' => $organizationId, 'project_name' => $projectName, 'request_key' => $requestKey];
    $bindingDigest = hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $eventKey = 'full-site-review-draft:' . $organizationId . ':' . $customerId . ':' . $requestKey;
    $ownsRoot = !$this->database->inTransaction();
    // Routing hint only. Existing requests must lock request before membership,
    // like customer asset writers; the event is revalidated under those locks.
    $hint = $this->draftEvent($eventKey);
    if ($hint === FALSE && !$ownsRoot) throw new \RuntimeException('New staff drafts require a fresh root transaction.');
    for ($pass = 0; $pass < 2; $pass++) {
      $transaction = $this->database->startTransaction();
      $closed = FALSE;
      try {
        $created = $hint === FALSE;
        if (!$created) {
          $event = json_decode((string) $hint, TRUE, 512, JSON_THROW_ON_ERROR);
          if (!hash_equals($bindingDigest, (string) ($event['binding_sha256'] ?? ''))) throw new \RuntimeException('This staff request key already has a different binding.');
          $publicId = (string) ($event['request_public_id'] ?? '');
          $row = $this->ownedRequest($customerId, $publicId, TRUE);
          if (!$row || (int) $row['id'] !== (int) ($event['website_request_id'] ?? 0) || (int) $row['organization_id'] !== $organizationId) throw new \RuntimeException('The original staff draft binding is unavailable.');
          if ($this->draftEvent($eventKey, TRUE) !== $hint) throw new \RuntimeException('The original staff draft binding changed.');
          $requestId = (int) $row['id'];
        }
        else {
          // There is no existing request to lock. Membership serializes creation.
          // Do not establish a repeatable-read snapshot before this locking read.
          if (!$this->activeMembership($customerId, $organizationId, TRUE)) throw new \RuntimeException('Exact active customer organization membership is required.');
          $prior = $this->draftEvent($eventKey, TRUE);
          if ($prior !== FALSE) {
            // A competing creator committed while we waited. Release our ROOT
            // before acquiring its request, never invert membership -> request.
            $transaction->rollBack(); $closed = TRUE; unset($transaction);
            $hint = $prior;
            continue;
          }
        }
        $customer = $this->database->select('famtastic_customer', 'c')->fields('c', ['id'])->condition('id', $customerId)->execute()->fetchField();
        $organization = $this->database->select('famtastic_organization', 'o')->fields('o', ['id'])->condition('id', $organizationId)->condition('status', 'active')->execute()->fetchField();
        if (!$customer || !$organization) throw new \RuntimeException('Exact active customer organization membership is required.');
        if ($created) {
          $matching = $this->database->select('famtastic_project_request', 'r')->condition('customer_id', $customerId)->condition('organization_id', $organizationId)->condition('project_name', $projectName)->countQuery()->execute()->fetchField();
          if ($matching) throw new \RuntimeException('A matching request exists; attach using its verified exact public UUID.');
          $publicId = $this->uuid->generate();
          $now = $this->time->getRequestTime();
          $intake = ['staff_assisted_brief' => ['schema' => 'famtastic.staff-assisted-brief.v1', 'actor' => $actor, 'authority' => $authority, 'customer_supplied' => FALSE, 'purpose' => 'Owner-authorized full website review, created by staff without customer submission.', 'request_key' => $requestKey]];
          $requestId = (int) $this->database->insert('famtastic_project_request')->fields(['public_id' => $publicId, 'customer_id' => $customerId, 'organization_id' => $organizationId, 'project_name' => $projectName, 'business_name' => $projectName, 'project_type' => 'new_website', 'domain_choice' => 'undecided', 'status' => 'draft', 'proof_review_status' => 'not_started', 'intake_data' => json_encode($intake, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'created' => $now, 'changed' => $now])->execute();
          $payload = $binding + ['binding_sha256' => $bindingDigest, 'website_request_id' => $requestId, 'request_public_id' => $publicId, 'actor' => $actor, 'authority_ref' => $authority, 'execution_uid' => (int) $this->account->id(), 'customer_supplied' => FALSE, 'customer_submission' => FALSE, 'notification_queued' => FALSE, 'proof_job_queued' => FALSE, 'payment_changed' => FALSE];
          if (!$this->ledger->recordEvent($eventKey, 'website_request.staff_full_site_review_draft', $payload, provider: 'staff_full_site_review')) throw new \RuntimeException('Staff draft changed concurrently; retry the exact request key.');
        }
        $result = $this->attach($publicId, $customerId, $organizationId, $sourceDirectory, $input, $actor, $authority);
        $transaction->commitOrRelease(); $closed = TRUE; unset($transaction);
        return ['newly_created' => $created, 'request_id' => $requestId, 'request_public_id' => $publicId] + $result;
      }
      catch (\Throwable $error) { if (!$closed && isset($transaction)) $transaction->rollBack(); throw $error; }
    }
    throw new \RuntimeException('Staff draft changed concurrently; retry the exact request key.');
  }

  /** Installs only allowlisted bytes into private storage, then records ownership. */
  public function attach(string $requestPublicId, int $customerId, int $organizationId, string $sourceDirectory, array $input, string $actor, string $authority): array {
    $this->assertStaffAuthority($actor, $authority);
    $manifest = FullSiteReviewPackage::normalize($input);
    $digest = FullSiteReviewPackage::digest($manifest);
    if (is_link($sourceDirectory) || !is_dir($sourceDirectory)) throw new \InvalidArgumentException('A real static package directory is required.');
    $source = realpath($sourceDirectory);
    if (!$source) throw new \InvalidArgumentException('Static package is unavailable.');
    $transaction = $this->database->startTransaction();
    $closed = FALSE;
    try {
      $row = $this->ownedRequest($customerId, $requestPublicId, TRUE);
      if (!$row || (int) $row['organization_id'] !== $organizationId) throw new \RuntimeException('Exact customer, organization and request binding is required.');
      if (!in_array($row['status'], ['draft', 'submitted'], TRUE) || !empty($row['commerce_order_id']) || !empty($row['project_id']) || !empty($row['proof_campaign_id']) || !in_array((string) $row['proof_review_status'], ['', 'not_started'], TRUE)) throw new \RuntimeException('Full-site review cannot replace an active proof, purchase or selected build.');
      $jobKey = 'website_proof.generate.v1:request:' . $row['id'];
      $jobs = $this->database->select('famtastic_job', 'j');
      $scope = $jobs->orConditionGroup()->condition('job_key', $jobKey)->condition('job_key', $this->database->escapeLike($jobKey . ':brief:') . '%', 'LIKE');
      if ($jobs->fields('j', ['id'])->condition($scope)->range(0, 1)->forUpdate()->execute()->fetchField() !== FALSE) throw new \RuntimeException('Full-site review cannot replace an existing proof routine.');
      $build = $this->database->select('famtastic_build_run', 'b')->fields('b', ['source_sha', 'artifact_checksum'])->condition('build_key', 'build-dna:' . $manifest['build_id'])->execute()->fetchAssoc();
      if (!$build || !hash_equals((string) $build['source_sha'], $manifest['source_commit']) || !preg_match('/^[a-f0-9]{64}$/', (string) $build['artifact_checksum'])) throw new \RuntimeException('Register the matching source Build DNA before attachment.');
      $intake = json_decode((string) $row['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
      $existing = $intake['staff_assisted_brief']['full_site_review'] ?? NULL;
      $eventKey = 'full-site-review:' . $row['id'] . ':' . $manifest['review_id'];
      $prior = $this->draftEvent($eventKey, TRUE);
      if ($prior !== FALSE) {
        $event = json_decode((string) $prior, TRUE, 512, JSON_THROW_ON_ERROR);
        if (!hash_equals((string) ($event['manifest_sha256'] ?? ''), $digest)) throw new \RuntimeException('This immutable review id already has different bytes or metadata.');
        if (!is_array($existing) || ($existing['manifest_sha256'] ?? '') !== $digest || ($existing['manifest']['review_id'] ?? '') !== $manifest['review_id']) throw new \RuntimeException('Historical review retries cannot replace the current version.');
        $root = $this->packageRoot($requestPublicId, $digest, FALSE);
        foreach ($manifest['files'] as $file) FullSiteReviewPackage::read($root, $file);
        $transaction->commitOrRelease(); $closed = TRUE; unset($transaction);
        return ['newly_attached' => FALSE, 'manifest_sha256' => $digest, 'review' => FullSiteReviewPackage::projection($requestPublicId, $existing)];
      }
      // Validate every authored reference before creating the private copy.
      $files = array_column($manifest['files'], NULL, 'path');
      $read = static function (string $path) use ($source, $files): string {
        if (!isset($files[$path])) throw new \RuntimeException('Undeclared package file.');
        return FullSiteReviewPackage::read($source, $files[$path]);
      };
      foreach ($manifest['files'] as $file) $read($file['path']);
      foreach ($manifest['pages'] as $page) FullSiteReviewRenderer::render($read($page['path']), $page['path'], $requestPublicId, $manifest['files'], $read, 'preflight');
      $root = $this->packageRoot($requestPublicId, $digest, TRUE);
      foreach ($manifest['files'] as $file) {
        $target = $root . '/' . $file['path'];
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) throw new \RuntimeException('Private review directory could not be created.');
        $this->assertNoLinks($root, $file['path']);
        if (file_exists($target)) { FullSiteReviewPackage::read($root, $file); continue; }
        $bytes = $read($file['path']);
        $handle = fopen($target, 'xb');
        if (!$handle) throw new \RuntimeException('Private review file could not be created.');
        try { if (fwrite($handle, $bytes) !== strlen($bytes)) throw new \RuntimeException('Private review write was incomplete.'); }
        finally { fclose($handle); }
        chmod($target, 0600);
        FullSiteReviewPackage::read($root, $file);
      }
      $now = $this->time->getRequestTime();
      $record = ['schema' => FullSiteReviewPackage::SCHEMA, 'request_public_id' => $requestPublicId, 'customer_id' => $customerId, 'organization_id' => $organizationId, 'manifest' => $manifest, 'manifest_sha256' => $digest, 'created_at' => gmdate(DATE_ATOM, $now)];
      $payload = ['website_request_id' => (int) $row['id'], 'request_public_id' => $requestPublicId, 'customer_id' => $customerId, 'organization_id' => $organizationId, 'actor' => $actor, 'authority_ref' => $authority, 'execution_uid' => (int) $this->account->id(), 'customer_supplied' => FALSE, 'manifest_sha256' => $digest, 'review' => $record, 'prior_intake_json' => (string) $row['intake_data'], 'prior_intake_sha256' => hash('sha256', (string) $row['intake_data']), 'customer_acceptance' => FALSE, 'notification_queued' => FALSE, 'payment_changed' => FALSE, 'final_launch' => FALSE];
      if (!$this->ledger->recordEvent($eventKey, 'website_request.full_site_review_attached', $payload, prospectId: !empty($row['prospect_id']) ? (int) $row['prospect_id'] : NULL, provider: 'staff_full_site_review')) throw new \RuntimeException('Review changed concurrently; retry the exact manifest.');
      $staff = is_array($intake['staff_assisted_brief'] ?? NULL) ? $intake['staff_assisted_brief'] : [];
      $staff += ['schema' => 'famtastic.staff-assisted-brief.v1', 'actor' => $actor, 'authority' => $authority, 'customer_supplied' => FALSE];
      $staff['full_site_review'] = $record;
      $intake['staff_assisted_brief'] = $staff;
      $this->database->update('famtastic_project_request')->fields(['intake_data' => json_encode($intake, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'changed' => $now])->condition('id', $row['id'])->execute();
      $this->database->insert('famtastic_portal_activity')->fields(['organization_id' => $organizationId, 'event_type' => 'website_request.full_site_review_attached', 'summary' => 'Your full website and research are ready for review.', 'created' => $now])->execute();
      $transaction->commitOrRelease(); $closed = TRUE; unset($transaction);
      return ['newly_attached' => TRUE, 'manifest_sha256' => $digest, 'review' => FullSiteReviewPackage::projection($requestPublicId, $record)];
    }
    catch (\Throwable $error) { if (!$closed && isset($transaction)) $transaction->rollBack(); throw $error; }
  }

  /** Each page, resource and document read independently verifies current tenancy. */
  public function read(int $customerId, string $requestPublicId, string $path): array {
    $row = $this->ownedRequest($customerId, $requestPublicId);
    if (!$row) throw new \RuntimeException('Review not found.');
    return $this->readRecord($row, $path);
  }

  /** Explicit staff QA route, never a customer session or approval impersonation. */
  public function readForStaff(int $requestId, string $path): array {
    if (!$this->account->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('Staff authorization is required.');
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('r.id', $requestId)->execute()->fetchAssoc();
    if (!$row || $requestId < 1) throw new \RuntimeException('Review not found.');
    return $this->readRecord($row, $path);
  }

  private function readRecord(array $row, string $path): array {
    $path = FullSiteReviewPackage::path($path);
    $requestPublicId = (string) $row['public_id'];
    $intake = json_decode((string) $row['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    $record = $intake['staff_assisted_brief']['full_site_review'] ?? NULL;
    if (!is_array($record) || ($record['request_public_id'] ?? '') !== $requestPublicId || ($record['customer_id'] ?? 0) !== (int) $row['customer_id'] || ($record['organization_id'] ?? 0) !== (int) $row['organization_id']) throw new \RuntimeException('Review not found.');
    $manifest = FullSiteReviewPackage::normalize($record['manifest'] ?? []);
    $digest = FullSiteReviewPackage::digest($manifest);
    if (!hash_equals($digest, (string) ($record['manifest_sha256'] ?? ''))) throw new \RuntimeException('Review integrity failed.');
    $files = array_column($manifest['files'], NULL, 'path');
    if (!isset($files[$path])) throw new \RuntimeException('Review file not found.');
    $root = $this->packageRoot($requestPublicId, $digest, FALSE);
    return ['bytes' => FullSiteReviewPackage::read($root, $files[$path]), 'file' => $files[$path], 'manifest' => $manifest, 'manifest_sha256' => $digest, 'request_public_id' => $requestPublicId];
  }

  private function ownedRequest(int $customerId, string $publicId, bool $lock = FALSE): array|false {
    $query = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('r.public_id', $publicId)->condition('r.customer_id', $customerId);
    if ($lock) $query->forUpdate();
    $row = $query->execute()->fetchAssoc();
    if (!$row) return FALSE;
    return $this->activeMembership($customerId, (int) $row['organization_id'], $lock) ? $row : FALSE;
  }

  private function activeMembership(int $customerId, int $organizationId, bool $lock): bool {
    $query = $this->database->select('famtastic_membership', 'm')->fields('m', ['id'])->condition('customer_id', $customerId)->condition('organization_id', $organizationId)->condition('status', 'active');
    if ($lock) $query->forUpdate();
    return $query->execute()->fetchField() !== FALSE;
  }

  private function draftEvent(string $eventKey, bool $lock = FALSE): string|false {
    $query = $this->database->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', $eventKey);
    if ($lock) $query->forUpdate();
    return $query->execute()->fetchField();
  }

  private function packageRoot(string $publicId, string $digest, bool $create): string {
    if (!preg_match('/^[0-9a-f-]{36}$/', $publicId) || !preg_match('/^[a-f0-9]{64}$/', $digest)) throw new \RuntimeException('Invalid private review binding.');
    $base = $this->fileSystem->realpath('private://');
    if (!$base || !is_dir($base)) throw new \RuntimeException('Private storage is required.');
    $base = realpath($base);
    $relative = 'famtastic-full-site-reviews/' . $publicId . '/' . $digest;
    $this->assertNoLinks($base, $relative);
    $path = $base . '/' . $relative;
    if ($create && !is_dir($path) && !mkdir($path, 0700, TRUE) && !is_dir($path)) throw new \RuntimeException('Private review storage could not be created.');
    if (!is_dir($path)) throw new \RuntimeException('Review package is unavailable.');
    return $path;
  }

  private function assertNoLinks(string $root, string $path): void {
    $current = $root;
    foreach (explode('/', $path) as $part) { $current .= '/' . $part; if (is_link($current)) throw new \RuntimeException('Review symlinks are forbidden.'); }
  }

  private function assertStaffAuthority(string $actor, string $authority): void {
    if (!$this->account->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('Staff authorization is required.');
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9:._-]{2,159}$/', $actor) || trim($authority) === '' || strlen($authority) > 2000) throw new \InvalidArgumentException('The actual actor and owner authority reference are required.');
  }
}
