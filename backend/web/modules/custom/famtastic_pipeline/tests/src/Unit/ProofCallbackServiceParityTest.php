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

  #[DataProvider('callbacks')]
  public function testActualCallbackAgainstPreExtractionCommit(array $variants): void {
    $repo = dirname(__DIR__, 8);
    $path = 'backend/web/modules/custom/famtastic_pipeline/src/Service/ProofCampaignService.php';
    [$status, $old, $errors] = $this->process(['git', '-C', $repo, 'show', self::BASE . ':' . $path]);
    self::assertSame(0, $status, $errors);
    self::assertSame($this->runCallback($repo, $old, $variants), $this->runCallback($repo, file_get_contents($repo . '/' . $path), $variants));
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

  private function runCallback(string $repo, string $service, array $variants): array {
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
    $fixture = str_replace($needle, "(\$class === 'ProofCampaignService') ? eval('?>' . base64_decode('" . base64_encode($service) . "')) : require \$root . \$class . '.php';", $fixture);
    $fixture = str_replace('dirname(__DIR__)', var_export($repo, TRUE), $fixture);
    $callback = Input::input(); unset($callback['schema']); $callback['variants'] = $variants;
    [$status, $output, $errors] = $this->process([PHP_BINARY, '-r', "eval('?>' . base64_decode('" . base64_encode($fixture) . "'));"], Input::wire(['installation' => [], 'raw_callback' => Input::wire($callback)]));
    self::assertSame(0, $status, $errors);
    self::assertSame('', $errors);
    $result = json_decode($output, TRUE, flags: JSON_THROW_ON_ERROR);
    if (count($variants) === 3 && !str_contains(Input::wire($variants), '<script>') && !str_contains(Input::wire($variants), '../') && !str_contains(Input::wire($variants), '!!!')) {
      self::assertTrue($result['first'] ?? FALSE, $output); self::assertFalse($result['retry']);
    }
    return $result;
  }

  private function process(array $command, string $input = ''): array {
    $p = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    self::assertIsResource($p); fwrite($pipes[0], $input); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out, $err];
  }
}
