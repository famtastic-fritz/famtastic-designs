<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\FullSiteReviewService;
use Drupal\famtastic_pipeline\Service\FullSiteReviewPackage;
use Drupal\famtastic_pipeline\Service\FullSiteReviewRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/** Uses the existing Drupal session; no bearer links, passwords or public room. */
final class FullSiteReviewController implements ContainerInjectionInterface {
  public function __construct(private readonly AccountProxyInterface $account, private readonly CustomerPortalService $portal, private readonly FullSiteReviewService $reviews) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('current_user'), $container->get('famtastic_pipeline.customer_portal'), $container->get('famtastic_pipeline.full_site_review'));
  }

  public function file(string $website_request, string $artifact_path, string $artifact_part_2 = '', string $artifact_part_3 = ''): Response {
    $read = function (string $path) use ($website_request): array {
      $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
      if (!$customer) throw new \RuntimeException('Review not found.');
      return $this->reviews->read((int) $customer['id'], $website_request, $path);
    };
    return $this->response($this->artifactPath($artifact_path, $artifact_part_2, $artifact_part_3), $read);
  }

  public function adminFile(int $website_request, string $artifact_path, string $artifact_part_2 = '', string $artifact_part_3 = ''): Response {
    $read = function (string $path) use ($website_request): array {
      if (!$this->account->isAuthenticated() || !$this->account->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('Review not found.');
      return $this->reviews->readForStaff($website_request, $path);
    };
    return $this->response($this->artifactPath($artifact_path, $artifact_part_2, $artifact_part_3), $read, static fn(string $path): string => FullSiteReviewPackage::staffUrl($website_request, $path));
  }

  /** Drupal's database route provider requires one placeholder per path part. */
  private function artifactPath(string $first, string $second, string $third): string {
    return $first . ($second !== '' ? '/' . $second : '') . ($third !== '' ? '/' . $third : '');
  }

  private function response(string $artifactPath, callable $read, ?callable $url = NULL): Response {
    $nonce = base64_encode(random_bytes(24));
    try {
      $result = $read($artifactPath);
      $body = $result['bytes'];
      if ($result['file']['media_type'] === 'text/html') {
        $version = $result['manifest_sha256'];
        $bytes = static function (string $path) use ($read, $version): string {
          $resource = $read($path);
          if (!hash_equals($version, $resource['manifest_sha256'])) throw new \RuntimeException('Review version changed while rendering.');
          return $resource['bytes'];
        };
        $body = FullSiteReviewRenderer::render($body, $artifactPath, $result['request_public_id'], $result['manifest']['files'], $bytes, $nonce, $url);
      }
      $type = $result['file']['media_type'];
      $response = new Response($body, 200, ['Content-Type' => $type . (str_starts_with($type, 'text/') ? '; charset=UTF-8' : '')]);
    }
    catch (\Throwable) { $response = new Response('Review not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']); }
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('Content-Security-Policy', FullSiteReviewRenderer::csp($nonce));
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    return $response;
  }
}
