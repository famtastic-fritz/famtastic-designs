<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** @internal Private package I/O only; not a public reader or authority service. */
class ManagedProofPackageFiles {
  public function __construct(private readonly ?string $privateRoot = NULL, private readonly ?string $documentRoot = NULL) {}

  public function root(): string {
    if ($this->privateRoot === NULL || $this->documentRoot === NULL) throw new \RuntimeException('Managed package storage is unconfigured.');
    foreach ([$this->privateRoot, $this->documentRoot] as $path) {
      if ($path === '/' || !str_starts_with($path, '/') || realpath($path) !== $path || !is_dir($path)) throw new \RuntimeException('Package roots require canonical absolute directories.');
      self::noLinks($path);
    }
    if ($this->privateRoot === $this->documentRoot || str_starts_with($this->privateRoot, $this->documentRoot . '/')
      || str_starts_with($this->documentRoot, $this->privateRoot . '/')) throw new \RuntimeException('Package root must be disjoint from document root.');
    self::directory($this->privateRoot);
    return $this->privateRoot;
  }

  /** Caller supplies regenerated, finite relative inventory, never stored paths. */
  public function verify(string $directory, array $files): void {
    $tree = ['.' => []];
    foreach ($files as $relative => $bytes) {
      self::relative($relative);
      $parts = explode('/', $relative); $parent = '.';
      foreach ($parts as $index => $part) {
        $tree[$parent][$part] = TRUE;
        if ($index < count($parts) - 1) {
          $parent = $parent === '.' ? $part : $parent . '/' . $part;
          $tree[$parent] ??= [];
        }
      }
    }
    foreach ($tree as $relative => $entries) {
      $path = $relative === '.' ? $directory : $directory . '/' . $relative;
      self::directory($path);
      $handle = opendir($path);
      if ($handle === FALSE) throw new \RuntimeException('Cannot inspect package directory.');
      $actual = [];
      try {
        while (($entry = readdir($handle)) !== FALSE) {
          if ($entry === '.' || $entry === '..') continue;
          if (!isset($entries[$entry])) throw new \RuntimeException('Package inventory contains an undeclared entry.');
          $actual[$entry] = TRUE;
        }
      }
      finally { closedir($handle); }
      if (count($actual) !== count($entries)) throw new \RuntimeException('Package inventory is incomplete.');
    }
    foreach ($files as $relative => $bytes) {
      if (self::read($directory . '/' . $relative, strlen($bytes)) !== $bytes) throw new \RuntimeException('Package bytes differ from regenerated inventory.');
    }
    $this->root();
  }

  public function create(string $directory, string $relative, string $bytes): void {
    if (dirname($directory) !== $this->root()) throw new \RuntimeException('Package escaped its private root.');
    self::relative($relative); self::directory($directory);
    $parent = $directory;
    foreach (explode('/', dirname($relative)) as $part) {
      if ($part === '.') continue;
      $parent .= '/' . $part; self::noLinks($parent);
      if (!is_dir($parent) && !@mkdir($parent, 0700)) throw new \RuntimeException('Cannot create package directory.');
      self::directory($parent);
    }
    $path = $directory . '/' . $relative;
    self::noLinks($path);
    $handle = @fopen($path, 'xb');
    if ($handle === FALSE) throw new \RuntimeException('Package file collision or exclusive write failure.');
    try {
      if (!chmod($path, 0600) || $this->writeBytes($handle, $bytes) !== strlen($bytes) || !fflush($handle)
        || !fsync($handle) || !chmod($path, 0400)) throw new \RuntimeException('Incomplete package write.');
    }
    finally { fclose($handle); }
    if (self::read($path, strlen($bytes)) !== $bytes) throw new \RuntimeException('Package write differs from expected bytes.');
  }

  /** Sealed package files, or one trusted constructor-supplied canonical logo. */
  public static function read(string $path, int $maximum, bool $sealed = TRUE): string {
    self::noLinks($path); clearstatcache(TRUE, $path); $before = @lstat($path);
    if ($maximum < 0 || $before === FALSE || ($before['mode'] & 0170000) !== 0100000 || $before['nlink'] !== 1
      || $before['size'] > $maximum || ($sealed ? ($before['mode'] & 0777) !== 0400 : ($before['mode'] & 0022) !== 0)
      || ($sealed && function_exists('posix_geteuid') && $before['uid'] !== posix_geteuid())) throw new \RuntimeException('Package file is missing, nonregular, unsealed, linked or oversized.');
    $handle = fopen($path, 'rb');
    if ($handle === FALSE) throw new \RuntimeException('Cannot read package file.');
    try { $bytes = stream_get_contents($handle, $maximum + 1); $after = fstat($handle); }
    finally { fclose($handle); }
    self::noLinks($path); clearstatcache(TRUE, $path); $current = @lstat($path);
    if ($bytes === FALSE || strlen($bytes) !== $before['size'] || $after === FALSE || $current === FALSE) throw new \RuntimeException('Package file changed while reading.');
    foreach (['dev', 'ino', 'mode', 'uid', 'size', 'nlink', 'mtime', 'ctime'] as $key) {
      if ($before[$key] !== $after[$key] || $before[$key] !== $current[$key]) throw new \RuntimeException('Package file changed while reading.');
    }
    return $bytes;
  }

  public static function directory(string $path): void {
    self::noLinks($path); clearstatcache(TRUE, $path); $stat = @lstat($path);
    if ($stat === FALSE || ($stat['mode'] & 0170000) !== 0040000 || ($stat['mode'] & 0777) !== 0700
      || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) throw new \RuntimeException('Package directory must be private and owned.');
  }

  private static function relative(string $path): void {
    if (!preg_match('~\A[A-Za-z0-9_-][A-Za-z0-9_.-]*(?:/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*\z~', $path)
      || strlen($path) > 240) throw new \RuntimeException('Invalid internal package path.');
  }

  private static function noLinks(string $path): void {
    if (!str_starts_with($path, '/') || str_contains($path, "\0") || array_intersect(explode('/', $path), ['.', '..'])) throw new \RuntimeException('Invalid absolute package path.');
    $cursor = '';
    foreach (explode('/', ltrim($path, '/')) as $part) {
      $cursor .= '/' . $part; clearstatcache(TRUE, $cursor);
      if (is_link($cursor)) throw new \RuntimeException('Symbolic links are forbidden in package storage.');
    }
  }

  /** Synthetic short-write seam, never a worker parameter. */
  protected function writeBytes(mixed $handle, string $bytes): int|false { return fwrite($handle, $bytes); }
}
