<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Receipt-authorized private reads. Unregistered; never a release/selection API.
 *
 * Installation supplies all three closures, never HTTP data:
 * - packageFactory(Closure $resolveReceipt): ManagedProofArtifactPackage creates
 *   a NEW package instance with the supplied resolver and trusted private roots,
 *   prepared store and pinned logo. The reader never accepts a filesystem path.
 * - authenticateReviewer(object $principal, array $receipt, array $request):
 *   ?string authenticates a server principal for this exact request and returns
 *   its canonical automation:<worker-id>. A body identity/boolean is not proof.
 * - verifyCustomerRelease(array $committed, array $request,
 *   AccountInterface $principal): array verifies the actual managed release and
 *   returns EXACTLY receipt_id, receipt_sha256, request_id, customer_id,
 *   campaign_id, package_manifest_sha256, producer_ids, proof_review_status,
 *   proof_approved_at, evidence_sha256, release_sha256. The last two digests name
 *   independently verified QA evidence and its stored immutable release decision;
 *   the other fields must equal current receipt/request facts. No default grant.
 *
 * Customer principals must come from Drupal authentication, never deserialization.
 * Reviewer principals may be server identity objects authenticated by the closure.
 * The release verifier must inspect authoritative evidence, not echo its inputs.
 * It must be local/read-only and must NOT call this reader (no recursive grant).
 *
 * All methods require the primary connection OUTSIDE any transaction. They do
 * not write or request locks. Authority is re-read around package I/O and at its
 * receipt resolver; observed changes fail closed. This is not a linearizable
 * revocation guarantee across concurrent commits/HTTP response delivery. Release
 * writers still need their own locked current-authority check. Never reuse these
 * facts as a later write grant. Selected/project/revision transitions are closed.
 */
