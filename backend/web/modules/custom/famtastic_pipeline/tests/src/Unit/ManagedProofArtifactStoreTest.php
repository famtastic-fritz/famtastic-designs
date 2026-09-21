<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\{ManagedProofArtifactStore, ProofCallbackArtifacts};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ProofArtifactInputs as Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/ProofArtifactInputs.php';

final class ManagedProofArtifactStoreTest extends TestCase {
  private string $temporary;
  private string $root;
  private string $web;

  protected function setUp(): void {
    $this->temporary = realpath(sys_get_temp_dir()) . '/managed-proof-test-' . bin2hex(random_bytes(12));
    mkdir($this->temporary, 0700);
    mkdir($this->root = $this->temporary . '/private', 0700);
    mkdir($this->web = $this->temporary . '/web', 0700);
  }

  protected function tearDown(): void {
    // Test-owned temporary roots only; the production store never deletes.
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($walk as $file) {
      if ($file->isLink() || !$file->isDir()) unlink($file->getPathname());
      else rmdir($file->getPathname());
    }
    rmdir($this->temporary);
  }

  private function normalized(?array $input = NULL): array { return ProofCallbackArtifacts::normalize(($input ?? Input::input())['variants'], Input::DIRECTIONS); }
  private function prepare(?ManagedProofArtifactStore $store = NULL, ?array $input = NULL, ?array $expected = NULL): array {
    return ($store ?? new ManagedProofArtifactStore($this->root, $this->web))->prepare(Input::wire($input ?? Input::input()), $expected ?? $this->normalized());
  }
  private function reject(callable $operation, string $message = ''): void {
    $error = NULL;
    try { $operation(); } catch (\Throwable $e) { $error = $e; }
    self::assertNotNull($error, 'Expected rejection, never hide a failed PHPUnit assertion in the exception catch.');
    if ($message !== '') self::assertStringContainsString($message, $error->getMessage());
  }
  private function emptyRoot(): void { self::assertSame(['.', '..'], scandir($this->root)); }

  public function testCompleteManifestCoversEveryPreparedByteAndNoWebWrites(): void {
    $result = $this->prepare(); $dir = $result['directory']; $manifest = $result['manifest'];
    self::assertFalse($manifest['deliverable']); self::assertSame('private_preparation_only', $manifest['status']);
    self::assertSame(['.', '..'], scandir($this->web));
    self::assertSame($manifest, json_decode(file_get_contents($dir . '/manifest.json'), TRUE, flags: JSON_THROW_ON_ERROR));
    self::assertSame($result['manifest_sha256'], hash_file('sha256', $dir . '/manifest.json'));
    self::assertSame($result['manifest_size_bytes'], filesize($dir . '/manifest.json'));
    $actual = [];
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($walk as $file) if ($file->isFile() && $file->getFilename() !== 'manifest.json') $actual[] = substr($file->getPathname(), strlen($dir) + 1);
    sort($actual); self::assertSame(array_column($manifest['files'], 'path'), $actual);
    foreach ($manifest['files'] as $file) {
      self::assertSame($file['sha256'], hash_file('sha256', $dir . '/' . $file['path']));
      self::assertSame($file['size_bytes'], filesize($dir . '/' . $file['path']));
      self::assertSame(0400, fileperms($dir . '/' . $file['path']) & 0777);
    }
    self::assertSame(Input::wire(Input::input()), file_get_contents($dir . '/callback.json'));
    foreach (Input::variants() as $variant) {
      $d = $variant['direction_id'];
      self::assertSame($variant['html'], file_get_contents($dir . '/' . $manifest['variants'][$d]['html']));
      self::assertSame($variant['design_dna'], json_decode(file_get_contents($dir . '/' . $manifest['variants'][$d]['design_dna']), TRUE));
      self::assertSame(base64_decode($variant['thumbnail_base64']), file_get_contents($dir . '/' . $manifest['variants'][$d]['thumbnail']));
    }
    $retry = $this->prepare();
    self::assertNotSame($dir, $retry['directory']); self::assertSame($manifest, $retry['manifest']);
    self::assertSame($result['manifest_sha256'], $retry['manifest_sha256']);
  }

  public function testUnconfiguredAndPublicOrNoncanonicalRootsFailWithoutWrites(): void {
    $this->reject(fn() => $this->prepare(new ManagedProofArtifactStore()), 'unconfigured');
    mkdir($this->web . '/private', 0700);
    foreach ([[$this->web . '/private', $this->web], [$this->temporary, $this->web], [$this->web, $this->web], [$this->root . '/../private', $this->web], ['/', $this->web], [$this->root, NULL], [$this->root . '/missing', $this->web], ['private://proofs', $this->web]] as [$root, $web]) {
      $this->reject(fn() => $this->prepare(new ManagedProofArtifactStore($root, $web)));
    }
    chmod($this->root, 0755); $this->reject(fn() => $this->prepare(), 'owner-only'); chmod($this->root, 0700);
    $this->emptyRoot();
  }

