<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\{ManagedProofArtifactPackage as Package, ManagedProofArtifactStore, ManagedProofPackageFiles, ProofAssetContract, ProofCallbackArtifacts, SelectedCreatorCreditProjection as Credit};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ProofArtifactInputs as Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/ProofArtifactInputs.php';
require_once dirname(__DIR__, 3) . '/src/Service/ProofAssetContract.php';
require_once dirname(__DIR__, 3) . '/src/Service/ProofCallbackArtifacts.php';
require_once dirname(__DIR__, 3) . '/src/Service/ManagedProofArtifactStore.php';
require_once dirname(__DIR__, 3) . '/src/Service/SelectedCreatorCreditProjection.php';
require_once dirname(__DIR__, 3) . '/src/Service/ManagedProofPackageFiles.php';
require_once dirname(__DIR__, 3) . '/src/Service/ManagedProofArtifactPackage.php';

/** Synthetic private file tests only. No real receipt, tenant or import exists. */
final class ManagedProofArtifactPackageTest extends TestCase {
  private string $temporary;
  private string $sources;
  private string $packages;
  private string $web;
  private string $logo;
  private ManagedProofArtifactStore $store;

  protected function setUp(): void {
    $this->logo = getenv('FAMTASTIC_TEST_CANONICAL_LOGO') ?: dirname(__DIR__, 8) . '/frontend/public/brand/famtastic-designs-logo-v1.png';
    // Test-only override supports sparse helpers without Git history or copying
    // the PNG into fixtures. Never skip positive policy verification if absent.
    self::assertFileExists($this->logo, 'Supply the existing canonical PNG for this sparse checkout.');
    $this->logo = realpath($this->logo);
    self::assertSame(2020725, filesize($this->logo));
    self::assertSame('ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950', hash_file('sha256', $this->logo));
    $this->temporary = realpath(sys_get_temp_dir()) . '/managed-package-test-' . bin2hex(random_bytes(12));
    mkdir($this->temporary, 0700);
    mkdir($this->sources = $this->temporary . '/sources', 0700);
    mkdir($this->packages = $this->temporary . '/packages', 0700);
    mkdir($this->web = $this->temporary . '/web', 0700);
    $this->store = new ManagedProofArtifactStore($this->sources, $this->web);
  }

  protected function tearDown(): void {
    if (!isset($this->temporary) || !is_dir($this->temporary)) return;
    // Exact test-owned root only. No following links, adoption or source cleanup.
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($walk as $file) {
      if ($file->isLink() || !$file->isDir()) unlink($file->getPathname());
      else rmdir($file->getPathname());
    }
    rmdir($this->temporary);
  }

