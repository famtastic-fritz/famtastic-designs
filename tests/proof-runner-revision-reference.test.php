<?php

declare(strict_types=1);

/**
 * Narrow no-bootstrap test for the revision baseline render reference.
 *
 * It never constructs the runner, touches Drupal services, dispatches a
 * provider, stores Build DNA, writes a proof candidate, or queues email. It
 * proves the private normalizer keeps source-byte integrity while giving the
 * remote runner an inactive, contact-free reference of a real proof page.
 */

if (!class_exists('Drupal', FALSE)) {
  final class Drupal {
    public static string $testRoot = '';

    public static function root(): string {
      return self::$testRoot;
    }
  }
}

require dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/ProofRunnerContractService.php';

use Drupal\famtastic_pipeline\Service\ProofRunnerContractService;

$temp = sys_get_temp_dir() . '/famtastic-proof-revision-reference-' . bin2hex(random_bytes(8));
$webRoot = $temp . '/backend/web';
$proofDirectory = $webRoot . '/proofs/revision-reference';
if (!mkdir($proofDirectory, 0700, TRUE) && !is_dir($proofDirectory)) {
  fwrite(STDERR, "FAIL: could not create fixture proof directory\n");
  exit(1);
}
Drupal::$testRoot = $webRoot;
$path = $proofDirectory . '/index.html';
$html = '<!doctype html><html><head><base href="https://unsafe.example/"><script>window.leak = true;</script></head><body onload="steal()"><main><h1>Keep this visual hierarchy</h1><a href="javascript:run()">CTA</a><iframe src="https://unsafe.example/"></iframe><p>hello@sample.test · (772) 555-0199</p></main></body></html>';
file_put_contents($path, $html);

try {
  $class = new ReflectionClass(ProofRunnerContractService::class);
  $runner = $class->newInstanceWithoutConstructor();
  $method = $class->getMethod('revisionBaselineReference');
  $reference = $method->invoke($runner, 'web/proofs/revision-reference/index.html', hash('sha256', $html));
  if (!is_array($reference) || !hash_equals((string) ($reference['sha256'] ?? ''), hash('sha256', (string) ($reference['html'] ?? '')))) {
    throw new RuntimeException('sanitized reference checksum is not reproducible');
  }
  $safe = (string) ($reference['html'] ?? '');
  if (!str_contains($safe, 'Keep this visual hierarchy')
    || preg_match('/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:|hello@sample\.test|772\)\s*555/i', $safe)) {
    throw new RuntimeException('sanitized reference did not preserve safe layout while removing active/contact content');
  }
  $redactor = $class->getMethod('redactContactValues');
  $notes = (string) $redactor->invoke($runner, 'Call 772-555-0199 or write hello@sample.test about the revision.');
  if (preg_match('/hello@sample\.test|772[- )]555/i', $notes) || !str_contains($notes, '[redacted-email]') || !str_contains($notes, '[redacted-phone]')) {
    throw new RuntimeException('revision notes did not redact contact values before provider dispatch');
  }
  fwrite(STDOUT, "PASS: revision baseline is hash-bound and sanitized for no-send remote reference\n");
}
catch (Throwable $error) {
  fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
  exit(1);
}
finally {
  @unlink($path);
  @rmdir($proofDirectory);
  @rmdir(dirname($proofDirectory));
  @rmdir(dirname(dirname($proofDirectory)));
  @rmdir($webRoot);
  @rmdir(dirname($webRoot));
  @rmdir($temp);
}
