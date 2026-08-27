<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Config-backed cohort direction contracts.
 *
 * A cold importer supplies only a profile ID. The stored profile snapshot—not
 * importer code—defines the proof count, labels, and creative intent. The
 * The profile is frozen on the delivery and handed to the proof worker. This
 * allows an owner to choose a bounded 1--6 direction cohort without turning a
 * cold campaign import into a new global product setting.
 */
final class ProofCohortProfileService implements ProofCohortProfileResolverInterface {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /** {@inheritdoc} */
  public function resolveAnonymous(?string $profileId = NULL): array {
    $config = $this->configFactory->get('famtastic_pipeline.proof_cohorts');
    $profileId = trim((string) ($profileId ?: $config->get('anonymous.default_profile')));
    if (preg_match('/^[a-z0-9][a-z0-9_.-]{2,127}$/', $profileId) !== 1) {
      throw new \InvalidArgumentException('A valid anonymous proof cohort profile is required.');
    }
    $profiles = (array) $config->get('anonymous.profiles');
    $raw = $profiles[$profileId] ?? NULL;
    if (!is_array($raw)) {
      throw new \InvalidArgumentException('Unknown anonymous proof cohort profile: ' . $profileId);
    }
    if ((string) ($raw['audience'] ?? '') !== 'anonymous_public') {
      throw new \InvalidArgumentException('The requested profile is not available to anonymous public proof rooms.');
    }
    $count = (int) ($raw['direction_count'] ?? 0);
    $directions = (array) ($raw['directions'] ?? []);
    if ($count < 1 || $count > 6) {
      throw new \InvalidArgumentException('Anonymous proof cohort profiles must configure between one and six directions.');
    }
    $expectedIds = array_slice(['a', 'b', 'c', 'd', 'e', 'f'], 0, $count);
    if (array_keys($directions) !== $expectedIds) {
      throw new \InvalidArgumentException('Anonymous proof cohort direction IDs must be the ordered prefix a through f for the configured count.');
    }
    $normalized = [];
    foreach ($directions as $id => $definition) {
      if (!is_array($definition)) {
        throw new \InvalidArgumentException('Anonymous proof cohort directions must be mappings.');
      }
      $name = $this->clean((string) ($definition['name'] ?? ''), 255);
      $intent = $this->clean((string) ($definition['intent'] ?? ''), 1200);
      if ($name === '' || $intent === '') {
        throw new \InvalidArgumentException('Every anonymous proof cohort direction requires a name and intent.');
      }
      $normalized[$id] = ['name' => $name, 'intent' => $intent];
    }
    return [
      'id' => $profileId,
      'audience' => 'anonymous_public',
      'direction_count' => $count,
      'directions' => $normalized,
    ];
  }

  private function clean(string $value, int $maximum): string {
    $value = preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? '';
    return mb_substr($value, 0, $maximum);
  }

}
