<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\{ManagedProofArtifactStore, ProofCallbackArtifacts};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ProofArtifactInputs as Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/ProofArtifactInputs.php';

/** Real disposable filesystem; no importer, database, account or remote effects. */
final class ManagedProofArtifactVerificationTest extends TestCase {
  private string $temporary;
  private string $root;
  private string $web;
  private ManagedProofArtifactStore $store;
  private array $prepared;

  protected function setUp(): void {
    $this->temporary = realpath(sys_get_temp_dir()) . '/managed-proof-verification-' . bin2hex(random_bytes(12));
    mkdir($this->temporary, 0700);
    mkdir($this->root = $this->temporary . '/private', 0700);
    mkdir($this->web = $this->temporary . '/web', 0700);
    $this->store = new ManagedProofArtifactStore($this->root, $this->web);
    $this->prepared = $this->store->prepare(Input::wire(Input::input()), ProofCallbackArtifacts::normalize(Input::variants(), Input::DIRECTIONS));
  }

  protected function tearDown(): void {
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($walk as $file) {
      if ($file->isLink() || !$file->isDir()) unlink($file->getPathname());
      else rmdir($file->getPathname());
    }
    rmdir($this->temporary);
  }

  private function verify(?string $id = NULL, ?string $hash = NULL): array {
    return $this->store->verifyPrepared($id ?? basename($this->prepared['directory']), $hash ?? $this->prepared['manifest_sha256']);
  }
  private function replace(string $relative, string $bytes): void {
    $path = $this->prepared['directory'] . '/' . $relative;
    if (file_exists($path)) chmod($path, 0600);
    file_put_contents($path, $bytes); chmod($path, 0400);
  }
  private function reject(callable $call, string $message): void {
    $error = NULL;
    try { $call(); } catch (\Throwable $e) { $error = $e; }
    self::assertNotNull($error, 'Expected rejection, never catch the assertion.');
    self::assertStringContainsString($message, $error->getMessage());
  }
  private function snapshot(): array {
    $result = [];
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
    foreach ($walk as $file) {
      $path = $file->getPathname(); $s = lstat($path);
      $result[$path] = [$s['ino'], $s['mode'], $s['mtime'], $s['size'], $file->isFile() ? hash_file('sha256', $path) : NULL];
    }
    ksort($result); return $result;
  }

  public function testRepeatedVerificationReturnsOnlyExactFactsWithoutWritesOrAuthority(): void {
    $before = $this->snapshot(); $one = $this->verify();
    self::assertSame($one, $this->verify()); self::assertSame($before, $this->snapshot());
    self::assertSame(['bundle_id', 'manifest', 'manifest_sha256', 'raw_callback', 'normalized'], array_keys($one));
    self::assertSame($this->prepared['manifest'], $one['manifest']); self::assertFalse($one['manifest']['deliverable']);
    self::assertSame('private_preparation_only', $one['manifest']['status']);
    self::assertSame(Input::wire(Input::input()), $one['raw_callback']);
    self::assertSame(ProofCallbackArtifacts::normalize(Input::variants(), Input::DIRECTIONS), $one['normalized']);
    self::assertSame(['.', '..'], scandir($this->web));
  }

  #[DataProvider('badIdentities')]
  public function testWorkerPathsAndMalformedHashesAreRejected(string $id, string $hash): void {
    $before = $this->snapshot(); $this->reject(fn() => $this->verify($id, $hash), 'identity or manifest hash');
    self::assertSame($before, $this->snapshot());
  }
  public static function badIdentities(): iterable {
    foreach (['../escape', '/tmp/escape', 'private://escape', str_repeat('a', 32) . '/..', strtoupper(str_repeat('a', 32)), ''] as $id) yield $id => [$id, str_repeat('b', 64)];
    yield 'hash' => [str_repeat('a', 32), 'wrong'];
  }

  public function testUnconfiguredStorageUnknownBundleAndWrongHashReject(): void {
    $this->reject(fn() => (new ManagedProofArtifactStore())->verifyPrepared(basename($this->prepared['directory']), $this->prepared['manifest_sha256']), 'unconfigured');
    $this->reject(fn() => $this->verify(str_repeat('0', 32)), 'private owned directories');
    $this->reject(fn() => $this->verify(hash: str_repeat('0', 64)), 'manifest hash mismatch');
  }

