<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Verifies final Build DNA before a runner-bound proof callback can advance.
 *
 * This is deliberately FAMtastic-side only. It does not alter Site Studio's
 * recipe engine; it verifies the additive proof_runner envelope Site Studio or
 * another provider returns through the existing signed callback boundary.
 */
final class ProofRunnerCallbackVerifier {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly BuildTelemetryService $telemetry,
    private readonly OperationalLedger $ledger,
  ) {}

  /**
   * Validates a source-bound provider completion and projects final Build DNA.
   *
   * Legacy callbacks without a runner-linked Build DNA record stay visible as
   * legacy. A campaign created by the canonical runner always has such a
   * record, so it cannot bypass this verifier by omitting its envelope.
   */
  public function verify(array $callback): array {
    $campaignId = trim((string) ($callback['campaign_id'] ?? ''));
    if ($campaignId === '') {
      throw new \InvalidArgumentException('Proof runner callback requires campaign_id.');
    }
    $campaign = $this->campaign($campaignId);
    $registered = $this->telemetry->loadBuildDnaForCampaign((int) $campaign->id());
    $runner = (array) ($callback['proof_runner'] ?? []);
    if (!$registered) {
      return ['status' => 'legacy_callback_not_runner_bound'];
    }
    $pending = (array) $registered['manifest'];
    $expectedBuildId = (string) ($pending['build_id'] ?? '');
    if ($expectedBuildId === '' || ($pending['classification'] ?? '') === 'local_contract_fixture') {
      throw new \InvalidArgumentException('A local fixture or incomplete Build DNA record cannot complete a proof campaign.');
    }
    if (($registered['record']['status'] ?? '') !== 'preflight') {
      throw new \InvalidArgumentException('The runner-bound proof campaign no longer has a pending preflight record.');
    }

    if ((string) ($runner['build_id'] ?? '') !== $expectedBuildId) {
      throw new \InvalidArgumentException('Proof callback Build DNA identity does not match its prepared runner contract.');
    }
    $expectedContractHash = (string) ($pending['artifacts'][0]['sha256'] ?? '');
    if ($expectedContractHash === '' || !hash_equals($expectedContractHash, (string) ($runner['contract_sha256'] ?? ''))) {
      throw new \InvalidArgumentException('Proof callback contract checksum does not match the source-bound runner contract.');
    }

    $final = (array) ($callback['build_dna'] ?? []);
    $this->assertFinalBuildDna($final, $pending, $campaign, $callback);
    $this->telemetry->recordBuildDna($final);
    $finalJson = json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $finalHash = hash('sha256', $finalJson);
    $this->ledger->recordEvent(
      'proof-runner:callback-verified:' . $expectedBuildId . ':' . (string) ($callback['event_id'] ?? ''),
      'proof.runner.callback_verified',
      [
        'build_id' => $expectedBuildId,
        'campaign_id' => $campaignId,
        'proof_campaign_id' => (int) $campaign->id(),
        'build_dna_hash' => $finalHash,
        'profile_id' => (string) ($pending['recipe']['profile_id'] ?? ''),
      ],
      (int) $campaign->get('prospect_id')->target_id,
      (int) $campaign->id(),
    );
    return [
      'status' => 'verified',
      'build_id' => $expectedBuildId,
      'build_dna_hash' => $finalHash,
      'profile_id' => (string) ($pending['recipe']['profile_id'] ?? ''),
      // These values are copied only after the final DNA has been checked
      // against the pending immutable contract. Downstream delivery code must
      // use them instead of inferring a request/delivery from the prospect.
      'proof_phase' => (string) ($pending['run']['source_correlation']['proof_phase'] ?? ''),
      'source_correlation' => (array) ($pending['run']['source_correlation'] ?? []),
      'lineage' => (array) ($pending['lineage'] ?? []),
      'direction_contract' => (array) ($pending['recipe']['direction_contract'] ?? []),
    ];
  }

  private function assertFinalBuildDna(array $final, array $pending, object $campaign, array $callback): void {
    if (($final['schema'] ?? '') !== 'famtastic.build-dna.v1' || (string) ($final['build_id'] ?? '') !== (string) ($pending['build_id'] ?? '')) {
      throw new \InvalidArgumentException('Proof callback must return the complete Build DNA record for the prepared build_id.');
    }
    if (($final['classification'] ?? '') !== 'production_proof_completion') {
      throw new \InvalidArgumentException('Runner-bound proof completion must declare classification=production_proof_completion.');
    }
    $run = (array) ($final['run'] ?? []);
    if (($run['completion_state'] ?? '') !== 'provider_completed') {
      throw new \InvalidArgumentException('Runner-bound proof completion must declare run.completion_state=provider_completed.');
    }
    $allowedFinalStates = ['passed', 'complete', 'completed'];
    if (!in_array(mb_strtolower((string) ($run['status'] ?? '')), $allowedFinalStates, TRUE)) {
      throw new \InvalidArgumentException('Proof callback Build DNA must report a completed run.');
    }
    if ((int) ($run['prospect_id'] ?? 0) !== (int) $campaign->get('prospect_id')->target_id || (int) ($run['proof_campaign_id'] ?? 0) !== (int) $campaign->id() || (string) ($run['campaign_id'] ?? '') !== (string) $campaign->get('campaign_id')->value) {
      throw new \InvalidArgumentException('Proof callback Build DNA source correlation does not match the campaign.');
    }
    $pendingRun = (array) ($pending['run'] ?? []);
    if ((int) ($pendingRun['prospect_id'] ?? 0) !== (int) $campaign->get('prospect_id')->target_id) {
      throw new \InvalidArgumentException('Prepared proof runner Build DNA prospect does not match callback campaign.');
    }
    if (!empty($pendingRun['proof_campaign_id']) && (int) $pendingRun['proof_campaign_id'] !== (int) $campaign->id()) {
      throw new \InvalidArgumentException('Prepared proof runner Build DNA is linked to another proof campaign.');
    }
    if ((string) ($final['recipe']['routine'] ?? '') !== (string) ($pending['recipe']['routine'] ?? '')
      || !ProofRunnerContractService::isSupportedRoutine((string) ($final['recipe']['routine'] ?? ''))
      || (string) ($final['recipe']['profile_id'] ?? '') !== (string) ($pending['recipe']['profile_id'] ?? '')) {
      throw new \InvalidArgumentException('Proof callback Build DNA routine or profile differs from the prepared contract.');
    }
    $expectedSource = (array) ($pending['run']['source_correlation'] ?? []);
    $actualSource = (array) ($run['source_correlation'] ?? []);
    if (($run['source_type'] ?? '') !== ($pendingRun['source_type'] ?? '')) {
      throw new \InvalidArgumentException('Proof callback Build DNA source type does not match the prepared contract.');
    }
    foreach (['prospect_id', 'type', 'proof_phase', 'lineage_hash', 'source_preview_delivery_id', 'public_preview_delivery_id', 'parent_public_proof_campaign_id', 'parent_public_campaign_key', 'parent_public_build_dna_id', 'parent_public_build_dna_hash', 'intake_id', 'website_request_id', 'website_request_public_id'] as $key) {
      if (array_key_exists($key, $expectedSource) && (string) ($actualSource[$key] ?? '') !== (string) $expectedSource[$key]) {
        throw new \InvalidArgumentException('Proof callback Build DNA source correlation differs at ' . $key . '.');
      }
    }
    if ((array) ($final['lineage'] ?? []) !== (array) ($pending['lineage'] ?? [])) {
      throw new \InvalidArgumentException('Proof callback Build DNA lineage does not match the prepared detailed source snapshot.');
    }
    if (!is_array($final['artifacts'] ?? NULL) || count($final['artifacts']) < 1 || !is_array($final['stages'] ?? NULL) || count($final['stages']) < 1) {
      throw new \InvalidArgumentException('Proof callback Build DNA must include artifacts and stage evidence.');
    }
    foreach ($final['artifacts'] as $artifact) {
      if (!is_array($artifact) || !preg_match('/^[a-f0-9]{64}$/', (string) ($artifact['sha256'] ?? ''))) {
        throw new \InvalidArgumentException('Proof callback Build DNA has an invalid artifact checksum.');
      }
    }
    $capabilities = [];
    foreach ($final['stages'] as $stage) {
      if (!is_array($stage) || trim((string) ($stage['stage_id'] ?? '')) === '' || trim((string) ($stage['capability'] ?? '')) === '' || !is_array($stage['execution'] ?? NULL) || !is_array($stage['result'] ?? NULL)) {
        throw new \InvalidArgumentException('Proof callback Build DNA has an invalid stage record.');
      }
      if (in_array(mb_strtolower((string) ($stage['result']['status'] ?? '')), ['failed', 'gated'], TRUE)) {
        throw new \InvalidArgumentException('Proof callback Build DNA contains an unclosed failed or gated stage.');
      }
      $capabilities[] = (string) $stage['capability'];
    }
    foreach (['browser_qa', 'visual_review'] as $required) {
      if (!in_array($required, $capabilities, TRUE)) {
        throw new \InvalidArgumentException('Proof callback Build DNA is missing required ' . $required . ' evidence.');
      }
    }
    $this->assertNoFixtureOrMockEvidence($final);
    $this->assertIndependentQualityDecision($final);
    $this->assertVariantArtifactLineage($callback, $final, $pending);
    if (!is_array($final['retrieval'] ?? NULL) || !is_array($final['integrity'] ?? NULL) || ($final['integrity']['artifact_hash_algorithm'] ?? '') !== 'sha256') {
      throw new \InvalidArgumentException('Proof callback Build DNA retrieval or integrity contract is incomplete.');
    }
  }

  /**
   * Requires the returned page bytes to be cryptographically represented in
   * final Build DNA. A callback cannot attach a pretty manifest for one set
   * of HTML while FAMtastic writes another set into the proof campaign.
   */
  private function assertVariantArtifactLineage(array $callback, array $final, array $pending): void {
    $expectedDirections = array_keys((array) ($pending['recipe']['direction_contract'] ?? []));
    sort($expectedDirections);
    $variants = (array) ($callback['variants'] ?? []);
    if (count($variants) !== count($expectedDirections)) {
      throw new \InvalidArgumentException('Runner-bound callback does not contain the contracted number of variants.');
    }
    $variantHashes = [];
    foreach ($variants as $variant) {
      if (!is_array($variant)) {
        throw new \InvalidArgumentException('Runner-bound callback variants must be objects.');
      }
      $direction = strtolower(trim((string) ($variant['direction_id'] ?? '')));
      $html = (string) ($variant['html'] ?? '');
      $declared = strtolower(trim((string) ($variant['artifact_sha256'] ?? '')));
      if (!in_array($direction, $expectedDirections, TRUE) || isset($variantHashes[$direction])) {
        throw new \InvalidArgumentException('Runner-bound callback variants must match each contracted direction exactly once.');
      }
      if ($html === '' || !preg_match('/^[a-f0-9]{64}$/', $declared) || !hash_equals($declared, hash('sha256', $html))) {
        throw new \InvalidArgumentException('Runner-bound callback HTML does not match its declared artifact_sha256.');
      }
      $variantHashes[$direction] = $declared;
    }
    ksort($variantHashes);
    if (array_keys($variantHashes) !== $expectedDirections) {
      throw new \InvalidArgumentException('Runner-bound callback directions differ from the prepared contract.');
    }
    $finalArtifacts = [];
    foreach ((array) ($final['artifacts'] ?? []) as $artifact) {
      if (!is_array($artifact) || ($artifact['role'] ?? '') !== 'proof_html') {
        continue;
      }
      $direction = strtolower(trim((string) ($artifact['direction_id'] ?? '')));
      $sha = strtolower(trim((string) ($artifact['sha256'] ?? '')));
      if ($direction !== '' && $sha !== '') {
        $finalArtifacts[$direction][] = $sha;
      }
    }
    foreach ($variantHashes as $direction => $sha) {
      if (!in_array($sha, $finalArtifacts[$direction] ?? [], TRUE)) {
        throw new \InvalidArgumentException('Final Build DNA is missing the matching proof_html artifact hash for direction ' . $direction . '.');
      }
    }
  }

  /** Requires an explicit, independent quality decision rather than a label. */
  private function assertIndependentQualityDecision(array $final): void {
    $quality = (array) ($final['quality'] ?? []);
    $technical = (array) ($quality['technical'] ?? []);
    $visual = (array) ($quality['visual'] ?? []);
    if (!$this->isPassed((string) ($quality['status'] ?? '')) || !$this->isPassed((string) ($technical['status'] ?? ''))) {
      throw new \InvalidArgumentException('Proof callback Build DNA quality status and technical QA must explicitly pass.');
    }
    if (($visual['independent'] ?? FALSE) !== TRUE
      || !$this->isPassed((string) ($visual['status'] ?? ''))
      || trim((string) ($visual['decision'] ?? '')) === ''
      || trim((string) ($visual['reviewer'] ?? '')) === '') {
      throw new \InvalidArgumentException('Proof callback Build DNA requires an explicit passing independent visual review decision and reviewer.');
    }
  }

  /**
   * A callback cannot turn a labelled fixture into a production completion by
   * changing only its top-level classification. The final evidence record is
   * deliberately inspected at its formal provenance fields rather than
   * searching free-form customer copy or research notes.
   */
  private function assertNoFixtureOrMockEvidence(array $final): void {
    $run = (array) ($final['run'] ?? []);
    $quality = (array) ($final['quality'] ?? []);
    $visual = (array) ($quality['visual'] ?? []);
    $evidence = [
      'run.execution_class' => $run['execution_class'] ?? '',
      'run.environment' => $run['environment'] ?? '',
      'run.provider_mode' => $run['provider_mode'] ?? '',
      'run.evidence_level' => $run['evidence_level'] ?? '',
      'quality.visual.reviewer' => $visual['reviewer'] ?? '',
      'quality.visual.review_type' => $visual['review_type'] ?? '',
    ];
    foreach ((array) ($final['stages'] ?? []) as $index => $stage) {
      $execution = is_array($stage['execution'] ?? NULL) ? $stage['execution'] : [];
      $provider = is_array($execution['provider'] ?? NULL) ? $execution['provider'] : [];
      $model = is_array($execution['model'] ?? NULL) ? $execution['model'] : [];
      $timing = is_array($execution['timing'] ?? NULL) ? $execution['timing'] : [];
      $cost = is_array($execution['cost'] ?? NULL) ? $execution['cost'] : [];
      $result = is_array($stage['result'] ?? NULL) ? $stage['result'] : [];
      $prefix = 'stages.' . $index;
      $evidence += [
        $prefix . '.stage_id' => $stage['stage_id'] ?? '',
        $prefix . '.execution.kind' => $execution['kind'] ?? '',
        $prefix . '.execution.provider.id' => $provider['id'] ?? '',
        $prefix . '.execution.provider.mode' => $provider['mode'] ?? '',
        $prefix . '.execution.provider.environment' => $provider['environment'] ?? '',
        $prefix . '.execution.model.id' => $model['id'] ?? '',
        $prefix . '.execution.model.status' => $model['status'] ?? '',
        $prefix . '.execution.timing.status' => $timing['status'] ?? '',
        $prefix . '.execution.cost.status' => $cost['status'] ?? '',
        $prefix . '.result.status' => $result['status'] ?? '',
        $prefix . '.result.evidence_class' => $result['evidence_class'] ?? '',
      ];
    }
    foreach ((array) ($final['artifacts'] ?? []) as $index => $artifact) {
      if (!is_array($artifact)) {
        continue;
      }
      $evidence['artifacts.' . $index . '.role'] = $artifact['role'] ?? '';
      $evidence['artifacts.' . $index . '.path'] = $artifact['path'] ?? '';
      $evidence['artifacts.' . $index . '.rights_status'] = $artifact['rights_status'] ?? '';
      $evidence['artifacts.' . $index . '.provenance'] = $artifact['provenance'] ?? '';
    }
    foreach ((array) ($final['retrieval'] ?? []) as $surface => $details) {
      if (!is_array($details)) {
        continue;
      }
      $evidence['retrieval.' . $surface . '.status'] = $details['status'] ?? '';
      $evidence['retrieval.' . $surface . '.mode'] = $details['mode'] ?? '';
    }
    foreach ($evidence as $field => $value) {
      if (is_string($value) && $this->hasNonProductionMarker($value)) {
        throw new \InvalidArgumentException('Proof callback Build DNA carries non-production fixture/mock/test evidence at ' . $field . '.');
      }
    }
  }

  /** Identifies explicit test doubles without treating ordinary copy as evidence. */
  private function hasNonProductionMarker(string $value): bool {
    return preg_match('/(?:^|[^a-z0-9])(fixture|mock|stub|fake|simulat(?:e|ed|ion)|test(?:ing|[_ -]?mode)?|loopback|not[_ -]?a[_ -]?real)(?:$|[^a-z0-9])/i', $value) === 1;
  }

  private function isPassed(string $status): bool {
    return in_array(mb_strtolower(trim($status)), ['passed', 'pass', 'approved', 'complete', 'completed'], TRUE);
  }

  private function campaign(string $campaignId): object {
    $storage = $this->entities->getStorage('proof_campaign');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('campaign_id', $campaignId)->range(0, 1)->execute();
    $campaign = $ids ? $storage->load(reset($ids)) : NULL;
    if (!$campaign) {
      throw new \InvalidArgumentException('Proof callback campaign is unknown.');
    }
    return $campaign;
  }

}
