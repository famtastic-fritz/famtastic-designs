<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Serves the minimal, signed public concept room for an approved lead. */
final class PublicPreviewController extends ControllerBase {

  public function __construct(
    private readonly PublicPreviewDeliveryService $previews,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('famtastic_pipeline.public_preview_deliveries'),
    );
  }

  public function share(string $preview_delivery, string $signature): JsonResponse {
    $share = $this->previews->publicShare($preview_delivery, $signature);
    return $this->secure(new JsonResponse($share ? ['ok' => TRUE, 'preview_delivery' => $share] : ['ok' => FALSE, 'error' => 'preview_not_found'], $share ? 200 : 404));
  }

  public function proof(string $preview_delivery, string $signature, string $direction): Response {
    $variant = $this->previews->publicVariant($preview_delivery, $signature, $direction);
    if (!$variant) return $this->secure(new Response('Proof not found.', 404));
    $stored = (string) $variant['artifact_path'];
    $path = str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . ltrim($stored, '/');
    $real = realpath($path);
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real) || !hash_equals((string) $variant['artifact_hash'], (string) hash_file('sha256', $real))) {
      return $this->secure(new Response('Proof artifact unavailable.', 404));
    }
    $html = (string) file_get_contents($real);
    // Legacy assetless proofs remain byte-for-byte as stored. Asset-bearing
    // proofs receive only a response-time base element; the stored HTML hash
    // and immutable Build DNA evidence are never rewritten.
    if (!empty($variant['assets'])) {
      $html = $this->injectAssetBase($html, $this->previews->publicAssetBaseUrl($preview_delivery, $signature, $direction));
    }
    $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'self'; base-uri 'self'; form-action 'none'");
    return $this->secure($response);
  }

  /** Serves one frozen image only through the current signed room link. */
  public function asset(string $preview_delivery, string $signature, string $direction, string $asset_path): Response {
    $asset = $this->previews->publicAsset($preview_delivery, $signature, $direction, $asset_path);
    if (!$asset) {
      return $this->secure(new Response('Proof asset not found.', 404));
    }
    $bytes = (string) $asset['bytes'];
    $response = new Response($bytes, 200, [
      'Content-Type' => (string) $asset['media_type'],
      'Content-Length' => (string) strlen($bytes),
      'X-Content-Type-Options' => 'nosniff',
    ]);
    return $this->secure($response);
  }

  /** Adds a signed asset base without altering the stored proof artifact. */
  private function injectAssetBase(string $html, string $base): string {
    $tag = '<base href="' . htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    if (preg_match('/<head\b[^>]*>/i', $html) === 1) {
      return (string) preg_replace('/<head\b[^>]*>/i', '$0' . $tag, $html, 1);
    }
    if (preg_match('/<html\b[^>]*>/i', $html) === 1) {
      return (string) preg_replace('/<html\b[^>]*>/i', '$0<head>' . $tag . '</head>', $html, 1);
    }
    return '<!doctype html><html><head>' . $tag . '</head><body>' . $html . '</body></html>';
  }

  private function secure(Response $response): Response {
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