final class ManagedProofReader {
  /** @var \WeakMap<object, array> Opaque handles are valid only on this instance. */
  private \WeakMap $contexts;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly ?\Closure $packageFactory = NULL,
    private readonly ?\Closure $authenticateReviewer = NULL,
    private readonly ?\Closure $verifyCustomerRelease = NULL,
  ) {
    $this->contexts = new \WeakMap();
  }

  private function __clone() {}

  /** No caller context array, receipt ID, path, manifest or cached grant accepted. */
  public function context(int $requestId, object $principal, string $purpose): object {
    $this->outsideTransaction();
    if ($this->packageFactory === NULL) throw new \RuntimeException('Managed reader package factory is unconfigured.');
    $current = $this->authorize($requestId, $principal, $purpose);
    $handle = new \stdClass();
    $this->contexts[$handle] = [
      'request_id' => $requestId, 'principal' => $principal, 'purpose' => $purpose,
      'binding' => $this->fingerprint($current),
    ];
    return $handle;
  }

  /** Curated QA facts after actual package verification; no manifest/paths/DNA. */
  public function facts(object $context): array {
    $opened = $this->open($context);
    $current = $opened['current']; $r = $current['committed']['receipt'];
    $hashes = []; $originals = []; $names = [];
    foreach (['a', 'b', 'c'] as $direction) {
      $hashes[$direction] = $r['variants'][$direction]['html_sha256'];
      $originals[$direction] = $r['variants'][$direction]['original_sha256'];
      $names[$direction] = $r['variants'][$direction]['name'];
    }
    return [
      'schema' => 'famtastic.managed-proof-read-context.v1',
      'request_id' => $r['request_id'], 'request_public_id' => $current['request']['public_id'],
      'customer_id' => $r['customer_id'], 'organization_id' => $r['organization_id'],
      'campaign_id' => $r['campaign_entity_id'], 'job_id' => $r['job_id'],
      'receipt_id' => $current['committed']['receipt_id'], 'receipt_sha256' => $current['committed']['receipt_sha256'],
      'package_id' => $r['package_id'], 'package_manifest_sha256' => $r['package_manifest_sha256'],
      'producer_ids' => $r['producer_ids'], 'actor' => $current['actor'],
      'purpose' => $this->contexts[$context]['purpose'], 'proof_review_status' => $current['request']['proof_review_status'],
      'artifact_hashes' => $hashes, 'original_artifact_hashes' => $originals, 'direction_names' => $names,
      'release_sha256' => $current['release_sha256'], 'evidence_sha256' => $current['evidence_sha256'],
    ];
  }

  /** @return array{bytes:string, media_type:string, sha256:string, size_bytes:int} */
  public function readRole(object $context, string $direction, string $role, ?string $assetId = NULL): array {
    $this->role($direction, $role, $assetId);
    return $this->readOpened($context, $this->open($context), $direction, $role, $assetId);
  }

  /**
   * Exact direction-relative URL path, INCLUDING assets/. The controller prefixes
   * its existing asset_path parameter once. No trimming/decoding/case/path aliases.
   */
  public function readRelativeAsset(object $context, string $direction, string $relativePath): array {
    $this->role($direction, 'html', NULL);
    if (!str_starts_with($relativePath, 'assets/')) throw new \InvalidArgumentException('Managed asset path is not exact.');
    $ordinary = substr($relativePath, strlen('assets/'));
    if (ProofAssetContract::normalizeRelativePath($ordinary) !== $ordinary) throw new \InvalidArgumentException('Managed asset path is not exact.');
    $opened = $this->open($context);
    if ($relativePath === SelectedCreatorCreditProjection::ASSET_PATH) {
      return $this->readOpened($context, $opened, $direction, 'system_logo', NULL);
    }
    foreach ($opened['manifest']['variants'][$direction]['assets'] as $id => $path) {
      if ($path === $direction . '/' . $relativePath) return $this->readOpened($context, $opened, $direction, 'asset', $id);
    }
    throw new \RuntimeException('Managed asset is not declared.');
  }

  private function authorize(int $requestId, object $principal, string $purpose): array {
    $this->outsideTransaction();
    if ($requestId < 1 || !in_array($purpose, ['reviewer', 'customer'], TRUE)) throw new \InvalidArgumentException('Invalid managed read request or purpose.');
    $current = ManagedProofCurrentBinding::read($this->database, $requestId, $this->time->getCurrentTime());
    $request = $current['request']; $customer = $current['customer'];
    $committed = $current['committed'];
    $r = $committed['receipt'];

    $releaseHash = NULL; $evidenceHash = NULL;
    if ($purpose === 'reviewer') {
      if ($request['proof_review_status'] !== 'owner_review' || $request['proof_approved_by_uid'] !== NULL
        || $request['proof_approved_at'] !== NULL || $request['proof_notified_at'] !== NULL) throw new \RuntimeException('Managed proof is not pending independent review.');
      if ($this->authenticateReviewer === NULL) throw new \RuntimeException('Managed reviewer authentication is unconfigured.');
      $actor = ($this->authenticateReviewer)($principal, $r, $request);
      if (!is_string($actor) || !preg_match('/\Aautomation:[a-z][a-z0-9._-]{2,63}\z/', $actor)
        || in_array($actor, $r['producer_ids'], TRUE)) throw new \RuntimeException('Managed reviewer is unauthenticated or a producer.');
    }
    else {
      if (!$principal instanceof AccountInterface || !$principal->isAuthenticated() || (int) $principal->id() < 1
        || (int) $principal->id() !== (int) $customer['uid']) throw new \RuntimeException('Managed customer principal does not own this request.');
      if (!in_array($request['proof_review_status'], ['customer_ready', 'notified'], TRUE)
        || $request['proof_approved_by_uid'] !== NULL || (int) $request['proof_approved_at'] < 1) throw new \RuntimeException('Managed customer release is absent.');
      if ($this->verifyCustomerRelease === NULL) throw new \RuntimeException('Managed customer release verifier is unconfigured.');
      $grant = ($this->verifyCustomerRelease)($committed, $request, $principal);
      $expected = ['receipt_id' => $committed['receipt_id'], 'receipt_sha256' => $committed['receipt_sha256'],
        'request_id' => $r['request_id'], 'customer_id' => $r['customer_id'], 'campaign_id' => $r['campaign_entity_id'],
        'package_manifest_sha256' => $r['package_manifest_sha256'], 'producer_ids' => $r['producer_ids'], 'proof_review_status' => $request['proof_review_status'],
        'proof_approved_at' => (int) $request['proof_approved_at']];
      if (!is_array($grant)) throw new \RuntimeException('Managed release attestation is invalid.');
      ProofOperationContract::keys($grant, [...array_keys($expected), 'evidence_sha256', 'release_sha256']);
      foreach ($expected as $key => $value) if ($grant[$key] !== $value) throw new \RuntimeException('Managed release attestation differs from current receipt.');
      ProofOperationContract::digest($grant['release_sha256']);
      ProofOperationContract::digest($grant['evidence_sha256']);
      $releaseHash = $grant['release_sha256']; $evidenceHash = $grant['evidence_sha256'];
      $actor = 'customer:' . $r['customer_id'] . ':uid:' . (int) $principal->id();
    }
    // Reverse ONLY receipt-proven import (+ explicitly verified release above).
    // No selection, project, commerce, authored input or asset field is erased.
    $beforeImport = $request; $beforeImport['proof_review_status'] = 'not_started';
    $binding = FreshProofBinding::read($this->database, $current['admission_event'], $beforeImport);
    // Preserve the post-authentication rights sample, including changes observed
    // while the trusted release verifier was reading retained evidence.
    if ($binding['binding']['asset_snapshot'] !== $current['asset_snapshot']
      || $current['asset_snapshot'] !== FreshProofInput::assets($this->database, $request, FALSE)) throw new \RuntimeException('Managed asset authority changed.');
    $this->outsideTransaction();
    return ['committed' => $committed, 'request' => $request, 'actor' => $actor, 'release_sha256' => $releaseHash, 'evidence_sha256' => $evidenceHash,
      'authority_sha256' => $current['authority_sha256']];
  }

  private function reauthorize(object $context): array {
    $this->outsideTransaction();
    if (!isset($this->contexts[$context])) throw new \InvalidArgumentException('Unknown managed read context.');
    $bound = $this->contexts[$context];
    $current = $this->authorize($bound['request_id'], $bound['principal'], $bound['purpose']);
    if ($bound['binding'] !== $this->fingerprint($current)) throw new \RuntimeException('Managed read context changed; obtain a fresh context.');
    return $current;
  }

  private function fingerprint(array $current): array {
    return [$current['committed']['receipt_id'], $current['committed']['receipt_sha256'], $current['actor'],
      $current['release_sha256'], $current['evidence_sha256'], $current['authority_sha256']];
  }

  /** Internal package + manifest never escape as a caller-controlled context. */
  private function open(object $context): array {
    $current = $this->reauthorize($context); $r = $current['committed']['receipt'];
    $resolver = function (string $receiptId, string $direction, string $role, ?string $assetId) use ($context): array {
      $this->role($direction, $role, $assetId);
      $current = $this->reauthorize($context); $r = $current['committed']['receipt'];
      if ($receiptId !== $current['committed']['receipt_id']) throw new \RuntimeException('Managed package receipt differs.');
      return ['receipt_id' => $receiptId, 'package_id' => $r['package_id'], 'package_manifest_sha256' => $r['package_manifest_sha256'],
        'prepared_bundle_id' => $r['prepared_bundle_id'], 'prepared_manifest_sha256' => $r['prepared_manifest_sha256'],
        'allowed_roles' => [$direction => [$role]]];
    };
    $package = ($this->packageFactory)($resolver);
    if (!$package instanceof ManagedProofArtifactPackage) throw new \RuntimeException('Managed package factory returned an invalid reader.');
    $verified = $package->verifyPreparedPackage($r['package_id'], $r['package_manifest_sha256'], $r['prepared_bundle_id'], $r['prepared_manifest_sha256']);
    $manifest = $verified['manifest'];
    foreach (['a', 'b', 'c'] as $d) {
      $v = $manifest['variants'][$d]; $expected = $r['variants'][$d];
      if ($manifest['files'][$v['html']]['sha256'] !== $expected['html_sha256']
        || $v['projection']['original_home']['sha256'] !== $expected['original_sha256']
        || ManagedProofImportContract::hash($v['projection']) !== $expected['projection_sha256']) throw new \RuntimeException('Managed package differs from imported variants.');
    }
    $this->reauthorize($context);
    return ['current' => $current, 'package' => $package, 'manifest' => $manifest];
  }

  private function readOpened(object $context, array $opened, string $direction, string $role, ?string $assetId): array {
    $this->role($direction, $role, $assetId);
    $v = $opened['manifest']['variants'][$direction];
    $path = $role === 'asset' ? ($v['assets'][$assetId] ?? NULL) : $v[$role];
    if ($path === NULL) throw new \RuntimeException('Managed package role is absent.');
    $expected = $opened['manifest']['files'][$path];
    $this->reauthorize($context);
    $bytes = $opened['package']->read($opened['current']['committed']['receipt_id'], $direction, $role, $assetId);
    if (array_keys($bytes) !== ['bytes', 'media_type', 'sha256', 'size_bytes'] || !is_string($bytes['bytes'])
      || $bytes['media_type'] !== $expected['media_type'] || $bytes['sha256'] !== $expected['sha256']
      || $bytes['size_bytes'] !== $expected['size_bytes'] || strlen($bytes['bytes']) !== $bytes['size_bytes']
      || hash('sha256', $bytes['bytes']) !== $bytes['sha256']) throw new \RuntimeException('Managed package read differs from verified content.');
    $this->reauthorize($context);
    return $bytes;
  }

  private function role(string $direction, string $role, ?string $assetId): void {
    if (!in_array($direction, ['a', 'b', 'c'], TRUE) || !in_array($role, ['html', 'asset', 'thumbnail', 'system_logo'], TRUE)
      || ($role === 'asset' ? $assetId === NULL || !preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $assetId) : $assetId !== NULL)) throw new \InvalidArgumentException('Invalid managed read role.');
  }

  private function outsideTransaction(): void {
    if ($this->database->inTransaction()) throw new \LogicException('Managed reader requires a committed connection outside transactions.');
  }
}
