<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Validates the source-backed input contract for an anonymous cold cohort.
 *
 * This parser deliberately has no mailer, provider, entity, or database
 * dependency. A research/model tool may prepare a seed, but it cannot use a
 * seed to approve, dispatch, or otherwise authorize customer communication.
 */
final class ColdProofCampaignSeedValidator {

  public const SCHEMA_VERSION = 'famtastic.cold_proof_campaign_seed.v1';

  /**
   * The only currently supported cold-campaign purpose.
   *
   * A verified-cold proof is not a generic website-redesign campaign. Its
   * current commercial promise is a first owned website, so accepting a lead
   * with an independent website would make the evidence and offer disagree.
   * Future campaign purposes need their own explicit contract; they must not
   * be introduced by loosening this profile.
   */
  public const CAMPAIGN_PROFILE_FIRST_SITE = 'first_site';

  /**
   * These are eligibility descriptions, not creative or sales diagnoses.
   * A deploy may narrow this list through the proof-cohorts configuration,
   * but it can never add an arbitrary model-authored status.
   */
  private const WEBSITE_OBSERVATION_STATUSES = [
    'confirmed_absent',
    'observed_outdated',
    'verified_present',
    'exploratory',
  ];

  public function __construct(private readonly ?ConfigFactoryInterface $configFactory = NULL) {}