  private function package(?\Closure $resolver = NULL, ?string $logo = NULL): Package {
    return new Package($this->store, new ManagedProofPackageFiles($this->packages, $this->web), $logo ?? $this->logo, $resolver);
  }
  private function source(?array $input = NULL): array {
    $input ??= Input::input();
    return $this->store->prepare(Input::wire($input), ProofCallbackArtifacts::normalize($input['variants'], Input::DIRECTIONS));
  }
  private function prepared(?array $input = NULL): array {
    $s = $this->source($input);
    return [$s, $this->package()->prepare(basename($s['directory']), $s['manifest_sha256'])];
  }
  private function binding(array $source, array $package): array {
    return ['receipt_id' => 'synthetic-committed-receipt', 'package_id' => $package['package_id'], 'package_manifest_sha256' => $package['package_manifest_sha256'],
      'prepared_bundle_id' => basename($source['directory']), 'prepared_manifest_sha256' => $source['manifest_sha256'],
      'allowed_roles' => array_fill_keys(['a', 'b', 'c'], ['html', 'asset', 'thumbnail', 'system_logo'])];
  }
  private function readBinding(array $binding, string $direction = 'a', string $role = 'html', ?string $asset = NULL): array {
    return $this->package(static fn() => $binding)->read('synthetic-committed-receipt', $direction, $role, $asset);
  }
  private function reject(callable $operation, string $message): void {
    $error = NULL;
    try { $operation(); } catch (\Throwable $e) { $error = $e; }
    self::assertNotNull($error, 'Expected rejection must not catch a failed PHPUnit assertion.');
    self::assertStringContainsString($message, $error->getMessage());
  }
  private function snapshot(string $root): array {
    $files = [];
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
      $files[substr($file->getPathname(), strlen($root) + 1)] = [hash_file('sha256', $file->getPathname()), $file->getSize(), $file->getPerms() & 0777];
    }
    ksort($files); return $files;
  }
  private function replace(string $path, string $bytes): void {
    chmod($path, 0600); file_put_contents($path, $bytes); chmod($path, 0400); clearstatcache(TRUE, $path);
  }

  public function testExactProjectionRolesAndOriginalsRemainSeparate(): void {
    $input = Input::input();
    $input['variants'][0]['html'] = "\xEF\xBB\xBF<html><body><main>café 雪</main>\r\n</BODY></html>\r\n";
    $input['variants'][1]['html'] = Credit::derive($input['variants'][1]['html']);
    $source = $this->source($input); $before = $this->snapshot($source['directory']);
    $p = $this->package()->prepare(basename($source['directory']), $source['manifest_sha256']); $binding = $this->binding($source, $p);
    self::assertFalse($p['manifest']['deliverable']); self::assertSame('private_package_only', $p['manifest']['status']);
    self::assertSame(['bundle_id' => basename($source['directory']), 'manifest_sha256' => $source['manifest_sha256']], $p['manifest']['source']);
    foreach ($input['variants'] as $variant) {
      $d = $variant['direction_id']; $html = $variant['html'];
      $expected = $d === 'b' ? $html : str_ireplace('</body>', Credit::row() . "\n</body>", $html);
      self::assertSame($expected, $this->readBinding($binding, $d)['bytes']);
      self::assertSame(Credit::project($html, ['path' => $d . '/index.html', 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)]), $p['manifest']['variants'][$d]['projection']);
      self::assertSame($d === 'b' ? 'identity' : 'append_missing_root_row', $p['manifest']['variants'][$d]['projection']['mode']);
      $logo = $this->readBinding($binding, $d, 'system_logo');
      self::assertSame(2020725, $logo['size_bytes']); self::assertSame(Credit::policy()['system_asset']['sha256'], $logo['sha256']);
      self::assertSame(file_get_contents($this->logo), $logo['bytes']); self::assertSame('image/png', $logo['media_type']);
      self::assertSame(base64_decode($variant['assets'][0]['base64']), $this->readBinding($binding, $d, 'asset', 'hero')['bytes']);
      self::assertSame(base64_decode($variant['thumbnail_base64']), $this->readBinding($binding, $d, 'thumbnail')['bytes']);
    }
    self::assertSame($before, $this->snapshot($source['directory']));
    self::assertSame(['.', '..'], scandir($this->web));
    $dir = $this->packages . '/' . $p['package_id'];
    foreach ($this->snapshot($dir) as $file) self::assertSame(0400, $file[2]);
    self::assertSame(0700, fileperms($dir) & 0777);
    self::assertFileDoesNotExist($dir . '/callback.json'); self::assertFileDoesNotExist($dir . '/a/design-dna.json');
  }

  public function testDefaultsAndDeniedResolverGrantNoReadsOrWrites(): void {
    $this->reject(fn() => (new Package())->prepare(str_repeat('a', 32), str_repeat('b', 64)), 'unconfigured');
    $this->reject(fn() => (new Package())->read('synthetic', 'a', 'html'), 'resolver is unconfigured');
    $this->reject(fn() => $this->package(static fn() => NULL)->read('synthetic', 'a', 'html'), 'Invalid authoritative');
    $this->reject(fn() => $this->package(static function() { throw new \RuntimeException('access denied'); })->read('synthetic', 'a', 'html'), 'access denied');
    self::assertSame(['.', '..'], scandir($this->packages)); self::assertSame(['.', '..'], scandir($this->sources));
  }

  public function testPreImportVerificationNeedsNoReceiptAndGrantsNoReadAuthority(): void {
    [$s, $p] = $this->prepared(); $before = $this->snapshot($this->packages); $sources = $this->snapshot($this->sources);
    $facts = $this->package()->verifyPreparedPackage($p['package_id'], $p['package_manifest_sha256'], basename($s['directory']), $s['manifest_sha256']);
    self::assertSame($p, $facts); self::assertArrayNotHasKey('bytes', $facts); self::assertArrayNotHasKey('receipt_id', $facts);
    $this->reject(fn() => $this->package()->read('synthetic', 'a', 'html'), 'resolver is unconfigured');
    self::assertSame($before, $this->snapshot($this->packages)); self::assertSame($sources, $this->snapshot($this->sources));
    $other = $this->source();
    self::assertSame($s['manifest_sha256'], $other['manifest_sha256'], 'Same bytes in another bundle still have a different server identity.');
    $this->reject(fn() => $this->package()->verifyPreparedPackage($p['package_id'], $p['package_manifest_sha256'], basename($other['directory']), $other['manifest_sha256']), 'differs from regenerated source');
    $input = Input::input(); $input['variants'][0]['html'] = '<html><body>Different valid source</body></html>'; $other = $this->source($input);
    $this->reject(fn() => $this->package()->verifyPreparedPackage($p['package_id'], $p['package_manifest_sha256'], basename($other['directory']), $other['manifest_sha256']), 'differs from regenerated source');
    $path = $this->packages . '/' . $p['package_id'] . '/a/index.html'; $this->replace($path, 'tampered');
    $this->reject(fn() => $this->package()->verifyPreparedPackage($p['package_id'], $p['package_manifest_sha256'], basename($s['directory']), $s['manifest_sha256']), 'differ from regenerated');
    self::assertSame($s['manifest_sha256'], $this->store->verifyPrepared(basename($s['directory']), $s['manifest_sha256'])['manifest_sha256']);
  }

  #[DataProvider('badRequests')]
  public function testCallerCannotRequestPathsDnaOrUnknownRoles(string $receipt, string $direction, string $role, ?string $asset): void {
    $called = FALSE;
    $p = $this->package(static function() use (&$called) { $called = TRUE; return NULL; });
    $this->reject(fn() => $p->read($receipt, $direction, $role, $asset), 'Invalid package read');
    self::assertFalse($called);
  }
  public static function badRequests(): iterable {
    yield ['../receipt', 'a', 'html', NULL]; yield ['synthetic', '../a', 'html', NULL];
    foreach (['callback', 'design_dna', 'original', 'manifest', 'assets/brand/famtastic-designs-logo-v1.png', 'HTML'] as $role) yield ['synthetic', 'a', $role, NULL];
    yield ['synthetic', 'a', 'asset', '../hero.png']; yield ['synthetic', 'a', 'asset', NULL];
    yield ['synthetic', 'a', 'asset', '1hero'];
    yield ['synthetic', 'a', 'system_logo', 'hero']; yield ['synthetic', 'a', 'html', 'hero'];
  }

  #[DataProvider('badBindings')]
  public function testForgedResolverBindingsRejectBeforeFilesystem(string $field, mixed $value, string $message): void {
    $b = ['receipt_id' => 'synthetic-committed-receipt', 'package_id' => 'mp-' . str_repeat('a', 32), 'package_manifest_sha256' => str_repeat('b', 64),
      'prepared_bundle_id' => str_repeat('c', 32), 'prepared_manifest_sha256' => str_repeat('d', 64), 'allowed_roles' => ['a' => ['html']]];
    $b[$field] = $value;
    $this->reject(fn() => $this->readBinding($b), $message);
  }
  public static function badBindings(): iterable {
    yield ['receipt_id', 'foreign', 'Invalid authoritative']; yield ['package_id', '../outside', 'Invalid server package'];
    yield ['package_manifest_sha256', 'https://invalid.test/hash', 'Invalid package manifest'];
    yield ['path', '/private/arbitrary', 'Invalid authoritative']; yield ['lease_token', 'synthetic-secret', 'Invalid authoritative'];
    yield ['allowed_roles', [], 'not authorized']; yield ['allowed_roles', ['b' => ['html']], 'not authorized'];
    yield ['allowed_roles', ['a' => ['system_logo']], 'not authorized']; yield ['allowed_roles', ['a' => ['html', 'callback']], 'Invalid authoritative package role'];
    yield ['allowed_roles', ['a' => ['html', 'html']], 'Invalid authoritative package role'];
    yield ['allowed_roles', ['a' => [42]], 'Invalid authoritative package role'];
  }

  public function testResolverGetsExactRequestAndDenialRemainsAuthoritative(): void {
    [$source, $p] = $this->prepared(); $b = $this->binding($source, $p); $b['allowed_roles'] = ['b' => ['asset']]; $calls = [];
    $reader = $this->package(static function(...$args) use ($b, &$calls) { $calls[] = $args; return $b; });
    self::assertSame(Input::asset()['sha256'], $reader->read($b['receipt_id'], 'b', 'asset', 'hero')['sha256']);
    $this->reject(fn() => $reader->read($b['receipt_id'], 'b', 'html'), 'not authorized');
    $this->reject(fn() => $reader->read($b['receipt_id'], 'a', 'asset', 'hero'), 'not authorized');
    self::assertSame([$b['receipt_id'], 'b', 'asset', 'hero'], $calls[0]);
    $this->reject(fn() => $reader->read($b['receipt_id'], 'b', 'asset', 'missing'), 'role is absent');
  }

  public function testOptionalMissingRolesDoNotInventAssetsOrThumbnails(): void {
    $input = Input::input();
    foreach ($input['variants'] as &$variant) unset($variant['assets'], $variant['thumbnail_base64'], $variant['thumbnail_media_type']);
    unset($variant);
    [$s, $p] = $this->prepared($input); $binding = $this->binding($s, $p);
    self::assertCount(6, $p['manifest']['files']);
    self::assertSame([], $p['manifest']['variants']['a']['assets']); self::assertNull($p['manifest']['variants']['a']['thumbnail']);
    $this->reject(fn() => $this->readBinding($binding, 'a', 'asset', 'hero'), 'role is absent');
    $this->reject(fn() => $this->readBinding($binding, 'a', 'thumbnail'), 'role is absent');
    self::assertSame('image/png', $this->readBinding($binding, 'a', 'system_logo')['media_type']);
  }

  #[DataProvider('badCredits')]
  public function testAmbiguousCreditsAreRejectedWithoutPackageWrites(string $html): void {
    $input = Input::input(); $input['variants'][0]['html'] = $html; $source = $this->source($input);
    $this->reject(fn() => $this->package()->prepare(basename($source['directory']), $source['manifest_sha256']), 'source_association_credit');
    self::assertSame(['.', '..'], scandir($this->packages));
  }
  public static function badCredits(): iterable {
    yield ['<html><body><div data-fd-creator-credit="v1">Legacy unchanged</div></body></html>'];
    yield ['<html><body><div data-famtastic-creator-credit="1">Forged</div></body></html>'];
    yield ['<html><body>' . Credit::row() . Credit::row() . "\n</body></html>"];
    yield ['<html><body><section>' . Credit::row() . "\n</section></body></html>"];
    yield ['<html><body><p>famtasticdesigns.com</p></body></html>'];
    yield ['<main>Missing document body</main>'];
  }

  public function testExactHtmlBoundaryAndWorkerLogoCapRemainIndependent(): void {
    $input = Input::input(); $shell = '<html><body></body></html>';
    $input['variants'][0]['html'] = str_replace('</body>', str_repeat('x', 500000 - strlen($shell)) . '</body>', $shell);
    [$source, $p] = $this->prepared($input);
    self::assertSame(500695, Package::MAX_HTML_BYTES);
    self::assertSame(500695, $this->readBinding($this->binding($source, $p))['size_bytes']);
    $input['variants'][0]['html'] .= 'x';
    $this->reject(fn() => $this->store->prepare(Input::wire($input), []), 'limited to 500 KB');
    $input = Input::input(); $input['variants'][0]['assets'][0]['base64'] = base64_encode(file_get_contents($this->logo));
    $input['variants'][0]['assets'][0]['sha256'] = Credit::policy()['system_asset']['sha256'];
    self::assertSame(2000000, ProofAssetContract::MAX_ASSET_BYTES);
    // Actual store rejects the worker PNG, not prior normalization by the test.
    $this->reject(fn() => $this->store->prepare(Input::wire($input), []), 'allowed size');
  }

  #[DataProvider('logoModes')]
  public function testTrustedLogoInputModesProduceOnlySealedCopies(int $mode): void {
    $logo = $this->temporary . '/canonical.png'; copy($this->logo, $logo); chmod($logo, $mode);
    $s = $this->source(); $p = $this->package(logo: $logo)->prepare(basename($s['directory']), $s['manifest_sha256']);
    self::assertSame($mode, fileperms($logo) & 0777);
    foreach (['a', 'b', 'c'] as $d) {
      $path = $this->packages . '/' . $p['package_id'] . '/' . $d . '/' . Credit::ASSET_PATH;
      self::assertSame(0400, fileperms($path) & 0777); self::assertSame(Credit::policy()['system_asset']['sha256'], hash_file('sha256', $path));
    }
  }
  public static function logoModes(): iterable { yield [0600]; yield [0644]; }

  public function testForgedLogoAndWorkerReservedPathsCannotReplaceSystemAsset(): void {
    $logo = $this->temporary . '/forged.png'; $bytes = file_get_contents($this->logo); $bytes[100] = chr(ord($bytes[100]) ^ 1);
    file_put_contents($logo, $bytes); chmod($logo, 0600); $s = $this->source();
    $this->reject(fn() => $this->package(logo: $logo)->prepare(basename($s['directory']), $s['manifest_sha256']), 'differs from pinned policy');
    foreach (['brand/famtastic-designs-logo-v1.png', 'Brand/FAMtastic-designs-logo-v1.png'] as $path) {
      $input = Input::input(); $input['variants'][0]['assets'] = [Input::asset($path)]; $s = $this->source($input);
      $this->reject(fn() => $this->package()->prepare(basename($s['directory']), $s['manifest_sha256']), 'collides');
    }
    self::assertSame(['.', '..'], scandir($this->packages));
  }

  #[DataProvider('tampering')]
  public function testReadsRequireCompleteSealedExactInventory(string $mutation, string $message): void {
    [$s, $p] = $this->prepared(); $binding = $this->binding($s, $p); $dir = $this->packages . '/' . $p['package_id'];
    $path = $dir . '/a/index.html';
    switch ($mutation) {
      case 'bytes': $old = file_get_contents($path); $old[20] = 'X'; $this->replace($path, $old); break;
      case 'oversized': $this->replace($path, file_get_contents($path) . 'X'); break;
      case 'unsealed': chmod($path, 0600); break;
      case 'missing': unlink($path); break;
      case 'extra': file_put_contents($dir . '/extra', 'extra'); break;
      case 'symlink': unlink($path); symlink($this->logo, $path); break;
      case 'hardlink': unlink($path); link($dir . '/b/index.html', $path); break;
      case 'directory-link': rename($dir . '/a', $this->temporary . '/moved-a'); symlink($this->temporary . '/moved-a', $dir . '/a'); break;
      case 'manifest-large': $this->replace($dir . '/manifest.json', str_repeat('x', 65537)); break;
      case 'manifest-digest': $binding['package_manifest_sha256'] = str_repeat('0', 64); break;
      case 'manifest-path':
        $manifest = $p['manifest']; $manifest['variants']['a']['html'] = '/arbitrary/private.html'; $wire = Input::wire($manifest);
        $this->replace($dir . '/manifest.json', $wire); $binding['package_manifest_sha256'] = hash('sha256', $wire); break;
      case 'logo': $path = $dir . '/c/' . Credit::ASSET_PATH; $old = file_get_contents($path); $old[100] = chr(ord($old[100]) ^ 1); $this->replace($path, $old); break;
      case 'source': $path = $s['directory'] . '/b/index.html'; $this->replace($path, 'tampered'); break;
      case 'source-digest': $binding['prepared_manifest_sha256'] = str_repeat('0', 64); break;
      case 'source-path': $binding['prepared_bundle_id'] = '../source'; break;
    }
    $this->reject(fn() => $this->readBinding($binding), $message);
  }
  public static function tampering(): iterable {
    yield ['bytes', 'differ from regenerated']; yield ['oversized', 'oversized']; yield ['unsealed', 'unsealed'];
    yield ['missing', 'incomplete']; yield ['extra', 'undeclared']; yield ['symlink', 'Symbolic links'];
    yield ['hardlink', 'linked']; yield ['directory-link', 'Symbolic links']; yield ['manifest-large', 'oversized'];
    yield ['manifest-digest', 'hash mismatch']; yield ['manifest-path', 'differs from regenerated source'];
    yield ['logo', 'differ from regenerated']; yield ['source', 'differs from canonical callback'];
    yield ['source-digest', 'Prepared manifest hash mismatch']; yield ['source-path', 'Invalid prepared bundle'];
  }

  public function testNonregularFileRejectedBeforePotentiallyBlockingOpen(): void {
    if (!function_exists('posix_mkfifo') || !function_exists('pcntl_alarm')) self::markTestSkipped('Unix FIFO/alarm capability required; not proven on this platform.');
    $path = $this->packages . '/fifo'; self::assertTrue(posix_mkfifo($path, 0400));
    $previousAsync = pcntl_async_signals(TRUE); $previousHandler = pcntl_signal_get_handler(SIGALRM);
    pcntl_signal(SIGALRM, static function(): void { throw new \RuntimeException('Bounded FIFO check timed out, unsafe open occurred.'); });
    $previousAlarm = pcntl_alarm(2); $started = hrtime(TRUE);
    try {
      $this->reject(fn() => ManagedProofPackageFiles::read($path, 500695), 'Package file is missing, nonregular, unsealed, linked or oversized.');
      self::assertLessThan(1.0, (hrtime(TRUE) - $started) / 1e9);
    }
    finally {
      pcntl_alarm(0); pcntl_signal(SIGALRM, $previousHandler); pcntl_async_signals($previousAsync);
      if ($previousAlarm > 0) pcntl_alarm($previousAlarm);
    }
  }

  /** Max transport-sized image fixtures, not a browser/image-decoder QA proof. */
  public function testMaximumAllowedInventoryRemainsBoundedAndReadable(): void {
    $input = Input::input(); $shell = '<html><body></body></html>';
    $html = str_replace('</body>', str_repeat('x', 500000 - strlen($shell)) . '</body>', $shell);
    // Signature-prefixed synthetic bytes, not decodable PNG/image QA evidence.
    $pngFixture = base64_decode(Input::asset()['base64'], TRUE);
    $assetBytes = $pngFixture . str_repeat("\0", 750000 - strlen($pngFixture));
    $thumbnailBytes = $pngFixture . str_repeat("\0", 1500000 - strlen($pngFixture));
    $assets = [];
    for ($i = 0; $i < 4; $i++) {
      $image = $assetBytes; $image[strlen($image) - 1] = chr($i + 1);
      $assets[] = ['asset_id' => 'asset_' . $i, 'relative_path' => 'image' . $i . '.png',
        'media_type' => 'image/png', 'base64' => base64_encode($image), 'sha256' => hash('sha256', $image)];
    }
    foreach ($input['variants'] as &$variant) {
      $variant['html'] = $html; $variant['assets'] = $assets;
      $variant['thumbnail_base64'] = base64_encode($thumbnailBytes); $variant['thumbnail_media_type'] = 'image/png';
    }
    unset($variant);
    $start = hrtime(TRUE); [$source, $p] = $this->prepared($input); $preparedAt = hrtime(TRUE);
    $facts = $this->package()->verifyPreparedPackage($p['package_id'], $p['package_manifest_sha256'], basename($source['directory']), $source['manifest_sha256']);
    $verifiedAt = hrtime(TRUE);
    self::assertSame($p, $facts);
    self::assertCount(21, $p['manifest']['files']);
    $contentBytes = array_sum(array_column($p['manifest']['files'], 'size_bytes'));
    self::assertSame(21064260, $contentBytes);
    self::assertLessThanOrEqual(Package::MAX_PACKAGE_BYTES, $contentBytes + filesize($this->packages . '/' . $p['package_id'] . '/manifest.json'));
    $binding = $this->binding($source, $p);
    self::assertSame(500695, $this->readBinding($binding, 'c')['size_bytes']);
    self::assertSame(base64_decode($assets[3]['base64'], TRUE), $this->readBinding($binding, 'b', 'asset', 'asset_3')['bytes']);
    self::assertSame($thumbnailBytes, $this->readBinding($binding, 'a', 'thumbnail')['bytes']);
    self::assertSame(Credit::policy()['system_asset']['sha256'], $this->readBinding($binding, 'c', 'system_logo')['sha256']);
    echo json_encode(['synthetic_max_package' => TRUE, 'content_files' => 21, 'content_bytes' => $contentBytes,
      'prepare_including_source_seconds' => ($preparedAt - $start) / 1e9,
      'verify_seconds' => ($verifiedAt - $preparedAt) / 1e9,
      'total_with_four_role_reads_seconds' => (hrtime(TRUE) - $start) / 1e9,
      'php_allocator_peak_bytes' => memory_get_peak_usage(TRUE)], JSON_THROW_ON_ERROR) . "\n";
  }

  public function testNoOverwriteNoPartialAdoptionAndNoPublicRoots(): void {
    $s = $this->source(); $storage = new ManagedProofPackageFiles($this->packages, $this->web);
    $package = new class($this->store, $storage, $this->logo) extends Package {
      protected function newPackageId(): string { return 'mp-' . str_repeat('a', 32); }
    };
    $p = $package->prepare(basename($s['directory']), $s['manifest_sha256']); $before = $this->snapshot($this->packages);
    $this->reject(fn() => $package->prepare(basename($s['directory']), $s['manifest_sha256']), 'already exists');
    $this->reject(fn() => $storage->create($this->packages . '/' . $p['package_id'], 'a/index.html', 'overwrite'), 'collision');
    self::assertSame($before, $this->snapshot($this->packages));
    $short = new class($this->packages, $this->web) extends ManagedProofPackageFiles {
      protected function writeBytes(mixed $handle, string $bytes): int|false { return fwrite($handle, substr($bytes, 0, 1)); }
    };
    $partial = new class($this->store, $short, $this->logo) extends Package {
      protected function newPackageId(): string { return 'mp-' . str_repeat('b', 32); }
    };
    $this->reject(fn() => $partial->prepare(basename($s['directory']), $s['manifest_sha256']), 'Incomplete package write');
    self::assertDirectoryExists($this->packages . '/mp-' . str_repeat('b', 32));
    self::assertFileDoesNotExist($this->packages . '/mp-' . str_repeat('b', 32) . '/manifest.json');
    $this->reject(fn() => $partial->prepare(basename($s['directory']), $s['manifest_sha256']), 'already exists');
    mkdir($this->web . '/nested', 0700);
    $bad = new Package($this->store, new ManagedProofPackageFiles($this->web . '/nested', $this->web), $this->logo);
    $this->reject(fn() => $bad->prepare(basename($s['directory']), $s['manifest_sha256']), 'disjoint');
    self::assertSame(['.', '..'], scandir($this->web . '/nested'));
  }
}
