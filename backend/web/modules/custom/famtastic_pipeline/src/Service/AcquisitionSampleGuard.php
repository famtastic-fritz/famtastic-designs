<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Validates frozen, reviewed sample input; contains no generation or dispatch. */
final class AcquisitionSampleGuard {

  public const NICHES = ['beauty_hair', 'mobile_detailing', 'baking_catering'];
  public const RETURN_PATH = '/portal?start=website&section=projects';

  public static function tokenHash(string $token): string {
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw new \InvalidArgumentException('sample_not_found');
    return hash('sha256', $token);
  }

  /** Bind only verified public facts. Private contact/claims are never inferred. */
  public static function bindings(array $input): array {
    $observation = $input['research_observation'] ?? NULL;
    unset($input['research_observation']);
    $allowed = ['business_name', 'recipient_name', 'locality', 'phone', 'booking_url', 'inquiry_url'];
    if (array_diff(array_keys($input), $allowed)) throw new \InvalidArgumentException('unsupported_sample_binding');
    $result = [];
    foreach ($allowed as $key) {
      $value = trim((string) ($input[$key] ?? ''));
      if (mb_strlen($value) > 300 || preg_match('/[\x00-\x1f<>]/u', $value) || str_contains($value, '{{')) throw new \InvalidArgumentException('unsafe_sample_binding');
      if ($key === 'recipient_name' && $value !== '' && (str_contains($value, '@') || strpbrk($value, '\\/:{}[]=') !== FALSE)) throw new \InvalidArgumentException('verified_recipient_name_required');
      if ($key === 'phone' && $value !== '' && (!preg_match('/^[+0-9(). \-]{7,40}$/D', $value) || strlen(preg_replace('/\D/', '', $value)) < 7 || strlen(preg_replace('/\D/', '', $value)) > 15)) throw new \InvalidArgumentException('verified_phone_binding_required');
      if (str_ends_with($key, '_url') && $value !== '') {
        $parts = parse_url($value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || !empty($parts['query']) || filter_var($parts['host'], FILTER_VALIDATE_IP) || !str_contains($parts['host'], '.') || preg_match('/(?:^|\.)(?:localhost|local|internal|lan|invalid)$/iD', $parts['host']) || in_array(strtolower($parts['host']), ['accounts.google.com'], TRUE)) throw new \InvalidArgumentException('unsafe_sample_url');
      }
      $result[$key] = $value;
    }
    if ($observation !== NULL) {
      if (!is_array($observation) || ($observation['owner_approved'] ?? FALSE) !== TRUE || ($observation['classification'] ?? '') !== 'verified_public_fact' || !is_numeric($observation['checked_at'] ?? NULL) || (int) $observation['checked_at'] < 1 || empty($observation['text']) || mb_strlen((string) $observation['text']) > 500 || preg_match('/[\x00-\x1f<>]/u', (string) $observation['text'])) throw new \InvalidArgumentException('reviewed_public_observation_required');
      self::bindings(['business_name' => 'Observation source', 'inquiry_url' => (string) ($observation['source_url'] ?? '')]);
      if (empty($observation['source_url'])) throw new \InvalidArgumentException('observation_source_required');
      $result['research_observation'] = $observation;
    }
    if ($result['business_name'] === '') throw new \InvalidArgumentException('business_name_required');
    return $result;
  }

  /** Two references only; Component Studio remains the owner of recipes. */
  public static function recipes(array $recipes): array {
    if (count($recipes) !== 2) throw new \InvalidArgumentException('two_reviewed_samples_required');
    $ids = [];
    foreach ($recipes as $recipe) {
      if (preg_match('/^[a-z0-9_-]{3,96}$/D', (string) ($recipe['id'] ?? '')) !== 1 || isset($ids[$recipe['id']]) || (int) ($recipe['version'] ?? 0) < 1 || ($recipe['review']['status'] ?? '') !== 'approved' || empty($recipe['review']['reviewer']) || empty($recipe['review']['receipt']) || ($recipe['recipe_ref']['owner'] ?? '') !== 'component-studio' || empty($recipe['recipe_ref']['id']) || empty($recipe['recipe_ref']['version']) || preg_match('/^[a-f0-9]{64}$/D', (string) ($recipe['sha256'] ?? '')) !== 1 || empty($recipe['artifact_path'])) throw new \InvalidArgumentException('reviewed_recipe_evidence_required');
      if (($recipe['recipe_ref']['status'] ?? '') !== 'registered') throw new \InvalidArgumentException('component_recipe_registration_required');
      $ids[$recipe['id']] = TRUE;
    }
    return array_values($recipes);
  }

  public static function live(array $row, int $now): bool {
    return empty($row['revoked_at']) && (int) ($row['expires'] ?? 0) > $now;
  }

  /** Strict replacement, including attribute contexts. Never execute bindings. */
  public static function render(string $html, array $bindings): string {
    $replace = [];
    foreach (self::bindings($bindings) as $key => $value) if (is_string($value)) $replace['{{' . $key . '}}'] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = strtr($html, $replace);
    if (preg_match('/\{\{[^}]+\}\}/', $html)) throw new \RuntimeException('unknown_sample_placeholder');
    return $html;
  }

}
