<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ProofArtifactInputs as Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/ProofArtifactInputs.php';

/** Executes the actual old/new public callback using the same isolated doubles. */
final class ProofCallbackServiceParityTest extends TestCase {
  private const BASE = '3bc4e9baf0368db35d454ae8922c149430a866b4';
  private const BASE_BYTES = 63318;
  private const BASE_SHA256 = '549273b904ff05a263f2b3db5bc182f4b11e7fa2779ba3a69491eded1810e890';
  private const MAX_FIXTURE_BYTES = 65536;
  private const NO_GIT_PATH = '/nonexistent/famtastic-parity-no-executables';

  #[DataProvider('callbacks')]
  public function testActualCallbackAgainstFrozenPreExtractionService(array $variants): void {
    $repo = dirname(__DIR__, 8);
    $path = 'backend/web/modules/custom/famtastic_pipeline/src/Service/ProofCampaignService.php';
    $fixture = $repo . '/backend/tests/fixtures/managed-proof/ProofCampaignService.pre-extraction.fixture';
    self::assertFileExists($fixture);
    self::assertFalse(is_link($fixture));
    $old = file_get_contents($fixture, FALSE, NULL, 0, self::MAX_FIXTURE_BYTES + 1);
    self::assertIsString($old);
    self::assertLessThanOrEqual(self::MAX_FIXTURE_BYTES, strlen($old));
    self::assertSame(self::BASE_BYTES, strlen($old), 'Frozen source size from ' . self::BASE);
    self::assertSame(self::BASE_SHA256, hash('sha256', $old), 'Never regenerate the old baseline from current source.');
    self::assertSame($this->runCallback($repo, $fixture, $variants), $this->runCallback($repo, $repo . '/' . $path, $variants));
  }

  public function testCallbackChildCannotLookUpGitHistory(): void {
    // Same child launch environment as every old/new callback below. Absolute
    // PHP_BINARY still runs, but an accidental Git subprocess cannot resolve.
    $probe = <<<'PHP'
    $p = @proc_open(['git', '--version'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($p === FALSE) exit(73);
    fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    exit(proc_close($p) === 0 ? 0 : 73);
PHP;
    [$status, $out] = $this->process([PHP_BINARY, '-r', $probe]);
    self::assertSame(73, $status, 'Git must be unavailable in the actual callback child environment.');
    self::assertSame('', $out);
  }

  public static function callbacks(): iterable {
    $v = Input::variants(); yield 'images-and-thumbnails' => [$v];
    yield 'reordered' => [array_reverse($v)];
    foreach ($v as &$item) { unset($item['assets'], $item['thumbnail_base64'], $item['thumbnail_media_type']); } unset($item);
    yield 'HTML-only' => [$v];
    $v = Input::variants(); $v[0]['html'] = '<script>alert(1)</script>'; yield 'active HTML' => [$v];
    $v = Input::variants(); $v[0]['assets'][0]['relative_path'] = '../escape.png'; yield 'traversal' => [$v];
    $v = Input::variants(); $v[0]['thumbnail_base64'] = '!!!'; yield 'thumbnail failure' => [$v];
    yield 'wrong direction count' => [[Input::variants()[0]]];
  }

  private function runCallback(string $repo, string $serviceFile, array $variants): array {
    $fixture = file_get_contents($repo . '/scripts/test-normal-selected-records.php');
    // Retain that fixture's actual callback + duplicate and its exact temp cleanup.
    // Do not enter its later selection/build flow or introduce a replacement DB.
    $cut = strpos($fixture, "    \$request = \$input['request'];");
    $finally = strpos($fixture, '  } finally {', $cut);
    self::assertNotFalse($cut); self::assertNotFalse($finally);
    $capture = <<<'PHP'
    $files = [];
    $scan = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS));
    foreach ($scan as $file) if ($file->isFile()) $files[substr($file->getPathname(), strlen($tmp) + 1)] = ['sha256' => hash_file('sha256', $file->getPathname()), 'bytes' => filesize($file->getPathname())];
    ksort($files);
    echo json_encode(['first' => $first['newly_processed'], 'retry' => $retry['newly_processed'], 'campaign' => $campaign->data,
      'variants' => $entities->variants, 'row' => $db->row, 'writes' => $db->writes, 'tables' => $db->tables, 'jobs' => $ledger->jobs, 'files' => $files], JSON_THROW_ON_ERROR);
  } catch (\Throwable $e) {
    echo json_encode(['exception' => $e::class, 'message' => $e->getMessage(), 'row' => $db->row, 'writes' => $db->writes, 'jobs' => $ledger->jobs], JSON_THROW_ON_ERROR);
PHP;
    $fixture = substr($fixture, 0, $cut) . $capture . substr($fixture, $finally);
    $needle = "require \$root . \$class . '.php';";
    self::assertSame(1, substr_count($fixture, $needle));
    // Trusted test-owned paths only. Do not embed the full service in argv: the
    // double-base64 old-service argument exceeded 134 KB on the first harness.
    $fixture = str_replace($needle, "(\$class === 'ProofCampaignService') ? require " . var_export($serviceFile, TRUE) . " : require \$root . \$class . '.php';", $fixture);
    $fixture = str_replace('dirname(__DIR__)', var_export($repo, TRUE), $fixture);
    $callback = Input::input(); unset($callback['schema']); $callback['variants'] = $variants;
    $code = "eval('?>' . base64_decode('" . base64_encode($fixture) . "'));";
    self::assertLessThan(65536, strlen($code), 'Keep each callback child argument bounded for CI.');
    [$status, $output, $errors] = $this->process([PHP_BINARY, '-r', $code], Input::wire(['installation' => [], 'raw_callback' => Input::wire($callback)]));
    self::assertSame(0, $status, $errors);
    self::assertSame('', $errors);
    $result = json_decode($output, TRUE, flags: JSON_THROW_ON_ERROR);
    if (count($variants) === 3 && !str_contains(Input::wire($variants), '<script>') && !str_contains(Input::wire($variants), '../') && !str_contains(Input::wire($variants), '!!!')) {
      self::assertTrue($result['first'] ?? FALSE, $output); self::assertFalse($result['retry']);
    }
    return $result;
  }

  private function process(array $command, string $input = ''): array {
    $p = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
      __DIR__ . '/Fixtures', ['PATH' => self::NO_GIT_PATH]);
    self::assertIsResource($p); fwrite($pipes[0], $input); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out, $err];
  }
}
