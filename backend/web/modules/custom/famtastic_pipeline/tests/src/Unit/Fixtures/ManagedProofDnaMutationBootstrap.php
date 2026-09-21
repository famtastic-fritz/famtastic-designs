<?php
declare(strict_types=1);

/** Explicit offline mutation probe only; never used by normal test discovery. */
require dirname(__DIR__, 9) . '/scripts/automation-test-bootstrap.php';

if (class_exists(\Drupal\famtastic_pipeline\Service\ManagedProofArtifactStore::class, FALSE)) {
  throw new \RuntimeException('Mutation bootstrap requires an unloaded store.');
}
$path = dirname(__DIR__, 4) . '/src/Service/ManagedProofArtifactStore.php';
$source = file_get_contents($path);
if ($source === FALSE) throw new \RuntimeException('Cannot read mutation source.');
// Suppress only the recursive DNA guard and DNA serialized-size predicate in
// this subprocess. Raw/normalized equality and every other store guard remain.
foreach (["self::dna(\$variant['design_dna'], \$nodes);", " || strlen(self::wire(\$variant['design_dna'])) > 32768"] as $needle) {
  if (substr_count($source, $needle) !== 1) throw new \RuntimeException('Mutation anchor changed; no mutation proof.');
  $source = str_replace($needle, '', $source);
}
eval('?>' . $source);
