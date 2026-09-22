<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Bounded, immutable static review packages; never customer runtime source. */
final class FullSiteReviewPackage {

  public const SCHEMA = 'famtastic.full-site-review.v1';
  public const MAX_PATH_SEGMENTS = 3;
  private const TYPES = [
    'html' => 'text/html', 'css' => 'text/css', 'js' => 'text/javascript',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2',
    'txt' => 'text/plain', 'md' => 'text/plain',
  ];

  /** Canonicalizes the trusted staff manifest, not a customer upload. */
  public static function normalize(array $input): array {
    if (($input['schema'] ?? '') !== self::SCHEMA
      || !preg_match('/^[a-z0-9][a-z0-9-]{2,99}$/', (string) ($input['review_id'] ?? ''))) {
      throw new \InvalidArgumentException('A versioned full-site review manifest is required.');
    }
    $text = static fn(mixed $value, int $max = 1200): string => mb_substr(trim(strip_tags((string) $value)), 0, $max);
    $title = $text($input['title'] ?? '', 255);
    $files = $input['files'] ?? [];
    if ($title === '' || !is_array($files) || !array_is_list($files) || !$files || count($files) > 300) {
      throw new \InvalidArgumentException('A title and bounded file manifest are required.');
    }
    $normalized = []; $total = 0;
    foreach ($files as $file) {
      if (!is_array($file)) throw new \InvalidArgumentException('Invalid review file.');
      $path = self::path((string) ($file['path'] ?? ''));
      $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      $type = self::TYPES[$extension] ?? '';
      $role = (string) ($file['role'] ?? '');
      if (isset($normalized[$path]) || $type === '' || ($file['media_type'] ?? '') !== $type
        || !preg_match('/^[a-f0-9]{64}$/', (string) ($file['sha256'] ?? ''))
        || !is_int($file['bytes'] ?? NULL) || $file['bytes'] < 1 || $file['bytes'] > 10000000) {
        throw new \InvalidArgumentException('Review file type, size, hash or path is invalid.');
      }
      if (($role === 'page' && ($extension !== 'html' || !preg_match('~^(?:[a-z0-9-]+/)*index\.html$~', $path)))
        || ($role === 'asset' && (!str_starts_with($path, 'assets/') || in_array($extension, ['html', 'txt', 'md'], TRUE)))
        || ($role === 'document' && (!str_starts_with($path, 'review-documents/') || !in_array($extension, ['txt', 'md'], TRUE)))
        || !in_array($role, ['page', 'asset', 'document'], TRUE)) {
        throw new \InvalidArgumentException('Only declared pages, static assets and review documents are allowed.');
      }
      $total += $file['bytes'];
      if ($total > 64000000) throw new \InvalidArgumentException('Review package exceeds 64 MB.');
      $normalized[$path] = ['path' => $path, 'role' => $role, 'media_type' => $type, 'sha256' => $file['sha256'], 'bytes' => $file['bytes']];
    }
    ksort($normalized);
    $entry = self::path((string) ($input['entry_path'] ?? 'index.html'));
    if (($normalized[$entry]['role'] ?? '') !== 'page') throw new \InvalidArgumentException('Review entry page is missing.');
    $pages = []; $documents = [];
    foreach (['pages' => 'page', 'documents' => 'document'] as $list => $role) {
      $items = $input[$list] ?? [];
      if (!is_array($items) || !array_is_list($items) || count($items) > 50) throw new \InvalidArgumentException('Invalid review document list.');
      $seen = [];
      foreach ($items as $item) {
        if (!is_array($item)) throw new \InvalidArgumentException('Invalid review link.');
        $path = self::path((string) ($item['path'] ?? ''));
        $label = $text($item['label'] ?? '', 160);
        if ($label === '' || isset($seen[$path]) || ($normalized[$path]['role'] ?? '') !== $role) throw new \InvalidArgumentException('Every review link must name a declared file.');
        $seen[$path] = TRUE;
        if ($role === 'page') $pages[] = ['label' => $label, 'path' => $path];
        else $documents[] = ['label' => $label, 'path' => $path];
      }
      $expected = array_keys(array_filter($normalized, static fn(array $file): bool => $file['role'] === $role));
      $actual = array_keys($seen); sort($actual); sort($expected);
      if ($actual !== $expected) throw new \InvalidArgumentException('Every page and document must have an explicit review label.');
    }
    if (!$pages) throw new \InvalidArgumentException('Review pages are required.');
    $sources = [];
    foreach (array_slice((array) ($input['research']['sources'] ?? []), 0, 20) as $source) {
      if (!is_array($source) || !self::httpsUrl((string) ($source['url'] ?? ''))) throw new \InvalidArgumentException('Research sources require HTTPS URLs.');
      $label = $text($source['title'] ?? '', 255);
      if ($label === '') throw new \InvalidArgumentException('Research sources require titles.');
      $sources[] = ['title' => $label, 'url' => $source['url']];
    }
    $researched = $text($input['research']['researched_at'] ?? '', 10);
    if ($researched !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $researched)) throw new \InvalidArgumentException('Invalid research date.');
    $commit = (string) ($input['source_commit'] ?? '');
    if (!preg_match('/^[a-f0-9]{40}$/', $commit)) throw new \InvalidArgumentException('A real source commit is required.');
    $buildId = $text($input['build_id'] ?? '', 170);
    if ($buildId === '') throw new \InvalidArgumentException('A registered Build DNA id is required.');
    return [
      'schema' => self::SCHEMA, 'review_id' => $input['review_id'], 'title' => $title,
      'entry_path' => $entry, 'source_commit' => $commit, 'build_id' => $buildId,
      'pages' => $pages, 'documents' => $documents, 'files' => array_values($normalized),
      'research' => ['overview' => $text($input['research']['overview'] ?? ''), 'researched_at' => $researched, 'sources' => $sources],
    ];
  }

  public static function digest(array $manifest): string {
    return hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
  }

  public static function path(string $path): string {
    if ($path === '' || strlen($path) > 240 || str_contains($path, '\\') || str_contains($path, '%') || str_contains($path, '?') || str_contains($path, '#')) throw new \InvalidArgumentException('Invalid review path.');
    $parts = explode('/', $path);
    if (count($parts) > self::MAX_PATH_SEGMENTS) throw new \InvalidArgumentException('Review paths support at most three segments.');
    foreach ($parts as $part) {
      if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,95}$/', $part) || $part === '.' || $part === '..') throw new \InvalidArgumentException('Invalid review path.');
    }
    return $path;
  }

  /** No symlink in the root or any path component; every read verifies bytes. */
  public static function read(string $root, array $file): string {
    $root = rtrim($root, '/');
    if (!str_starts_with($root, '/') || is_link($root) || !is_dir($root)) throw new \RuntimeException('Review storage unavailable.');
    $path = self::path((string) $file['path']);
    $current = $root;
    foreach (explode('/', $path) as $part) {
      $current .= '/' . $part;
      if (is_link($current)) throw new \RuntimeException('Review symlinks are forbidden.');
    }
    $realRoot = realpath($root); $real = realpath($current);
    if (!$realRoot || !$real || !str_starts_with($real, $realRoot . '/') || !is_file($real) || filesize($real) !== $file['bytes']) throw new \RuntimeException('Review file unavailable.');
    $bytes = file_get_contents($real);
    if ($bytes === FALSE || !hash_equals($file['sha256'], hash('sha256', $bytes))) throw new \RuntimeException('Review file integrity failed.');
    return $bytes;
  }

  public static function httpsUrl(string $url): bool {
    $parts = parse_url($url);
    return strlen($url) <= 2048 && filter_var($url, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https' && empty($parts['user']) && empty($parts['pass']);
  }

  public static function url(string $publicId, string $path): string {
    return '/web/api/customer/website-requests/' . rawurlencode($publicId) . '/full-site/' . implode('/', array_map('rawurlencode', explode('/', self::path($path))));
  }

  public static function staffUrl(int $requestId, string $path): string {
    if ($requestId < 1) throw new \InvalidArgumentException('A request id is required.');
    return '/web/admin/famtastic/website-request/' . $requestId . '/full-site/' . implode('/', array_map('rawurlencode', explode('/', self::path($path))));
  }

  /** Only public labels and protected reader links leave the service boundary. */
  public static function projection(string $publicId, mixed $record): ?array {
    try {
      if (!is_array($record) || ($record['request_public_id'] ?? '') !== $publicId || !is_array($record['manifest'] ?? NULL)) return NULL;
      $manifest = self::normalize($record['manifest']);
      if (!hash_equals(self::digest($manifest), (string) ($record['manifest_sha256'] ?? ''))) return NULL;
      return [
        'status' => 'ready_for_review', 'title' => $manifest['title'], 'review_id' => $manifest['review_id'],
        'created_at' => (string) ($record['created_at'] ?? ''), 'page_count' => count($manifest['pages']),
        'url' => self::url($publicId, $manifest['entry_path']),
        'documents' => array_map(static fn(array $item): array => ['label' => $item['label'], 'url' => self::url($publicId, $item['path'])], $manifest['documents']),
        'research' => $manifest['research'],
      ];
    }
    catch (\Throwable) { return NULL; }
  }
}