  /**
   * @return array{schema_version:string,cohort:array,leads:list<array>}
   */
  public function validate(array $seed): array {
    if (($seed['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
      throw new \InvalidArgumentException('Cold proof seed has an unsupported schema_version.');
    }
    $this->assertNoDeliveryAuthority($seed);
    $cohort = $this->cohort((array) ($seed['cohort'] ?? []));
    $leads = $seed['leads'] ?? NULL;
    if (!is_array($leads) || $leads === [] || count($leads) > 1000) {
      throw new \InvalidArgumentException('Cold proof seed requires between one and 1000 leads.');
    }
    $seen = [];
    $normalized = [];
    foreach (array_values($leads) as $offset => $lead) {
      if (!is_array($lead)) {
        throw new \InvalidArgumentException(sprintf('Cold proof lead %d must be an object.', $offset + 1));
      }
      $item = $this->lead($lead, $offset + 1, $cohort['scheduled_release_at'], $cohort['campaign_profile']);
      if (isset($seen[$item['source_record_id']])) {
        throw new \InvalidArgumentException('Cold proof seed has a duplicate source_record_id: ' . $item['source_record_id']);
      }
      $seen[$item['source_record_id']] = TRUE;
      $normalized[] = $item;
    }
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'cohort' => $cohort,
      'leads' => $normalized,
    ];
  }

  private function cohort(array $cohort): array {
    $key = $this->key($cohort['cohort_key'] ?? '', 'cohort_key');
    $campaign = $this->key($cohort['campaign_key'] ?? '', 'campaign_key');
    $source = $this->text($cohort['source_name'] ?? '', 128, 'source_name', 2);
    $campaignProfile = strtolower(trim((string) ($cohort['campaign_profile'] ?? self::CAMPAIGN_PROFILE_FIRST_SITE)));
    if ($campaignProfile !== self::CAMPAIGN_PROFILE_FIRST_SITE) {
      throw new \InvalidArgumentException('cohort.campaign_profile must be first_site for the current verified-cold campaign.');
    }
    $profile = trim((string) ($cohort['package_profile'] ?? ''));
    if ($profile !== '' && preg_match('/^[a-z0-9][a-z0-9_.-]{2,127}$/', $profile) !== 1) {
      throw new \InvalidArgumentException('cohort.package_profile is invalid.');
    }
    return [
      'cohort_key' => $key,
      'campaign_key' => $campaign,
      'source_name' => $source,
      'campaign_profile' => $campaignProfile,
      'package_profile' => $profile,
      'scheduled_release_at' => $this->timestamp($cohort['scheduled_release_at'] ?? NULL, 'cohort.scheduled_release_at'),
    ];
  }

  private function lead(array $lead, int $number, ?int $cohortReleaseAt, string $campaignProfile): array {
    $record = $this->text($lead['source_record_id'] ?? '', 255, "leads[$number].source_record_id", 1);
    $email = mb_strtolower(trim((string) ($lead['email'] ?? $lead['public_email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException("leads[$number].email must be a valid recipient email.");
    }
    $source = $this->source((array) ($lead['public_source'] ?? []), $number);
    $websiteObservation = $this->websiteObservation((array) ($lead['website_observation'] ?? []), $number);
    $fact = $this->text($lead['corroborated_fact'] ?? '', 1200, "leads[$number].corroborated_fact", 12);
    $teaser = $this->text($lead['proof_teaser'] ?? '', 600, "leads[$number].proof_teaser", 12);
    $scheduled = $this->timestamp($lead['scheduled_release_at'] ?? NULL, "leads[$number].scheduled_release_at") ?? $cohortReleaseAt;
    $website = trim((string) ($lead['website_url'] ?? $lead['website'] ?? ''));
    if ($website !== '' && !preg_match('#^https?://#i', $website)) {
      $website = 'https://' . $website;
    }
    if ($website !== '' && !$this->isPublicUrl($website)) {
      throw new \InvalidArgumentException("leads[$number].website_url must be a public http(s) URL when supplied.");
    }
    if ($campaignProfile === self::CAMPAIGN_PROFILE_FIRST_SITE && $website !== '') {
      throw new \InvalidArgumentException("leads[$number].website_url must be blank for the first_site campaign profile.");
    }
    if ($campaignProfile === self::CAMPAIGN_PROFILE_FIRST_SITE && $websiteObservation['status'] !== 'confirmed_absent') {
      throw new \InvalidArgumentException("leads[$number].website_observation.status must be confirmed_absent for the first_site campaign profile.");
    }
    if (in_array($websiteObservation['status'], ['observed_outdated', 'verified_present'], TRUE) && $website === '') {
      throw new \InvalidArgumentException("leads[$number].website_url is required for the supplied website observation.");
    }
    if ($websiteObservation['status'] === 'confirmed_absent' && $website !== '') {
      throw new \InvalidArgumentException("leads[$number].website_url cannot be supplied with a confirmed_absent observation.");
    }
    $phone = preg_replace('/[^0-9+]/', '', (string) ($lead['phone'] ?? $lead['public_phone'] ?? '')) ?? '';
    $result = [
      'source_record_id' => $record,
      'business_name' => $this->text($lead['business_name'] ?? '', 255, "leads[$number].business_name", 1),
      'business_category' => $this->text($lead['business_category'] ?? $lead['category'] ?? '', 255, "leads[$number].business_category"),
      'business_description' => $this->text($lead['business_description'] ?? $lead['description'] ?? '', 5000, "leads[$number].business_description"),
      'address' => $this->text($lead['address'] ?? '', 5000, "leads[$number].address"),
      'service_area' => $this->text($lead['service_area'] ?? $lead['city'] ?? '', 255, "leads[$number].service_area"),
      'email' => $email,
      'phone' => $phone,
      'website_url' => $website,
      'website_quality' => $this->text($lead['website_quality'] ?? '', 64, "leads[$number].website_quality"),
      'upgrade_signal' => filter_var($lead['upgrade_signal'] ?? FALSE, FILTER_VALIDATE_BOOL),
      'website_observation' => $websiteObservation,
      'public_source' => $source,
      'corroborated_fact' => $fact,
      'proof_teaser' => $teaser,
      'scheduled_release_at' => $scheduled,
    ];
    $result['evidence_hash'] = hash('sha256', json_encode([
      'source_record_id' => $record,
      'public_source' => $source,
      'website_observation' => $websiteObservation,
      'corroborated_fact' => $fact,
      'proof_teaser' => $teaser,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $result;
  }

  private function source(array $source, int $number): array {
    $url = trim((string) ($source['url'] ?? ''));
    if (!$this->isPublicUrl($url)) {
      throw new \InvalidArgumentException("leads[$number].public_source.url must be a public http(s) URL.");
    }
    $provenance = $this->text($source['provenance'] ?? '', 255, "leads[$number].public_source.provenance", 4);
    $timeframe = $this->text($source['timeframe'] ?? '', 128, "leads[$number].public_source.timeframe", 7);
    if (preg_match('/\b20\d{2}-\d{2}-\d{2}\b/', $timeframe) !== 1) {
      throw new \InvalidArgumentException("leads[$number].public_source.timeframe must include an ISO date (YYYY-MM-DD).");
    }
    $parts = parse_url($url);
    $canonical = is_array($parts) && isset($parts['scheme'], $parts['host'])
      ? $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '')
      : $url;
    return ['url' => $canonical, 'provenance' => $provenance, 'timeframe' => $timeframe];
  }

  /** Requires an explicit, fact-backed website qualification for cold proofing. */
  private function websiteObservation(array $observation, int $number): array {
    $status = strtolower(trim((string) ($observation['status'] ?? '')));
    if (!in_array($status, $this->websiteObservationStatuses(), TRUE)) {
      throw new \InvalidArgumentException("leads[$number].website_observation.status must be an enabled explicit observation; unknown cannot qualify for a cold proof.");
    }
    $fact = $this->text($observation['fact'] ?? '', 600, "leads[$number].website_observation.fact", 12);
    return ['status' => $status, 'fact' => $fact];
  }

  /** @return list<string> */
  private function websiteObservationStatuses(): array {
    if (!$this->configFactory) {
      return self::WEBSITE_OBSERVATION_STATUSES;
    }
    $configured = (array) $this->configFactory
      ->get('famtastic_pipeline.proof_cohorts')
      ->get('cold.website_observation_statuses');
    $statuses = array_values(array_unique(array_filter(array_map(
      static fn (mixed $status): string => strtolower(trim((string) $status)),
      $configured,
    ), static fn (string $status): bool => in_array($status, self::WEBSITE_OBSERVATION_STATUSES, TRUE))));
    return $statuses ?: self::WEBSITE_OBSERVATION_STATUSES;
  }

  private function key(mixed $value, string $field): string {
    $value = trim((string) $value);
    if (preg_match('/^[a-z0-9][a-z0-9_.-]{2,127}$/', $value) !== 1) {
      throw new \InvalidArgumentException($field . ' must be a 3-128 character lowercase key.');
    }
    return $value;
  }

  private function text(mixed $value, int $maximum, string $field, int $minimum = 0): string {
    $value = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value))) ?? '';
    $value = mb_substr($value, 0, $maximum);
    if (mb_strlen($value) < $minimum) {
      throw new \InvalidArgumentException($field . ' is required.');
    }
    return $value;
  }

  private function timestamp(mixed $value, string $field): ?int {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
      return NULL;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
      throw new \InvalidArgumentException($field . ' must be an ISO-8601 timestamp with timezone.');
    }
    try {
      return (new \DateTimeImmutable($value))->getTimestamp();
    }
    catch (\Throwable) {
      throw new \InvalidArgumentException($field . ' is not a valid timestamp.');
    }
  }

  private function isPublicUrl(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return FALSE;
    }
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], TRUE) || $host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
      return FALSE;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
      return FALSE;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== FALSE;
    }
    return TRUE;
  }

  /** Explicitly reject model-authored owner/send authority in input. */
  private function assertNoDeliveryAuthority(array $value, string $path = ''): void {
    $forbidden = ['owner_approved', 'approve', 'approved', 'send', 'dispatch', 'auto_send', 'delivery_authorized'];
    foreach ($value as $key => $item) {
      $name = strtolower((string) $key);
      if (in_array($name, $forbidden, TRUE)) {
        throw new \InvalidArgumentException('Cold proof seed cannot set delivery authority: ' . ($path === '' ? $key : $path . '.' . $key));
      }
      if (is_array($item)) {
        $this->assertNoDeliveryAuthority($item, $path === '' ? (string) $key : $path . '.' . $key);
      }
    }
  }

}
