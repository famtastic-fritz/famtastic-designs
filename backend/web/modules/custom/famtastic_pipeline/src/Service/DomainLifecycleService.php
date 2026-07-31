<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;

/**
 * Records customer domain ownership and verifies delegated DNS/SSL state.
 */
final class DomainLifecycleService {

  public function __construct(
    private readonly Connection $database,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Registers a customer-owned domain without purchasing or mutating DNS.
   */
  public function register(
    int $projectId,
    string $domainName,
    string $ownerName,
    string $registrar,
    string $managementMode,
    array $authorizationEvidence = [],
  ): array {
    $domain = $this->normalize($domainName);
    if ($ownerName === '') {
      throw new \InvalidArgumentException('Customer domain owner name is required.');
    }
    if (!in_array($managementMode, ['customer_managed', 'delegated'], TRUE)) {
      throw new \InvalidArgumentException('Domain management mode must be customer_managed or delegated.');
    }
    if ($managementMode === 'delegated' && empty($authorizationEvidence)) {
      throw new \InvalidArgumentException('Delegated DNS management requires authorization evidence.');
    }
    $deployment = $this->database->select('famtastic_deployment', 'd')
      ->fields('d', ['id'])
      ->condition('project_id', $projectId)
      ->condition('status', 'deployed')
      ->orderBy('deployed_at', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!$deployment) {
      throw new \RuntimeException('A deployed project is required before domain registration.');
    }

    $existing = $this->database->select('famtastic_domain', 'd')
      ->fields('d')
      ->condition('domain_name', $domain)
      ->execute()
      ->fetchAssoc();
    if ($existing) {
      if ((int) $existing['project_id'] !== $projectId || $existing['owner_type'] !== 'customer') {
        throw new \RuntimeException('Domain is already assigned to another project or owner.');
      }
      return $existing;
    }

    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_domain')
      ->fields([
        'project_id' => $projectId,
        'domain_name' => $domain,
        'owner_type' => 'customer',
        'owner_name' => trim($ownerName),
        'registrar' => trim($registrar),
        'management_mode' => $managementMode,
        'authorization_evidence' => json_encode($authorizationEvidence, JSON_THROW_ON_ERROR),
        'dns_status' => 'pending',
        'ssl_status' => 'pending',
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
    $this->ledger->recordEvent(
      'domain.registered:' . $id,
      'domain.registered',
      [
        'domain_id' => $id,
        'domain_name' => $domain,
        'owner_type' => 'customer',
        'management_mode' => $managementMode,
      ],
      projectId: $projectId,
    );
    $this->ledger->enqueue(
      'domain.verify:domain:' . $id,
      'domain.verify',
      ['deployment_id' => (int) $deployment, 'domain_id' => $id],
    );
    return $this->load($id);
  }

  /**
   * Verifies DNS and TLS using read-only evidence; never purchases or mutates.
   */
  public function verifyDeployment(int $deploymentId, ?int $domainId = NULL): array {
    $deployment = $this->database->select('famtastic_deployment', 'd')
      ->fields('d')
      ->condition('id', $deploymentId)
      ->execute()
      ->fetchAssoc();
    if (!$deployment || $deployment['status'] !== 'deployed') {
      throw new \RuntimeException('Domain verification requires a deployed release.');
    }
    $query = $this->database->select('famtastic_domain', 'd')
      ->fields('d')
      ->condition('project_id', (int) $deployment['project_id']);
    if ($domainId !== NULL) {
      $query->condition('id', $domainId);
    }
    $domain = $query->range(0, 1)->execute()->fetchAssoc();
    if (!$domain) {
      return ['status' => 'awaiting_customer_domain', 'deployment_id' => $deploymentId];
    }

    $evidence = $this->verificationEvidence((string) $domain['domain_name']);
    $expected = (string) ($evidence['expected_target'] ?? parse_url((string) $deployment['public_url'], PHP_URL_HOST) ?: '');
    $observed = array_values(array_unique(array_map('strtolower', (array) ($evidence['observed_targets'] ?? []))));
    $dnsStatus = $expected !== '' && in_array(strtolower($expected), $observed, TRUE) ? 'verified' : 'mismatch';
    $sslStatus = !empty($evidence['ssl_valid']) ? 'verified' : 'invalid';
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_domain')
      ->fields([
        'dns_status' => $dnsStatus,
        'ssl_status' => $sslStatus,
        'expires_at' => isset($evidence['certificate_expires_at']) ? (int) $evidence['certificate_expires_at'] : NULL,
        'last_verified_at' => $now,
        'changed' => $now,
      ])
      ->condition('id', $domain['id'])
      ->execute();
    $this->ledger->recordEvent(
      sprintf('domain.verified:%d:%s', $domain['id'], hash('sha256', json_encode($evidence))),
      'domain.verified',
      [
        'domain_id' => (int) $domain['id'],
        'dns_status' => $dnsStatus,
        'ssl_status' => $sslStatus,
        'expected_target' => $expected,
        'observed_targets' => $observed,
        'read_only' => TRUE,
      ],
      projectId: (int) $deployment['project_id'],
    );
    if ($dnsStatus === 'verified' && $sslStatus === 'verified') {
      $this->ledger->enqueue(
        'hosting.activate:project:' . $deployment['project_id'],
        'hosting.activate',
        ['project_id' => (int) $deployment['project_id'], 'domain_id' => (int) $domain['id']],
      );
    }
    return $this->load((int) $domain['id']);
  }

  public function load(int $id): array {
    $record = $this->database->select('famtastic_domain', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      throw new \InvalidArgumentException('Unknown domain.');
    }
    $record['authorization_evidence'] = json_decode((string) ($record['authorization_evidence'] ?: '{}'), TRUE);
    return $record;
  }

  private function normalize(string $domainName): string {
    $domain = strtolower(rtrim(trim($domainName), '.'));
    if (function_exists('idn_to_ascii')) {
      $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: '';
    }
    if (
      $domain === ''
      || filter_var($domain, FILTER_VALIDATE_IP)
      || strlen($domain) > 253
      || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)
    ) {
      throw new \InvalidArgumentException('Invalid customer domain name.');
    }
    return $domain;
  }

  private function verificationEvidence(string $domain): array {
    $mode = getenv('FAMTASTIC_DOMAIN_VERIFY_MODE') ?: Settings::get('famtastic_domain_verify_mode', 'disabled');
    if ($mode !== 'fixture') {
      throw new \RuntimeException('Domain verification is disabled unless an approved read-only verifier is configured.');
    }
    $raw = getenv('FAMTASTIC_DOMAIN_VERIFY_FIXTURE') ?: '{}';
    $fixtures = json_decode($raw, TRUE, flags: JSON_THROW_ON_ERROR);
    if (!isset($fixtures[$domain]) || !is_array($fixtures[$domain])) {
      throw new \RuntimeException('No verification evidence exists for this domain.');
    }
    return $fixtures[$domain];
  }

}
