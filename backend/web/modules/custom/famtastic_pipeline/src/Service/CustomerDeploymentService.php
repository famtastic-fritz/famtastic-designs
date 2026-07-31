<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/**
 * Builds immutable customer releases and promotes them with backup/rollback.
 */
final class CustomerDeploymentService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Creates an immutable release from an approved project.
   */
  public function prepare(int $projectId): array {
    $project = $this->entityTypeManager->getStorage('famtastic_project')->load($projectId);
    if (!$project || $project->get('approval_status')->value !== 'approved') {
      throw new \RuntimeException('Only an approved project can become a release.');
    }
    $releaseSha = (string) $project->get('release_sha')->value;
    $sourceChecksum = (string) $project->get('artifact_checksum')->value;
    if (!preg_match('/^[a-f0-9]{64}$/', $releaseSha) || !preg_match('/^[a-f0-9]{64}$/', $sourceChecksum)) {
      throw new \RuntimeException('Approved project is missing immutable release fingerprints.');
    }
    $prospect = $project->get('prospect_ref')->entity;
    if (!$prospect) {
      throw new \RuntimeException('Project prospect is missing.');
    }
    $customerKey = sprintf('customer-%d-%s', $prospect->id(), $this->slug((string) $prospect->label()));
    $releaseRoot = $this->releaseRoot();
    $releaseDir = $releaseRoot . '/' . $customerKey . '/' . $releaseSha;
    $this->ensureDirectory($releaseDir);

    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE);
    $businessName = (string) ($studio['business']['name'] ?? $prospect->label());
    $description = (string) ($studio['business']['description'] ?? 'A local business ready to serve its customers.');
    $html = $this->renderRelease($businessName, $description, $releaseSha);
    $artifactChecksum = hash('sha256', $html);
    $manifest = [
      'schema_version' => 1,
      'project_id' => $projectId,
      'customer_key' => $customerKey,
      'release_sha' => $releaseSha,
      'source_checksum' => $sourceChecksum,
      'artifact_checksum' => $artifactChecksum,
      'created_at' => gmdate(DATE_ATOM, $this->time->getRequestTime()),
      'files' => ['index.html' => $artifactChecksum],
    ];
    $indexPath = $releaseDir . '/index.html';
    $manifestPath = $releaseDir . '/release.json';
    if (is_file($manifestPath)) {
      $existing = json_decode((string) file_get_contents($manifestPath), TRUE);
      if (
        !is_array($existing)
        || ($existing['artifact_checksum'] ?? '') !== $artifactChecksum
        || !is_file($indexPath)
        || hash_file('sha256', $indexPath) !== $artifactChecksum
      ) {
        throw new \RuntimeException('Immutable release path already exists with different content.');
      }
    }
    else {
      $this->atomicWrite($indexPath, $html);
      $this->atomicWrite($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    $deploymentKey = 'project:' . $projectId . ':release:' . $releaseSha;
    $existingId = $this->database->select('famtastic_deployment', 'd')
      ->fields('d', ['id'])
      ->condition('deployment_key', $deploymentKey)
      ->execute()
      ->fetchField();
    if ($existingId) {
      return $this->load((int) $existingId);
    }
    $targetPath = $this->targetRoot(FALSE) . '/' . $customerKey;
    $id = (int) $this->database->insert('famtastic_deployment')
      ->fields([
        'deployment_key' => $deploymentKey,
        'project_id' => $projectId,
        'customer_key' => $customerKey,
        'release_sha' => $releaseSha,
        'artifact_checksum' => $artifactChecksum,
        'status' => 'prepared',
        'target_path' => $targetPath,
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
    $this->ledger->recordEvent(
      'deployment.prepared:' . $deploymentKey,
      'deployment.prepared',
      ['deployment_id' => $id, 'release_sha' => $releaseSha, 'artifact_checksum' => $artifactChecksum],
      (int) $prospect->id(),
      projectId: $projectId,
    );
    $this->ledger->enqueue(
      'deployment.apply:' . $deploymentKey,
      'deployment.apply',
      ['deployment_id' => $id],
      (int) $prospect->id(),
    );
    return $this->load($id);
  }

  /**
   * Atomically promotes one prepared release to its isolated customer target.
   */
  public function apply(int $deploymentId): array {
    $deployment = $this->load($deploymentId);
    if (!$deployment) {
      throw new \RuntimeException('Deployment does not exist.');
    }
    if ($deployment['status'] === 'deployed') {
      return $deployment;
    }
    $transport = getenv('FAMTASTIC_DEPLOY_TRANSPORT') ?: Settings::get('famtastic_deploy_transport', 'disabled');
    if (!in_array($transport, ['local', 'real'], TRUE)) {
      throw new \RuntimeException('Customer deployment transport is disabled.');
    }
    if ($transport === 'real') {
      $allowed = filter_var(
        getenv('FAMTASTIC_ALLOW_CUSTOMER_DEPLOYMENTS') ?: Settings::get('famtastic_allow_customer_deployments', FALSE),
        FILTER_VALIDATE_BOOL,
      );
      if (!$allowed) {
        throw new \RuntimeException('Real customer deployment requires explicit environment approval.');
      }
    }
    $targetRoot = $this->targetRoot(TRUE);
    $target = $targetRoot . '/' . $deployment['customer_key'];
    $release = $this->releaseRoot() . '/' . $deployment['customer_key'] . '/' . $deployment['release_sha'];
    $manifest = json_decode((string) @file_get_contents($release . '/release.json'), TRUE);
    if (
      !is_array($manifest)
      || ($manifest['artifact_checksum'] ?? '') !== $deployment['artifact_checksum']
      || hash_file('sha256', $release . '/index.html') !== $deployment['artifact_checksum']
    ) {
      throw new \RuntimeException('Release verification failed before deployment.');
    }

    $stagingRoot = $targetRoot . '/.staging';
    $backupRoot = $targetRoot . '/.backups';
    $this->ensureDirectory($stagingRoot);
    $this->ensureDirectory($backupRoot);
    $stage = $stagingRoot . '/' . $deployment['customer_key'] . '-' . $deployment['release_sha'];
    if (is_dir($stage)) {
      $this->removeManagedTree($stage, $stagingRoot);
    }
    $this->copyTree($release, $stage);
    $backup = '';
    if (is_dir($target)) {
      $backup = $backupRoot . '/' . $deployment['customer_key'] . '-' . gmdate('Ymd\THis\Z', $this->time->getRequestTime());
      if (!rename($target, $backup)) {
        throw new \RuntimeException('Could not create deployment backup.');
      }
    }
    if (!rename($stage, $target)) {
      if ($backup !== '' && is_dir($backup)) {
        rename($backup, $target);
      }
      throw new \RuntimeException('Atomic customer release promotion failed.');
    }
    $deployedChecksum = hash_file('sha256', $target . '/index.html');
    if ($deployedChecksum !== $deployment['artifact_checksum']) {
      $failedRoot = $targetRoot . '/.failed';
      $this->ensureDirectory($failedRoot);
      $failed = $failedRoot . '/' . $deployment['customer_key'] . '-verification-' . gmdate('Ymd\THis\Z', $this->time->getRequestTime());
      rename($target, $failed);
      if ($backup !== '' && is_dir($backup)) {
        rename($backup, $target);
      }
      throw new \RuntimeException('Deployed artifact checksum mismatch.');
    }
    $publicBase = rtrim((string) (getenv('FAMTASTIC_CUSTOMER_PUBLIC_BASE') ?: ''), '/');
    $publicUrl = $publicBase !== '' ? $publicBase . '/' . $deployment['customer_key'] . '/' : '';
    $verification = [
      'index_exists' => TRUE,
      'release_manifest_exists' => is_file($target . '/release.json'),
      'artifact_checksum' => $deployedChecksum,
      'transport' => $transport,
    ];
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_deployment')
      ->fields([
        'status' => 'deployed',
        'target_path' => $target,
        'public_url' => $publicUrl,
        'backup_path' => $backup,
        'verification' => json_encode($verification, JSON_THROW_ON_ERROR),
        'deployed_at' => $now,
      ])
      ->condition('id', $deploymentId)
      ->execute();
    $project = $this->entityTypeManager->getStorage('famtastic_project')->load($deployment['project_id']);
    if ($project) {
      $project->set('delivery_status', 'deployed');
      if ($publicUrl !== '') {
        $project->set('live_url', $publicUrl);
      }
      $project->save();
    }
    $this->ledger->recordEvent(
      'deployment.deployed:' . $deployment['deployment_key'],
      'deployment.deployed',
      ['deployment_id' => $deploymentId, 'public_url' => $publicUrl, 'verification' => $verification],
      projectId: (int) $deployment['project_id'],
    );
    $this->ledger->enqueue(
      'domain.verify:deployment:' . $deploymentId,
      'domain.verify',
      ['deployment_id' => $deploymentId],
    );
    return $this->load($deploymentId);
  }

  /**
   * Restores the exact pre-deployment backup and records rollback evidence.
   */
  public function rollback(int $deploymentId): array {
    $deployment = $this->load($deploymentId);
    if (!$deployment || $deployment['backup_path'] === '' || !is_dir($deployment['backup_path'])) {
      throw new \RuntimeException('Deployment has no restorable backup.');
    }
    $targetRoot = $this->targetRoot(TRUE);
    $target = (string) $deployment['target_path'];
    $this->assertManagedPath($target, $targetRoot);
    $failed = $targetRoot . '/.failed/' . $deployment['customer_key'] . '-' . gmdate('Ymd\THis\Z', $this->time->getRequestTime());
    $this->ensureDirectory(dirname($failed));
    if (is_dir($target) && !rename($target, $failed)) {
      throw new \RuntimeException('Could not preserve failed deployed release.');
    }
    if (!rename($deployment['backup_path'], $target)) {
      if (is_dir($failed)) {
        rename($failed, $target);
      }
      throw new \RuntimeException('Could not restore deployment backup.');
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_deployment')
      ->fields([
        'status' => 'rolled_back',
        'rolled_back_at' => $now,
        'verification' => json_encode([
          'rollback_target_exists' => is_dir($target),
          'failed_release_preserved' => is_dir($failed),
        ], JSON_THROW_ON_ERROR),
      ])
      ->condition('id', $deploymentId)
      ->execute();
    $this->ledger->recordEvent(
      'deployment.rolled_back:' . $deployment['deployment_key'],
      'deployment.rolled_back',
      ['deployment_id' => $deploymentId, 'failed_release_path' => $failed],
      projectId: (int) $deployment['project_id'],
    );
    return $this->load($deploymentId);
  }

  public function load(int $id): ?array {
    $record = $this->database->select('famtastic_deployment', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    return $record ?: NULL;
  }

  private function releaseRoot(): string {
    $root = (string) (getenv('FAMTASTIC_CUSTOMER_RELEASE_ROOT') ?: dirname(\Drupal::root()) . '/private/customer-releases');
    $this->ensureDirectory($root);
    return rtrim(realpath($root) ?: $root, '/');
  }

  private function targetRoot(bool $required): string {
    $root = (string) (getenv('FAMTASTIC_CUSTOMER_DEPLOY_ROOT') ?: '');
    if ($root === '') {
      if ($required) {
        throw new \RuntimeException('FAMTASTIC_CUSTOMER_DEPLOY_ROOT is required.');
      }
      return '[pending-target]';
    }
    $this->ensureDirectory($root);
    return rtrim(realpath($root) ?: $root, '/');
  }

  private function renderRelease(string $businessName, string $description, string $releaseSha): string {
    $name = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
    $copy = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="famtastic-release" content="' . $releaseSha . '"><title>' . $name . '</title><style>body{font-family:system-ui;margin:0;background:#10130f;color:#f6f8f2}main{max-width:820px;margin:auto;padding:12vh 24px}h1{font-size:clamp(3rem,9vw,7rem);line-height:.9;color:#b8f135}p{font-size:1.25rem;max-width:55ch}</style></head><body><main><h1>' . $name . '</h1><p>' . $copy . '</p></main></body></html>';
  }

  private function slug(string $value): string {
    $slug = trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value)), '-');
    return substr($slug !== '' ? $slug : 'site', 0, 48);
  }

  private function ensureDirectory(string $path): void {
    if (!is_dir($path) && !mkdir($path, 0770, TRUE) && !is_dir($path)) {
      throw new \RuntimeException('Could not create managed deployment directory.');
    }
  }

  private function atomicWrite(string $path, string $content): void {
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
    if (file_put_contents($temporary, $content, LOCK_EX) === FALSE || !rename($temporary, $path)) {
      @unlink($temporary);
      throw new \RuntimeException('Could not write immutable release artifact.');
    }
  }

  private function copyTree(string $source, string $target): void {
    $this->ensureDirectory($target);
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
      $destination = $target . '/' . $iterator->getSubPathName();
      if ($item->isLink()) {
        throw new \RuntimeException('Release artifacts may not contain symlinks.');
      }
      if ($item->isDir()) {
        $this->ensureDirectory($destination);
      }
      elseif (!copy($item->getPathname(), $destination)) {
        throw new \RuntimeException('Could not stage release artifact.');
      }
    }
  }

  private function removeManagedTree(string $path, string $root): void {
    $this->assertManagedPath($path, $root);
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
      $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
  }

  private function assertManagedPath(string $path, string $root): void {
    $normalizedRoot = rtrim(realpath($root) ?: $root, '/') . '/';
    $normalizedPath = rtrim(realpath($path) ?: $path, '/') . '/';
    if (!str_starts_with($normalizedPath, $normalizedRoot) || $normalizedPath === $normalizedRoot) {
      throw new \RuntimeException('Refusing operation outside the managed deployment root.');
    }
  }

}
