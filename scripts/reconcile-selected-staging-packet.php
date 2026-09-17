<?php
/** Local proposal only. Does not access Drupal, register, dispatch, or contact a service. */
declare(strict_types=1);
$root = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
require $root . 'SelectedStagingContinuation.php';
if ($argc !== 5) { fwrite(STDERR, "Usage: php reconcile-selected-staging-packet.php legacy.json authoritative-context.json design-dna.json evidence-ref\nContext must contain request and contact from verified same-account records. Output is an unregistered proposal.\n"); exit(2); }
try {
  $read = static fn(string $path): array => json_decode(file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  $context = $read($argv[2]);
  $packet = \Drupal\famtastic_pipeline\Service\SelectedStagingContinuation::reconcileLegacy($read($argv[1]), $context['request'], $context['contact'], $read($argv[3]), $argv[4]);
  echo json_encode(['status' => 'unregistered_proposal', 'packet' => $packet], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
