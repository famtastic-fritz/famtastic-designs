<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/** Serves static, read-only industry preview candidates behind a local flag. */
final class AcquisitionIndustryPreviewController extends ControllerBase {

  private const INDUSTRIES = [
    'mobile-detailing', 'fitness-meal-prep', 'photography-media',
    'baking-catering', 'home-services', 'events-rentals',
    'pet-services', 'consulting-tutoring', 'handcrafted-boutiques',
  ];

  private const COMMON_ASSETS = [
    'famtastic-designs-logo-v1.png' => 'marketing/campaigns/acquisition-199/assets/famtastic-designs-logo-v1.png',
    'connect-qr.png' => 'marketing/campaigns/acquisition-199/assets/connect-qr.png',
    'metropolis-regular.woff2' => 'marketing/campaigns/acquisition-199/generic-review/assets/metropolis-regular.woff2',
    'metropolis-bold.woff2' => 'marketing/campaigns/acquisition-199/generic-review/assets/metropolis-bold.woff2',
    'lora-bold.woff2' => 'marketing/campaigns/acquisition-199/generic-review/assets/lora-bold.woff2',
    'lora-italic.woff2' => 'marketing/campaigns/acquisition-199/generic-review/assets/lora-italic.woff2',
  ];

  /** Read-only candidate HTML. No invitation, identity, or persistence input. */
  public function preview(string $industry, string $view): Response {
    if (!$this->enabled() || !in_array($industry, self::INDUSTRIES, TRUE) || !in_array($view, ['email', 'lab'], TRUE)) {
      return $this->secure(new Response('Preview unavailable.', 404));
    }

    $html = $this->readIndustryFile($industry, $view . '.html');
    if ($html === NULL) {
      return $this->secure(new Response('Preview unavailable.', 404));
    }

    $html = $this->rewriteLocalUrls($html, $industry);
    $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'none'; font-src 'self' data:; form-action 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
    return $this->secure($response);
  }

  /** Read-only, filename-allowlisted candidate and frozen brand assets. */
  public function asset(string $industry, string $asset): Response {
    if (!$this->enabled() || !in_array($industry, self::INDUSTRIES, TRUE) || preg_match('/^[a-z0-9_-]+\.(?:css|js|svg|png|jpe?g|webp|woff2)$/D', $asset) !== 1) {
      return $this->secure(new Response('', 404));
    }

    $bytes = $this->readIndustryFile($industry, $asset);
    if ($bytes === NULL) {
      return $this->secure(new Response('', 404));
    }

    if (str_ends_with($asset, '.css')) {
      $bytes = $this->rewriteLocalUrls($bytes, $industry);
      $contentType = 'text/css; charset=UTF-8';
    }
    elseif (str_ends_with($asset, '.js')) {
      $contentType = 'application/javascript; charset=UTF-8';
    }
    else {
      $contentType = $this->assetContentType($asset);
    }

    $response = new Response($bytes, 200, ['Content-Type' => $contentType]);
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'none'; font-src 'self' data:; form-action 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
    return $this->secure($response);
  }

  /** Frozen brand media only; callers cannot choose a source path. */
  public function commonAsset(string $asset): Response {
    if (!$this->enabled() || !isset(self::COMMON_ASSETS[$asset])) {
      return $this->secure(new Response('', 404));
    }
    $relative = self::COMMON_ASSETS[$asset];
    $root = realpath(dirname(__DIR__, 7));
    $path = $root ? realpath($root . '/' . $relative) : FALSE;
    if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || is_link($root . '/' . $relative) || !is_file($path) || filesize($path) > 8 * 1024 * 1024) {
      return $this->secure(new Response('', 404));
    }

