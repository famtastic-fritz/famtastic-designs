<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\SelectedCreatorCreditProjection as Credit;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/SelectedCreatorCreditProjection.php';

/** Pure byte-level checks; the dependency-free script covers agency seams. */
final class SelectedCreatorCreditProjectionTest extends TestCase {
  public function testPolicyAndRowArePinned(): void {
    self::assertSame(694, strlen(Credit::row()));
    self::assertSame(Credit::policy()['row_sha256'], hash('sha256', Credit::row()));
    self::assertSame('bb45adcade4cb87faac70ca84b14f8b1cb347395fbc8f3e14f50215d3704e914', hash('sha256', json_encode(Credit::policy(), JSON_UNESCAPED_SLASHES)));
    self::assertFalse(Credit::policy()['customer_acceptance_changed']);
    self::assertFalse(Credit::policy()['publication_authorized']);
  }

  public function testOriginalBytesAndCanonicalIdentity(): void {
    $html = "\xEF\xBB\xBF<html><body><main>café 雪 😀</main>\r\n</BODY></html>\r\n";
    $artifact = ['path' => 'web/proofs/synthetic.html', 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)];
    $derived = str_replace('</BODY>', Credit::row() . "\n</body>", $html);
    $projected = Credit::project($html, $artifact);
    self::assertSame('append_missing_root_row', $projected['mode']);
    self::assertSame(['source_path' => $artifact['path'], 'sha256' => $artifact['sha256'], 'bytes' => $artifact['bytes']], $projected['original_home']);
    self::assertSame(['path' => 'index.html', 'sha256' => hash('sha256', $derived), 'bytes' => strlen($derived)], $projected['derived_home']);
    self::assertSame($derived, Credit::derive($derived));
  }

  public function testInvalidExistingCreditIsNotRepaired(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('source_association_credit_existing_unsupported');
    Credit::derive('<html><body><div data-fd-creator-credit="v1">Other presentation</div></body></html>');
  }

  public function testWorkerReceiptCannotRedefinePolicy(): void {
    $html = '<html><body>Original</body></html>';
    $expected = Credit::project($html, ['path' => 'web/proofs/synthetic.html', 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)]);
    $supplied = $expected; $supplied['policy']['system_asset']['bytes']--;
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('source_association_credit_projection_mismatch');
    Credit::assertProjection($supplied, $expected);
  }
}
