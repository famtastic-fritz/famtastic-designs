<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\CharacterAssetService;
use Drupal\famtastic_pipeline\Service\FreshProofBinding;
use Drupal\famtastic_pipeline\Service\ManagedProofReader;
use Drupal\famtastic_pipeline\Service\ProofAssetContract;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Account- and admin-scoped proof previews plus private reference uploads. */
final class WebsiteRequestProofController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly CustomerPortalService $portal,
    private readonly AccountProxyInterface $account,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
    private readonly UuidInterface $uuid,
    private readonly CharacterAssetService $characterAssets,
    private readonly ?ManagedProofReader $managedReader = NULL,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('famtastic_pipeline.customer_portal'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('file.usage'),
      $container->get('uuid'),
      $container->get('famtastic_pipeline.character_assets'),
      $container->has('famtastic_pipeline.managed_proof_reader') ? $container->get('famtastic_pipeline.managed_proof_reader') : NULL,
    );
  }

  public function customerPreview(Request $request, string $website_request, string $direction): Response {
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    $row = $customer ? $this->portal->ownedWebsiteRequest((int) $customer['id'], $website_request) : NULL;
    if ($row && FreshProofBinding::isManagedReadOnly($this->database, $row)) return $this->managedCustomerResponse($request, $row, $direction);
    if (!$row || !in_array($row['proof_review_status'], ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) {
      return new Response('Proof not found.', 404);
    }
    return $this->artifactResponse($row, $direction);
  }

  public function adminPreview(Request $request, int $website_request, string $direction): Response {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $website_request)->execute()->fetchAssoc();
    return $row ? $this->artifactResponse($row, $direction, '') : new Response('Proof not found.', 404);
  }

  /** Serves an owner-review asset using the same frozen manifest as the customer view. */
  public function adminAsset(Request $request, int $website_request, string $direction, string $asset_path): Response {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $website_request)->execute()->fetchAssoc();
    return $row ? $this->assetResponse($row, $direction, $asset_path) : new Response('Proof asset not found.', 404);
  }

  public function publicShare(Request $request, string $website_request, string $signature): JsonResponse {
    if ($this->managedPublicRequest($website_request)) {
      return $this->managedReadDenied(TRUE);
    }
    $share = $this->portal->publicWebsiteProofShare($website_request, $signature);
    $response = new JsonResponse($share ? ['ok' => TRUE, 'proof_share' => $share] : ['ok' => FALSE, 'error' => 'proof_share_not_found'], $share ? 200 : 404);
    return $this->securePublicResponse($response);
  }

  public function publicPreview(Request $request, string $website_request, string $signature, string $direction): Response {
    if ($this->managedPublicRequest($website_request)) return $this->managedReadDenied();
    $row = $this->portal->sharedWebsiteRequest($website_request, $signature);
    return $row ? $this->artifactResponse($row, $direction, $signature) : $this->securePublicResponse(new Response('Proof not found.', 404));
  }

  /** Serves one frozen proof image only to the customer who owns the request. */
  public function customerAsset(Request $request, string $website_request, string $direction, string $asset_path): Response {
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    $row = $customer ? $this->portal->ownedWebsiteRequest((int) $customer['id'], $website_request) : NULL;
    if ($row && FreshProofBinding::isManagedReadOnly($this->database, $row)) return $this->managedCustomerResponse($request, $row, $direction, $asset_path);
    if (!$row || !in_array($row['proof_review_status'], ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) {
      return new Response('Proof asset not found.', 404);
    }
    return $this->assetResponse($row, $direction, $asset_path);
  }

  /** Serves one frozen asset for an explicitly enabled, revocable proof share. */
  public function publicAsset(Request $request, string $website_request, string $signature, string $direction, string $asset_path): Response {
    if ($this->managedPublicRequest($website_request)) return $this->managedReadDenied();
    $row = $this->portal->sharedWebsiteRequest($website_request, $signature);
    return $row ? $this->assetResponse($row, $direction, $asset_path) : $this->securePublicResponse(new Response('Proof asset not found.', 404));
  }

  public function uploadAsset(Request $request, string $website_request): JsonResponse {
    if ($this->database->inTransaction()) throw new \LogicException('Asset upload requires its own root transaction.');
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    $row = $customer ? $this->portal->ownedWebsiteRequest((int) $customer['id'], $website_request) : NULL;
    if (!$row) return new JsonResponse(['ok' => FALSE, 'error' => 'website_request_not_found', 'message' => 'Website request not found.'], 404);
    if (!$request->request->getBoolean('ownership_confirmed')) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'ownership_required', 'message' => 'Confirm that you own or may share this reference file.'], 422);
    }
    $roleInput = strtolower(trim((string) $request->request->get('asset_role', '')));
    $role = $roleInput === '' ? 'other' : CharacterAssetService::normalizeRole($roleInput);
    if ($roleInput !== '' && !in_array($roleInput, CharacterAssetService::allowedRoles(), TRUE)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'asset_role', 'message' => 'Choose a supported reference role.'], 422);
    }
    $isLikeness = in_array($role, CharacterAssetService::requiredLikenessRoles(), TRUE);
    $subjectPermission = $request->request->getBoolean('subject_permission_confirmed');
    $aiTransformationConsent = $request->request->getBoolean('ai_transformation_consent') || $request->request->getBoolean('ai_use_consent');
    if ($isLikeness && (!$subjectPermission || !$aiTransformationConsent)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'likeness_consent_required', 'message' => 'Confirm that you are the person shown or have permission, and allow FAMtastic to transform the image into project artwork.'], 422);
    }
    $upload = $request->files->get('asset');
    if (!$upload || !$upload->isValid()) return new JsonResponse(['ok' => FALSE, 'error' => 'asset_required', 'message' => 'Choose a valid reference file.'], 422);
    $size = (int) $upload->getSize();
    if ($size < 1 || $size > 10485760) return new JsonResponse(['ok' => FALSE, 'error' => 'asset_size', 'message' => 'Reference files must be 10 MB or smaller.'], 422);
    $bytes = (string) file_get_contents($upload->getPathname());
    $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes));
    $allowed = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'];
    if (!in_array($mime, $allowed, TRUE)) return new JsonResponse(['ok' => FALSE, 'error' => 'asset_type', 'message' => 'Upload a PNG, JPEG, WebP, or PDF reference.'], 422);
    $sha = hash('sha256', $bytes);
    $existing = $this->database->select('famtastic_request_asset', 'a')->fields('a')
      ->condition('website_request_id', (int) $row['id'])->condition('customer_id', (int) $customer['id'])->condition('sha256', $sha)->execute()->fetchAssoc();
    // This first read is advisory only: avoid preparing an unnecessary private
    // file for ordinary retries. Every result is revalidated under the parent
    // request lock, including a duplicate and an empty asset set.
    $preparedUri = NULL;
    $original = mb_substr(basename((string) $upload->getClientOriginalName()), 0, 255);
    if (!$existing) {
      $directory = 'private://famtastic-request-assets/' . preg_replace('/[^0-9a-f-]/', '', $website_request);
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        return new JsonResponse(['ok' => FALSE, 'error' => 'private_storage_unavailable', 'message' => 'Secure uploads are temporarily unavailable.'], 503);
      }
      $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original) ?: 'reference';
      // FileRepository::writeData also persists a permanent entity. Preparation
      // here is filesystem-only; all managed metadata belongs to the TX below.
      $preparedUri = $this->fileSystem->saveData($bytes, $directory . '/' . bin2hex(random_bytes(10)) . '-' . $safeName, FileSystemInterface::EXISTS_ERROR);
      if (!is_string($preparedUri) || !str_starts_with($preparedUri, $directory . '/')) throw new \RuntimeException('Private reference preparation failed.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->portal->ownedWebsiteRequest((int) $customer['id'], $website_request, TRUE);
      if (!$row) {
        $transaction->commitOrRelease();
        return new JsonResponse(['ok' => FALSE, 'error' => 'website_request_not_found', 'message' => 'Website request not found.'], 404);
      }
      $existing = $this->database->select('famtastic_request_asset', 'a')->fields('a')
        ->condition('website_request_id', (int) $row['id'])->condition('customer_id', (int) $customer['id'])
        ->condition('sha256', $sha)->forUpdate()->execute()->fetchAssoc();
      if ($existing) {
        $transaction->commitOrRelease();
        if ($existing['status'] !== 'active') return new JsonResponse(['ok' => FALSE, 'error' => 'reference_inactive', 'message' => 'This reference was withdrawn and remains inactive. Uploading it again does not restore permission to use it.'], 409);
        return new JsonResponse(['ok' => TRUE, 'duplicate' => TRUE, 'asset' => $this->assetPayload($existing)]);
      }
      if (!$preparedUri) throw new \RuntimeException('Reference changed during upload; retry.');
      $file = $this->entities->getStorage('file')->create(['uri' => $preparedUri, 'uid' => (int) $this->account->id()]);
      $file->setPermanent();
      $file->save();
      $now = time();
      $id = (int) $this->database->insert('famtastic_request_asset')->fields([
      'public_id' => $this->uuid->generate(), 'website_request_id' => (int) $row['id'], 'customer_id' => (int) $customer['id'],
      'file_id' => (int) $file->id(), 'original_name' => $original, 'mime_type' => $mime,
      'size_bytes' => $size, 'sha256' => $sha, 'ownership_confirmed' => 1,
      'kind' => $role === 'other' ? 'reference' : $role, 'role' => $role,
      'ai_use_consent' => $aiTransformationConsent ? 1 : 0,
      'likeness_consent_version' => $isLikeness ? mb_substr(trim((string) $request->request->get('likeness_consent_version', 'likeness-v1')), 0, 64) : '',
      'likeness_consent_at' => $isLikeness ? $now : NULL,
      'subject_permission_confirmed' => $subjectPermission ? 1 : 0,
      'ai_transformation_consent' => $aiTransformationConsent ? 1 : 0,
      'status' => 'active', 'created' => $now, 'changed' => $now,
      ])->execute();
      $this->fileUsage->add($file, 'famtastic_pipeline', 'website_request', (int) $row['id']);
      $asset = $this->database->select('famtastic_request_asset', 'a')->fields('a')->condition('id', $id)->execute()->fetchAssoc();
      $transaction->commitOrRelease();
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
    // A losing race may leave only an unreferenced private file; it cannot grant
    // asset-use authority. No destructive filesystem cleanup in this mutation.
    $this->portal->refreshSelectedWebsiteRequest((int) $customer['id'], $website_request);
    return new JsonResponse(['ok' => TRUE, 'duplicate' => FALSE, 'asset' => $this->assetPayload($asset)], 201);
  }

  /** Exact credited bytes, never legacy rewriting, public shares or read grants. */
  private function managedCustomerResponse(Request $request, array $row, string $direction, ?string $assetPath = NULL): Response {
    if ($this->managedReader === NULL) return $this->managedReadDenied();
    try {
      // Account comes from Drupal authentication, not route/body/customer IDs.
      // The reader separately requires an exact committed managed QA release.
      $context = $this->managedReader->context((int) $row['id'], $this->account, 'customer');
      $content = $assetPath === NULL
        ? $this->managedReader->readRole($context, $direction, 'html')
        : $this->managedReader->readRelativeAsset($context, $direction, 'assets/' . $assetPath);
      if ($assetPath === NULL && !str_ends_with($request->getPathInfo(), '/' . $direction . '/index.html')) {
        // A file-shaped URL resolves the retained relative assets correctly.
        // Existing links can enter here, but customer email still links /portal.
        $location = '/web/api/customer/website-requests/' . rawurlencode((string) $row['public_id'])
          . '/proofs/' . rawurlencode($direction) . '/index.html';
        $response = new Response('', 302, ['Location' => $location]);
      }
      else $response = new Response($content['bytes'], 200, ['Content-Type' => $content['media_type']]);
      $response->headers->set('X-Content-Type-Options', 'nosniff');
      $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
      return $this->securePublicResponse($response);
    }
    catch (\Throwable) { return $this->managedReadDenied(); }
  }

  private function artifactResponse(array $row, string $direction, ?string $shareSignature = NULL): Response {
    if (FreshProofBinding::isManagedReadOnly($this->database, $row)) return $this->managedReadDenied();
    $direction = strtolower($direction);
    if (!in_array($direction, ['a', 'b', 'c', 'd', 'e', 'f'], TRUE) || empty($row['proof_campaign_id'])) return new Response('Proof not found.', 404);
    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $row['proof_campaign_id'])->condition('direction_id', $direction)->range(0, 1)->execute();
    $variant = $ids ? $this->entities->getStorage('proof_variant')->load(reset($ids)) : NULL;
    if (!$variant) return new Response('Proof not found.', 404);
    $stored = (string) $variant->get('artifact_path')->value;
    $path = str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . ltrim($stored, '/');
    $real = realpath($path);
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) return new Response('Proof artifact unavailable.', 404);
    $html = $this->rewriteArtifactAssetUrls((string) file_get_contents($real), $variant, (int) $row['id'], (string) $row['public_id'], $direction, $shareSignature);
    $response = new Response(\Drupal\famtastic_pipeline\Service\CreatorCredit::present($html), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
    $response->headers->addCacheControlDirective('no-store', TRUE);
    return $response;
  }

  public function withdrawAsset(Request $request, string $website_request, string $asset): JsonResponse {
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    if (!$customer) return new JsonResponse(['ok' => FALSE, 'error' => 'authentication_required'], 401);
    try { $this->portal->withdrawWebsiteRequestAsset((int) $customer['id'], $website_request, $asset); }
    catch (\InvalidArgumentException) { return new JsonResponse(['ok' => FALSE, 'error' => 'reference_unavailable', 'message' => 'Reference not found.'], 404); }
    return new JsonResponse(['ok' => TRUE, 'asset_id' => $asset, 'status' => 'withdrawn']);
  }

  /** Safely maps declared callback asset paths into a scoped controller route. */
  private function rewriteArtifactAssetUrls(string $html, object $variant, int $requestId, string $publicId, string $direction, ?string $shareSignature): string {
    try {
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE, flags: JSON_THROW_ON_ERROR);
      $assets = ProofAssetContract::normalizeStoredManifest($dna['asset_manifest'] ?? []);
    }
    catch (\Throwable) {
      return $html;
    }
    foreach ($assets as $asset) {
      $encoded = implode('/', array_map('rawurlencode', explode('/', $asset['relative_path'])));
      $url = $shareSignature === ''
        ? '/web/admin/famtastic/website-request/' . $requestId . '/proof/' . rawurlencode($direction) . '/assets/' . $encoded
        : ($shareSignature === NULL
          ? '/web/api/customer/website-requests/' . rawurlencode($publicId) . '/proofs/' . rawurlencode($direction) . '/assets/' . $encoded
          : '/web/api/proof-shares/' . rawurlencode($publicId) . '/' . rawurlencode($shareSignature) . '/proofs/' . rawurlencode($direction) . '/assets/' . $encoded);
      $html = str_replace('assets/' . $asset['relative_path'], $url, $html);
    }
    return $html;
  }

  /** Reads one declared asset without granting filesystem-path access. */
  private function assetResponse(array $row, string $direction, string $assetPath): Response {
    if (FreshProofBinding::isManagedReadOnly($this->database, $row)) return $this->managedReadDenied();
    $direction = strtolower($direction);
    if (!in_array($direction, ['a', 'b', 'c', 'd', 'e', 'f'], TRUE) || empty($row['proof_campaign_id'])) {
      return new Response('Proof asset not found.', 404);
    }
    try {
      $relativePath = ProofAssetContract::normalizeRelativePath($assetPath);
      $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
        ->condition('campaign_id', (int) $row['proof_campaign_id'])->condition('direction_id', $direction)->range(0, 1)->execute();
      $variant = $ids ? $this->entities->getStorage('proof_variant')->load(reset($ids)) : NULL;
      if (!$variant) return new Response('Proof asset not found.', 404);
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE, flags: JSON_THROW_ON_ERROR);
      $manifest = ProofAssetContract::normalizeStoredManifest($dna['asset_manifest'] ?? []);
      $asset = NULL;
      foreach ($manifest as $candidate) {
        if (hash_equals($candidate['relative_path'], $relativePath)) {
          $asset = $candidate;
          break;
        }
      }
      if (!$asset) return new Response('Proof asset not found.', 404);
      $absolute = dirname(\Drupal::root()) . '/' . ltrim($asset['artifact_path'], '/');
      $real = realpath($absolute);
      $root = realpath(\Drupal::root() . '/proofs');
      if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real) || !hash_equals($asset['sha256'], (string) hash_file('sha256', $real))) {
        return new Response('Proof asset unavailable.', 404);
      }
      $response = new Response((string) file_get_contents($real), 200, ['Content-Type' => $asset['media_type']]);
      $response->setPrivate();
      $response->setMaxAge(0);
      $response->headers->set('Cache-Control', 'no-store, private');
      $response->headers->set('X-Content-Type-Options', 'nosniff');
      $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
      $response->headers->set('Referrer-Policy', 'no-referrer');
      return $response;
    }
    catch (\Throwable) {
      return new Response('Proof asset not found.', 404);
    }
  }

  /** Deny before the share helper can inspect legacy DNA or filesystem paths. */
  private function managedPublicRequest(string $publicId): bool {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')
      ->condition('public_id', $publicId)->execute()->fetchAssoc();
    return $row && FreshProofBinding::isManagedReadOnly($this->database, $row);
  }

  private function managedReadDenied(bool $json = FALSE): Response {
    $response = $json
      ? new JsonResponse(['ok' => FALSE, 'error' => 'proof_share_not_found'], 404)
      : new Response('Proof not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $this->securePublicResponse($response);
  }

  private function securePublicResponse(Response $response): Response {
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    return $response;
  }

  private function assetPayload(array $row): array {
    return [
      'public_id' => (string) $row['public_id'], 'kind' => (string) $row['kind'], 'name' => (string) $row['original_name'],
      'mime_type' => (string) $row['mime_type'], 'size_bytes' => (int) $row['size_bytes'],
      'ownership_confirmed' => (bool) $row['ownership_confirmed'], 'ai_use_consent' => (bool) $row['ai_use_consent'],
      'role' => (string) ($row['role'] ?? $row['kind'] ?? 'other'),
      'likeness_consent_version' => (string) ($row['likeness_consent_version'] ?? ''),
      'likeness_consent_at' => !empty($row['likeness_consent_at']) ? (int) $row['likeness_consent_at'] : NULL,
      'subject_permission_confirmed' => (bool) ($row['subject_permission_confirmed'] ?? FALSE),
      'ai_transformation_consent' => (bool) ($row['ai_transformation_consent'] ?? $row['ai_use_consent'] ?? FALSE),
    ];
  }

}