  #[DataProvider('mutations')]
  public function testEveryArtifactAndInventoryMutationFailsClosed(string $case, string $message): void {
    $dir = $this->prepared['directory']; $hash = $this->prepared['manifest_sha256'];
    switch ($case) {
      case 'html': $this->replace('a/index.html', '<html>Changed</html>'); break;
      case 'dna': $this->replace('b/design-dna.json', '{}'); break;
      case 'image': $path = $this->prepared['manifest']['variants']['c']['assets'][0]['path']; $this->replace($path, 'bad'); break;
      case 'missing': unlink($dir . '/a/index.html'); break;
      case 'extra-file': $this->replace('extra.txt', 'extra'); break;
      case 'extra-dir': mkdir($dir . '/extra', 0700); break;
      case 'symlink-file': unlink($dir . '/a/index.html'); symlink($dir . '/b/index.html', $dir . '/a/index.html'); break;
      case 'symlink-dir': rename($dir . '/a', $this->temporary . '/saved-a'); symlink($this->temporary . '/saved-a', $dir . '/a'); break;
      case 'hardlink': unlink($dir . '/a/index.html'); link($dir . '/b/index.html', $dir . '/a/index.html'); break;
      case 'unsealed': chmod($dir . '/a/index.html', 0600); break;
      case 'public-directory': chmod($dir . '/b', 0755); break;
      case 'manifest-oversize': $this->replace('manifest.json', str_repeat(' ', 65537)); $hash = hash('sha256', str_repeat(' ', 65537)); break;
      case 'manifest-space': $wire = file_get_contents($dir . '/manifest.json') . ' '; $this->replace('manifest.json', $wire); $hash = hash('sha256', $wire); break;
      case 'manifest-lie': $m = $this->prepared['manifest']; $m['deliverable'] = TRUE; $wire = Input::wire($m); $this->replace('manifest.json', $wire); $hash = hash('sha256', $wire); break;
      case 'callback-space': $this->replace('callback.json', ' ' . Input::wire(Input::input())); break;
      case 'callback-secret': $input = Input::input(); $input['lease_token'] = 'synthetic-only'; $this->replace('callback.json', Input::wire($input)); break;
      case 'callback-dna': $input = Input::input(); $input['variants'][0]['design_dna']['source_capture'] = 'synthetic'; $this->replace('callback.json', Input::wire($input)); break;
      case 'callback-changed': $input = Input::input(); $input['variants'][0]['html'] .= ' '; $this->replace('callback.json', Input::wire($input)); break;
      case 'callback-oversize': chmod($dir . '/callback.json', 0600); $f = fopen($dir . '/callback.json', 'r+b'); ftruncate($f, 24 * 1024 * 1024 + 1); fclose($f); chmod($dir . '/callback.json', 0400); break;
    }
    $this->reject(fn() => $this->verify(hash: $hash), $message);
    self::assertSame(['.', '..'], scandir($this->web));
  }
  public static function mutations(): iterable {
    foreach (['html' => 'canonical callback bytes', 'dna' => 'canonical callback bytes', 'image' => 'canonical callback bytes', 'missing' => 'incomplete',
      'extra-file' => 'undeclared', 'extra-dir' => 'undeclared', 'symlink-file' => 'Symbolic links', 'symlink-dir' => 'Symbolic links',
      'hardlink' => 'unsealed, linked or oversized', 'unsealed' => 'unsealed, linked or oversized', 'public-directory' => 'private owned directories',
      'manifest-oversize' => 'oversized', 'manifest-space' => 'canonical callback inventory', 'manifest-lie' => 'canonical callback inventory',
      'callback-space' => 'canonical JSON', 'callback-secret' => 'unknown fields', 'callback-dna' => 'prohibited field',
      'callback-changed' => 'canonical callback inventory', 'callback-oversize' => 'oversized'] as $case => $message) yield $case => [$case, $message];
  }

  public function testForgedManifestHashCannotHideChangedFileBytes(): void {
    $m = $this->prepared['manifest']; $this->replace('a/index.html', '<html>Changed</html>');
    foreach ($m['files'] as &$file) if ($file['path'] === 'a/index.html') { $file['size_bytes'] = 20; $file['sha256'] = hash('sha256', '<html>Changed</html>'); }
    unset($file); $wire = Input::wire($m); $this->replace('manifest.json', $wire);
    $this->reject(fn() => $this->verify(hash: hash('sha256', $wire)), 'canonical callback inventory');
  }

  public function testNonregularFileRejectedBeforePotentiallyBlockingOpen(): void {
    if (!function_exists('posix_mkfifo') || !function_exists('pcntl_alarm')) self::markTestSkipped('Unix FIFO/alarm capability required; not proven on this platform.');
    $path = $this->prepared['directory'] . '/a/index.html'; unlink($path);
    self::assertTrue(posix_mkfifo($path, 0400));
    $previousAsync = pcntl_async_signals(TRUE); $previousHandler = pcntl_signal_get_handler(SIGALRM);
    pcntl_signal(SIGALRM, static function (): void { throw new \RuntimeException('Bounded FIFO check timed out, unsafe open occurred.'); });
    $previousAlarm = pcntl_alarm(2); $started = hrtime(TRUE);
    try {
      $this->reject(fn() => $this->verify(), 'Prepared file is missing, unsealed, linked or oversized.');
      self::assertLessThan(1.0, (hrtime(TRUE) - $started) / 1e9);
    }
    finally {
      pcntl_alarm(0); pcntl_signal(SIGALRM, $previousHandler); pcntl_async_signals($previousAsync);
      if ($previousAlarm > 0) pcntl_alarm($previousAlarm);
    }
  }
}
