<?php

declare(strict_types=1);

/**
 * Loopback-only CUA fixture. No Drupal account or production artifact is touched.
 * FULL_SITE_REVIEW_FIXTURE_PACKAGE and FULL_SITE_REVIEW_FIXTURE_MANIFEST must
 * explicitly name the local package and its manifest. Start with PHP -S on
 * 127.0.0.1 and this router; visit /fixture/owner, /fixture/other or /fixture/out.
 */
if (PHP_SAPI !== 'cli-server' || ($_SERVER['SERVER_ADDR'] ?? $_SERVER['SERVER_NAME'] ?? '') !== '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
  http_response_code(404); exit;
}

require_once dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/FullSiteReviewPackage.php';
require_once dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/FullSiteReviewRenderer.php';

use Drupal\famtastic_pipeline\Service\FullSiteReviewPackage;
use Drupal\famtastic_pipeline\Service\FullSiteReviewRenderer;

const FIXTURE_PUBLIC_ID = '11111111-2222-4333-8444-555555555555';
session_name('FULL_SITE_REVIEW_FIXTURE');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => TRUE, 'samesite' => 'Lax']);
session_start();
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($requestPath, ['/fixture/owner', '/fixture/other', '/fixture/out'], TRUE)) {
  $_SESSION['fixture_customer_id'] = $requestPath === '/fixture/owner' ? 91 : ($requestPath === '/fixture/other' ? 999 : 0);
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!doctype html><title>Synthetic review session</title><h1>Synthetic review session</h1><p>This loopback fixture does not sign in to a real account.</p><a href="' . FullSiteReviewPackage::url(FIXTURE_PUBLIC_ID, 'index.html') . '">Open fixture website</a>';
  exit;
}
$nonce = base64_encode(random_bytes(24));
header('Content-Security-Policy: ' . FullSiteReviewRenderer::csp($nonce));
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
try {
  $prefix = '/web/api/customer/website-requests/' . FIXTURE_PUBLIC_ID . '/full-site/';
  if (!is_string($requestPath) || !str_starts_with($requestPath, $prefix)) throw new RuntimeException('Unknown route.');
  $path = FullSiteReviewPackage::path(substr($requestPath, strlen($prefix)));
  $root = (string) getenv('FULL_SITE_REVIEW_FIXTURE_PACKAGE');
  $manifestFile = (string) getenv('FULL_SITE_REVIEW_FIXTURE_MANIFEST');
  if (!str_starts_with($root, '/') || !str_starts_with($manifestFile, '/') || is_link($manifestFile)) throw new RuntimeException('Explicit local package required.');
  $manifest = FullSiteReviewPackage::normalize(json_decode(file_get_contents($manifestFile), TRUE, 512, JSON_THROW_ON_ERROR));
  $files = array_column($manifest['files'], NULL, 'path');
  $read = static function (string $path) use ($root, $files): string {
    if (($_SESSION['fixture_customer_id'] ?? 0) !== 91 || !isset($files[$path])) throw new RuntimeException('Not the fixture owner.');
    return FullSiteReviewPackage::read($root, $files[$path]);
  };
  $body = $read($path);
  $type = $files[$path]['media_type'];
  if ($type === 'text/html') {
    $body = FullSiteReviewRenderer::render($body, $path, FIXTURE_PUBLIC_ID, $manifest['files'], $read, $nonce);
    // Observable fixture-only assertions; no real secret is accessed or stored.
    $assertions = '<script nonce="' . $nonce . '">try { void localStorage.length; document.documentElement.dataset.fixtureStorage="accessible"; } catch { document.documentElement.dataset.fixtureStorage="blocked"; } try { void document.cookie; document.documentElement.dataset.fixtureCookies="accessible"; } catch { document.documentElement.dataset.fixtureCookies="blocked"; } const fixtureOutput=document.createElement("p"); fixtureOutput.textContent="Synthetic isolation check: local storage "+document.documentElement.dataset.fixtureStorage+"; cookies "+document.documentElement.dataset.fixtureCookies+"."; document.body.appendChild(fixtureOutput);</script>';
    $body = str_replace('</body>', $assertions . '</body>', $body);
  }
  header('Content-Type: ' . $type . (str_starts_with($type, 'text/') ? '; charset=UTF-8' : ''));
  echo $body;
}
catch (Throwable) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Review not found.';
}
