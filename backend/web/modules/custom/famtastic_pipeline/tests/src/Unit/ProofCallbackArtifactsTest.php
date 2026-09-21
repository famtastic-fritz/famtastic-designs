<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\ProofCallbackArtifacts;
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\{LegacyProofCallbackArtifacts, ProofArtifactInputs as Input};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/LegacyProofCallbackArtifacts.php';
require_once __DIR__ . '/Fixtures/ProofArtifactInputs.php';

final class ProofCallbackArtifactsTest extends TestCase {
  private static function captureNormalizationOutcome(callable $call): array {
    try { return ['value' => $call()]; }
    catch (\Throwable $e) { return ['exception' => $e::class, 'message' => $e->getMessage()]; }
  }

  #[DataProvider('cases')]
  public function testFrozenLegacyValidationParity(array $variants, array $directions = Input::DIRECTIONS, bool $signed = FALSE): void {
    self::assertSame(self::captureNormalizationOutcome(fn() => LegacyProofCallbackArtifacts::normalize($variants, $directions, $signed)),
      self::captureNormalizationOutcome(fn() => ProofCallbackArtifacts::normalize($variants, $directions, $signed)));
  }

  public static function cases(): iterable {
    $base = Input::variants();
    yield 'ordinary' => [$base];
    yield 'signed assets' => [$base, Input::DIRECTIONS, TRUE];
    yield 'reordered' => [array_reverse($base)];
    yield 'too few' => [array_slice($base, 1)];
    yield 'too many' => [[...$base, $base[0]]];
    $v = $base; $v[0] = 'not an object'; yield 'scalar variant' => [$v];
    foreach (['A', ' a ', 'd', '', NULL, 1, 'b'] as $direction) {
      $v = $base; $v[0]['direction_id'] = $direction; yield 'direction-' . var_export($direction, TRUE) => [$v];
    }
    foreach (['', NULL, 123, '<SCRIPT>x</SCRIPT>', '<IFRAME>', '<object>', '<embed>', '<base>', '<p onclick="x">', '<a href="javascript:alert(1)">', str_repeat('x', 500000), str_repeat('x', 500001)] as $i => $html) {
      $v = $base; $v[0]['html'] = $html; yield 'html-' . $i => [$v];
    }
    foreach ([NULL, 'scalar', ['extra_legacy_field' => 'preserve'], 42] as $i => $dna) {
      $v = $base; $v[0]['design_dna'] = $dna; yield 'dna-' . $i => [$v];
    }
    foreach ([NULL, [], 'bad', [Input::asset('../escape.png')], [Input::asset(), Input::asset()], array_fill(0, 5, Input::asset())] as $i => $assets) {
      $v = $base; $v[0]['assets'] = $assets; yield 'assets-' . $i => [$v];
    }
    $v = $base; $v[0]['assets'] = []; yield 'signed empty' => [$v, Input::DIRECTIONS, TRUE];
    foreach (['sha256' => str_repeat('0', 64), 'base64' => '!!!', 'media_type' => 'image/jpeg'] as $key => $value) {
      $v = $base; $v[0]['assets'][0][$key] = $value; yield 'asset-' . $key => [$v];
    }
    foreach ([2000000, 2000001, 2020725] as $size) {
      $v = $base; $v[0]['assets'] = [Input::asset('hero.png', $size)]; yield 'asset-size-' . $size => [$v];
    }
    $v = $base; $second = Input::asset('second.png', 1500001); $second['asset_id'] = 'second';
    $v[0]['assets'] = [Input::asset('first.png', 1500000), $second]; yield 'combined cap' => [$v];
    $v = $base; $a = $v[0]['assets'][0]; $v[0]['assets'] = [['id' => $a['asset_id'], 'path' => $a['relative_path'], 'data_base64' => $a['base64'], 'sha256' => strtoupper($a['sha256']), 'media_type' => ' IMAGE/PNG ']];
    yield 'legacy asset aliases' => [$v];
    foreach (['', 'IMAGE/PNG', 'image/png', 'image/webp'] as $mime) {
      $v = $base; $v[0]['thumbnail_media_type'] = $mime; $v[0]['thumbnail_base64'] = base64_encode("\x89PNG\r\n\x1a\nfixture"); yield 'thumbnail mime-' . $mime => [$v];
    }
    foreach (['', '!!!', base64_encode('bad'), base64_encode("\xff\xd8\xff" . str_repeat('x', 1499997)), base64_encode("\xff\xd8\xff" . str_repeat('x', 1499998))] as $i => $thumb) {
      $v = $base; $v[0]['thumbnail_base64'] = $thumb; yield 'thumbnail-' . $i => [$v];
    }
    $v = $base; foreach ($v as $i => &$item) $item['direction_id'] = ['d', 'e', 'f'][$i]; unset($item);
    yield 'showcase' => [$v, ['d' => 'Royal', 'e' => 'Crown', 'f' => 'Live']];
    yield 'custom ordered policy' => [$base, ['c' => 'C', 'b' => 'B', 'a' => 'A']];
  }
}
