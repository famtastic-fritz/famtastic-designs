<?php

declare(strict_types=1);

/**
 * Narrow no-bootstrap contract test for the callback provenance gate.
 *
 * It deliberately calls no provider, database, mail, payment, or Drupal
 * service. The private verifier helper is exercised through reflection so a
 * locally labelled fixture cannot be reclassified as a production completion.
 */

require dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/ProofRunnerCallbackVerifier.php';

use Drupal\famtastic_pipeline\Service\ProofRunnerCallbackVerifier;

$class = new ReflectionClass(ProofRunnerCallbackVerifier::class);
$verifier = $class->newInstanceWithoutConstructor();
$method = $class->getMethod('assertNoFixtureOrMockEvidence');

$productionEvidence = [
  'run' => [
    'execution_class' => 'provider_completion',
    'environment' => 'production',
    'provider_mode' => 'live',
    'evidence_level' => 'provider_completed',
  ],
  'quality' => ['visual' => ['reviewer' => 'independent_visual_reviewer', 'review_type' => 'independent']],
  'stages' => [[
    'stage_id' => 'provider-browser-qa',
    'execution' => [
      'kind' => 'provider_execution',
      'provider' => ['id' => 'creative_provider', 'mode' => 'live', 'environment' => 'production'],
      'model' => ['id' => 'image-model', 'status' => 'resolved'],
      'timing' => ['status' => 'provider_reported'],
      'cost' => ['status' => 'provider_reported'],
    ],
    'result' => ['status' => 'passed'],
  ]],
  'artifacts' => [[
    'role' => 'proof_html',
    'path' => 'proofs/a/index.html',
    'rights_status' => 'owned_or_licensed',
  ]],
  'retrieval' => [
    'filesystem' => ['status' => 'stored'],
    'database' => ['status' => 'registered'],
    'site_studio' => ['status' => 'provider_returned'],
  ],
];

try {
  $method->invoke($verifier, $productionEvidence);
}
catch (Throwable $error) {
  fwrite(STDERR, 'FAIL: production-shaped evidence was rejected: ' . $error->getMessage() . PHP_EOL);
  exit(1);
}

$fixtureEvidence = $productionEvidence;
$fixtureEvidence['stages'][0]['execution']['provider']['id'] = 'site_studio_loopback_fixture';
try {
  $method->invoke($verifier, $fixtureEvidence);
  fwrite(STDERR, "FAIL: fixture evidence was accepted.\n");
  exit(1);
}
catch (ReflectionException $error) {
  fwrite(STDERR, 'FAIL: callback evidence test could not invoke verifier: ' . $error->getMessage() . PHP_EOL);
  exit(1);
}
catch (Throwable $error) {
  $cause = $error;
  if (!$cause instanceof \InvalidArgumentException || !str_contains($cause->getMessage(), 'non-production fixture/mock/test evidence')) {
    fwrite(STDERR, 'FAIL: fixture evidence produced the wrong result: ' . $error->getMessage() . PHP_EOL);
    exit(1);
  }
}

fwrite(STDOUT, "PASS: runner callback rejects explicitly labelled fixture/mock/test provenance\n");
