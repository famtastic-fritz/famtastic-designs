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
    $stored = (string) $variant->get('artifact_path')->value;
    $path = str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . ltrim($stored, '/');
    $real = realpath($path);
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
      return $this->secure(new Response('Proof artifact unavailable.', 404));
    }
    $response = new Response((string) file_get_contents($real), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
    return $this->secure($response);
  }

  private function secure(Response $response): Response {
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    return $response;
  }

}