  #[DataProvider('invalidWire')]
  public function testStrictInputRejectsBeforeCreatingAnything(array $input): void {
    $this->reject(fn() => $this->prepare(input: $input)); $this->emptyRoot();
  }
  public static function invalidWire(): iterable {
    foreach (['lease_token', 'authorization', 'credentials', 'private_root', 'artifact_path', 'build_dna', 'anything'] as $key) {
      $i = Input::input(); $i[$key] = 'synthetic-reject'; yield 'top-' . $key => [$i];
    }
    foreach (['schema' => 'unsupported', 'campaign_id' => '../escape', 'event_id' => '', 'job_id' => 1, 'variants' => 'bad'] as $key => $value) {
      $i = Input::input(); $i[$key] = $value; yield $key => [$i];
    }
    foreach (['lease_token', 'artifact_path', 'selected_build_artifacts'] as $key) {
      $i = Input::input(); $i['variants'][0][$key] = 'bad'; yield 'variant-' . $key => [$i];
    }
    foreach (['lease_token', 'API_KEY', 'credentials', 'source_capture', 'selected_build_continuation'] as $key) {
      $i = Input::input(); $i['variants'][0]['design_dna']['nested'] = [$key => 'bad']; yield 'dna-' . $key => [$i];
    }
    foreach (['../escape.png', '/tmp/escape.png', 'a/../../x.png', '%2e%2e/x.png', '.hidden.png', 'x.php', 'a//x.png'] as $path) {
      $i = Input::input(); $i['variants'][0]['assets'] = [Input::asset($path)]; yield 'path-' . $path => [$i];
    }
    $i = Input::input(); $i['variants'][0]['assets'][0]['sha256'] = str_repeat('0', 64); yield 'wrong hash' => [$i];
    $i = Input::input(); $i['variants'][0]['assets'][0]['size_bytes'] = 24; yield 'untrusted size field' => [$i];
    $i = Input::input(); $i['variants'][0]['assets'] = [Input::asset('hero.png', 2020725)]; yield 'canonical logo still over cap' => [$i];
    $i = Input::input(); $i['variants'][0]['design_dna']['notes'] = str_repeat('x', 4097); yield 'DNA string cap' => [$i];
    $i = Input::input(); $i['variants'][0]['design_dna']['notes'] = array_fill(0, 513, 1); yield 'DNA node cap' => [$i];
    $i = Input::input(); $i['variants'][0]['thumbnail_base64'] = []; yield 'thumbnail type' => [$i];
    $i = Input::input(); unset($i['event_id']); yield 'missing identity' => [$i];
  }

  public function testRawWireAndEveryNormalizedFieldMustMatch(): void {
    $store = new ManagedProofArtifactStore($this->root, $this->web); $wire = Input::wire(Input::input());
    foreach ([' ' . $wire, substr($wire, 0, -1) . ',"lease_token":"bad"}', substr($wire, 0, -1) . ',"event_id":"synthetic-preparation"}', str_repeat(' ', 24 * 1024 * 1024 + 1)] as $raw) $this->reject(fn() => $store->prepare($raw, $this->normalized()));
    foreach (['html', 'design_dna', 'assets', 'thumbnail', 'thumbnail_extension'] as $key) {
      $expected = $this->normalized(); $expected['a'][$key] = NULL;
      $this->reject(fn() => $store->prepare($wire, $expected), 'differs');
    }
    $this->emptyRoot();
  }

  public function testSymlinkRootsAndExistingBundleCannotBeAdopted(): void {
    symlink($this->root, $this->temporary . '/alias');
    $this->reject(fn() => $this->prepare(new ManagedProofArtifactStore($this->temporary . '/alias', $this->web)));
    $id = str_repeat('a', 32); mkdir($this->root . '/' . $id, 0700);
    file_put_contents($this->root . '/' . $id . '/retained', 'unchanged');
    $store = $this->faultStore('none');
    $this->reject(fn() => $this->prepare($store), 'already exists');
    self::assertSame('unchanged', file_get_contents($this->root . '/' . $id . '/retained'));
    self::assertFileDoesNotExist($this->root . '/' . $id . '/manifest.json');
  }

  #[DataProvider('faults')]
  public function testWriteFaultsKeepPrivateUnfinishedBytesAndNeverReturnSuccess(string $fault): void {
    $store = $this->faultStore($fault); $this->reject(fn() => $this->prepare($store));
    $dir = $this->root . '/' . str_repeat('a', 32);
    self::assertDirectoryExists($dir); self::assertFileDoesNotExist($dir . '/manifest.json');
    self::assertSame(['.', '..'], scandir($this->web));
    $this->reject(fn() => $this->prepare($store), 'already exists');
  }
  public static function faults(): iterable { foreach (['partial', 'size', 'hash', 'symlink', 'collision'] as $fault) yield $fault => [$fault]; }
  private function faultStore(string $fault): ManagedProofArtifactStore {
    return new class($this->root, $this->web, $fault) extends ManagedProofArtifactStore {
      private int $writes = 0;
      public function __construct(private string $root, private string $web, private string $fault) { parent::__construct($root, $web); }
      protected function newBundleId(): string { return str_repeat('a', 32); }
      protected function writeBytes(mixed $handle, string $bytes): int|false {
        if (++$this->writes === 1) {
          if ($this->fault === 'partial') return fwrite($handle, substr($bytes, 0, 1));
          if ($this->fault === 'size') { fwrite($handle, substr($bytes, 0, -1)); return strlen($bytes); }
          if ($this->fault === 'hash') return fwrite($handle, str_repeat('x', strlen($bytes)));
          if ($this->fault === 'symlink') symlink($this->web, $this->root . '/' . str_repeat('a', 32) . '/b');
          if ($this->fault === 'collision') file_put_contents($this->root . '/' . str_repeat('a', 32) . '/a/index.html', 'do not replace');
        }
        return parent::writeBytes($handle, $bytes);
      }
    };
  }
}
