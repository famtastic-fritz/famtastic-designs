<?php
declare(strict_types=1);
// Local harness only, preserving Drupal's /web mount without exposing settings.
$sandbox = realpath(getenv('SELECTED_DRUPAL_SANDBOX') ?: '') ?: '';
if (PHP_SAPI !== 'cli-server' || !preg_match('#/famtastic-selected-drupal\.[A-Za-z0-9]{6}$#', $sandbox)
  || realpath(__DIR__) !== $sandbox . '/scripts' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
  http_response_code(403); exit;
}
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (!str_starts_with($path, '/web/') || str_contains($path, '..') || str_contains($path, "\0")) { http_response_code(404); exit; }
$root = $sandbox . '/backend';
$file = realpath($root . $path);
$types = ['css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
  'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'gif' => 'image/gif'];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($file && is_file($file) && str_starts_with($file, $root . '/web/') && isset($types[$ext])) {
  header('Content-Type: ' . $types[$ext]); header('X-Content-Type-Options: nosniff'); readfile($file); return;
}
if ($file && $file !== $root . '/web/index.php' && $path !== '/web/') { http_response_code(404); exit; }
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['SCRIPT_FILENAME'] = $root . '/web/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/web/index.php';
chdir($root . '/web');
require $_SERVER['SCRIPT_FILENAME'];