    $bytes = file_get_contents($path);
    if ($bytes === FALSE) {
      return $this->secure(new Response('', 404));
    }
    $response = new Response($bytes, 200, ['Content-Type' => $this->assetContentType($asset)]);
    $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'none'; script-src 'none'; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
    return $this->secure($response);
  }

  private function enabled(): bool {
    return Settings::get('famtastic_acquisition_industry_previews_enabled', FALSE) === TRUE;
  }

  private function readIndustryFile(string $industry, string $filename): ?string {
    if (!in_array($industry, self::INDUSTRIES, TRUE) || preg_match('/^[a-z0-9_-]+\.(?:html|css|js|svg|png|jpe?g|webp|woff2)$/D', $filename) !== 1) {
      return NULL;
    }
    $repo = realpath(dirname(__DIR__, 7));
    $base = $repo ? realpath($repo . '/marketing/campaigns/acquisition-199/industry-previews/' . $industry) : FALSE;
    $candidate = $base ? $base . '/' . $filename : '';
    $path = $candidate !== '' ? realpath($candidate) : FALSE;
    if (!$repo || !$base || !str_starts_with($base . DIRECTORY_SEPARATOR, $repo . '/marketing/campaigns/acquisition-199/industry-previews/') || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || is_link($candidate) || !is_file($path) || filesize($path) > 8 * 1024 * 1024) {
      return NULL;
    }
    $bytes = file_get_contents($path);
    return $bytes === FALSE ? NULL : $bytes;
  }

  private function rewriteLocalUrls(string $content, string $industry): string {
    $content = preg_replace_callback('/\b(src|href)=("|\')([^"\']+)(\2)/i', function (array $match) use ($industry): string {
      $attribute = $match[1];
      $url = $match[3];
      $route = $this->candidateUrl($industry, $url);
      if ($route === NULL) {
        return $match[0];
      }
      return $attribute . '=' . $match[2] . $route . $match[4];
    }, $content) ?? $content;

    return preg_replace_callback('/url\((\s*["\']?)([^)"\']+)(["\']?\s*)\)/i', function (array $match) use ($industry): string {
      $url = trim($match[2]);
      $url = $this->candidateUrl($industry, $url) ?? $url;
      return 'url(' . $match[1] . $url . $match[3] . ')';
    }, $content) ?? $content;
  }

  /** Build rewritten links through Drupal so a subdirectory install is honored. */
  private function candidateUrl(string $industry, string $url): ?string {
    if ($url === 'lab.html') {
      return Url::fromRoute('famtastic_pipeline.acquisition_industry_preview', ['industry' => $industry, 'view' => 'lab'])->toString();
    }
    if ($url === 'email.html') {
      return Url::fromRoute('famtastic_pipeline.acquisition_industry_preview', ['industry' => $industry, 'view' => 'email'])->toString();
    }
    if (preg_match('#^(?:(?:\.\./){1,3})?assets/(famtastic-designs-logo-v1\.png|connect-qr\.png)$#D', $url, $assetMatch)
      || preg_match('#^(?:\.\./){1,4}generic-review/assets/(famtastic-designs-logo-v1\.png|connect-qr\.png|metropolis-(?:regular|bold)\.woff2|lora-(?:bold|italic)\.woff2)$#D', $url, $assetMatch)) {
      return Url::fromRoute('famtastic_pipeline.acquisition_industry_preview_common_asset', ['asset' => $assetMatch[1]])->toString();
    }
    if (preg_match('#^assets/(metropolis-(?:regular|bold)\.woff2|lora-(?:bold|italic)\.woff2)$#D', $url, $assetMatch)) {
      return Url::fromRoute('famtastic_pipeline.acquisition_industry_preview_common_asset', ['asset' => $assetMatch[1]])->toString();
    }
    if (preg_match('/^[a-z0-9_-]+\.(?:css|js|svg|png|jpe?g|webp|woff2)$/D', $url) === 1) {
      return Url::fromRoute('famtastic_pipeline.acquisition_industry_preview_asset', ['industry' => $industry, 'asset' => $url])->toString();
    }
    return NULL;
  }

  private function assetContentType(string $filename): string {
    return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
      'svg' => 'image/svg+xml',
      'png' => 'image/png',
      'jpg', 'jpeg' => 'image/jpeg',
      'webp' => 'image/webp',
      'woff2' => 'font/woff2',
      default => 'application/octet-stream',
    };
  }

  private function secure(Response $response): Response {
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
