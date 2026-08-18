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
use Drupal\file\FileRepositoryInterface;
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
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
    private readonly UuidInterface $uuid,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('famtastic_pipeline.customer_portal'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('uuid'),
    );
  }

  public function customerPreview(Request $request, string $website_request, string $direction): Response {
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    $row = $customer ? $this->portal->ownedWebsiteRequest((int) $customer['id'], $website_request) : NULL;
    if (!$row || !in_array($row['proof_review_status'], ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) {
      return new Response('Proof not found.', 404);
    }
    return $this->artifactResponse($row, $direction);
  }

  public function adminPreview(Request $request, int $website_request, string $direction): Response {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $website_request)->execute()->fetchAssoc();
    return $row ? $this->artifactResponse($row, $direction) : new Response('Proof not found.', 404);
  }

  public function uploadAsset(Request $request, string $website_request): JsonResponse {
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    $row = $customer ? $this->portal->ownedWebsiteRequest((int) $customer['id'], $website_request) : NULL;
    if (!$row) return new JsonResponse(['ok' => FALSE, 'error' => 'website_request_not_found', 'message' => 'Website request not found.'], 404);
    if (!$request->request->getBoolean('ownership_confirmed')) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'ownership_required', 'message' => 'Confirm that you own or may share this reference file.'], 422);
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
      ->condition('website_request_id', (int) $row['id'])->condition('sha256', $sha)->execute()->fetchAssoc();
    if ($existing) return new JsonResponse(['ok' => TRUE, 'duplicate' => TRUE, 'asset' => $this->assetPayload($existing)]);
    $directory = 'private://famtastic-request-assets/' . preg_replace('/[^0-9a-f-]/', '', $website_request);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'private_storage_unavailable', 'message' => 'Secure uploads are temporarily unavailable.'], 503);
    }
    $original = mb_substr(basename((string) $upload->getClientOriginalName()), 0, 255);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original) ?: 'reference';
    $file = $this->fileRepository->writeData($bytes, $directory . '/' . bin2hex(random_bytes(10)) . '-' . $safeName, FileSystemInterface::EXISTS_ERROR);
    $file->setPermanent();
    $file->save();
    $this->fileUsage->add($file, 'famtastic_pipeline', 'website_request', (int) $row['id']);
    $now = time();
    $id = (int) $this->database->insert('famtastic_request_asset')->fields([
      'public_id' => $this->uuid->generate(), 'website_request_id' => (int) $row['id'], 'customer_id' => (int) $customer['id'],
      'file_id' => (int) $file->id(), 'kind' => 'reference', 'original_name' => $original, 'mime_type' => $mime,
      'size_bytes' => $size, 'sha256' => $sha, 'ownership_confirmed' => 1,
      'ai_use_consent' => $request->request->getBoolean('ai_use_consent') ? 1 : 0, 'status' => 'active', 'created' => $now, 'changed' => $now,
    ])->execute();
    $asset = $this->database->select('famtastic_request_asset', 'a')->fields('a')->condition('id', $id)->execute()->fetchAssoc();
    return new JsonResponse(['ok' => TRUE, 'duplicate' => FALSE, 'asset' => $this->assetPayload($asset)], 201);
  }

  private function artifactResponse(array $row, string $direction): Response {
    $direction = strtolower($direction);
    if (!in_array($direction, ['a', 'b', 'c'], TRUE) || empty($row['proof_campaign_id'])) return new Response('Proof not found.', 404);
    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $row['proof_campaign_id'])->condition('direction_id', $direction)->range(0, 1)->execute();
    $variant = $ids ? $this->entities->getStorage('proof_variant')->load(reset($ids)) : NULL;
    if (!$variant) return new Response('Proof not found.', 404);
    $stored = (string) $variant->get('artifact_path')->value;
    $path = str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . ltrim($stored, '/');
    $real = realpath($path);
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) return new Response('Proof artifact unavailable.', 404);
    $response = new Response((string) file_get_contents($real), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
    $response->headers->addCacheControlDirective('no-store', TRUE);
    return $response;
  }

  private function assetPayload(array $row): array {
    return [
      'public_id' => (string) $row['public_id'], 'kind' => (string) $row['kind'], 'name' => (string) $row['original_name'],
      'mime_type' => (string) $row['mime_type'], 'size_bytes' => (int) $row['size_bytes'],
      'ownership_confirmed' => (bool) $row['ownership_confirmed'], 'ai_use_consent' => (bool) $row['ai_use_consent'],
    ];
  }

}
