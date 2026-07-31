<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drupal\famtastic_pipeline\Entity\Prospect;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the FAMtastic pipeline.
 */
class PipelineCommands extends DrushCommands {

  /**
   * Runs a bounded batch of durable automation jobs.
   */
  #[CLI\Command(name: 'famtastic:jobs-run', aliases: ['fjr'])]
  #[CLI\Option(name: 'limit', description: 'Maximum jobs to process (1-100).')]
  #[CLI\Option(name: 'type', description: 'Optional exact job type filter.')]
  public function jobsRun(array $options = [
    'limit' => 25,
    'type' => '',
  ]): int {
    /** @var \Drupal\famtastic_pipeline\Service\AutomationWorker $worker */
    $worker = \Drupal::service('famtastic_pipeline.automation_worker');
    $results = $worker->run((int) $options['limit'], $options['type'] !== '' ? (string) $options['type'] : NULL);
    $this->io()->writeln(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $failures = array_filter($results, fn (array $result) => $result['status'] === 'failed');
    if ($failures) {
      $this->logger()->error(dt('@count job(s) exhausted retries.', ['@count' => count($failures)]));
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('Processed @count automation job(s).', ['@count' => count($results)]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Imports, normalizes, deduplicates, suppresses, and scores a lead CSV.
   */
  #[CLI\Command(name: 'famtastic:leads-import', aliases: ['fli'])]
  #[CLI\Argument(name: 'path', description: 'Absolute or current-working-directory CSV path.')]
  #[CLI\Option(name: 'source', description: 'Lawful public or licensed source identifier.')]
  #[CLI\Option(name: 'campaign', description: 'Campaign attribution key.')]
  #[CLI\Option(name: 'dry-run', description: 'Validate and score without writing records.')]
  #[CLI\Usage(name: 'drush fli leads.csv --source=licensed-directory --campaign=az-launch-01 --dry-run', description: 'Preview a bounded lead import.')]
  public function leadsImport(string $path, array $options = [
    'source' => '',
    'campaign' => '',
    'dry-run' => FALSE,
  ]): int {
    if ($options['source'] === '' || $options['campaign'] === '') {
      $this->logger()->error('--source and --campaign are required.');
      return self::EXIT_FAILURE;
    }
    /** @var \Drupal\famtastic_pipeline\Service\LeadIngestionService $ingestion */
    $ingestion = \Drupal::service('famtastic_pipeline.lead_ingestion');
    try {
      $result = $ingestion->importCsv(
        $path,
        (string) $options['source'],
        (string) $options['campaign'],
        (bool) $options['dry-run'],
      );
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logger()->success(dt(
      'Processed @total row(s): @counts.',
      [
        '@total' => $result['total'],
        '@counts' => json_encode($result['counts']),
      ],
    ));
    return self::EXIT_SUCCESS;
  }

  /**
   * Creates a prospect from publicly discovered info and issues a secure link.
   */
  #[CLI\Command(name: 'famtastic:prospect-create', aliases: ['fpc'])]
  #[CLI\Option(name: 'business-name', description: 'Business name (required).')]
  #[CLI\Option(name: 'category', description: 'Business category.')]
  #[CLI\Option(name: 'description', description: 'Business description.')]
  #[CLI\Option(name: 'address', description: 'Address.')]
  #[CLI\Option(name: 'service-area', description: 'Service area.')]
  #[CLI\Option(name: 'phone', description: 'Public phone.')]
  #[CLI\Option(name: 'email', description: 'Public email.')]
  #[CLI\Option(name: 'website', description: 'Existing website URL.')]
  #[CLI\Option(name: 'hours', description: 'Hours.')]
  #[CLI\Option(name: 'social', description: 'Social links (JSON or newline list).')]
  #[CLI\Option(name: 'campaign', description: 'Campaign identifier.')]
  #[CLI\Option(name: 'source', description: 'Discovery source (google, directory, referral, social).')]
  #[CLI\Option(name: 'notes', description: 'Internal discovery notes (never shown to the prospect).')]
  #[CLI\Usage(name: 'drush fpc --business-name="Joe\'s Plumbing" --category="Plumber" --source=google', description: 'Create a prospect and print its secure link.')]
  public function prospectCreate(array $options = [
    'business-name' => '',
    'category' => '',
    'description' => '',
    'address' => '',
    'service-area' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'hours' => '',
    'social' => '',
    'campaign' => '',
    'source' => '',
    'notes' => '',
  ]): int {
    if (empty($options['business-name'])) {
      $this->logger()->error('--business-name is required.');
      return self::EXIT_FAILURE;
    }

    /** @var \Drupal\famtastic_pipeline\Service\TokenManager $tokenManager */
    $tokenManager = \Drupal::service('famtastic_pipeline.token_manager');
    $token = $tokenManager->generate();

    $prospect = Prospect::create([
      'business_name' => $options['business-name'],
      'business_category' => $options['category'],
      'business_description' => $options['description'],
      'address' => $options['address'],
      'service_area' => $options['service-area'],
      'public_phone' => $options['phone'],
      'public_email' => $options['email'],
      'website_url' => $options['website'],
      'hours' => $options['hours'],
      'social_links' => $options['social'],
      'campaign' => $options['campaign'],
      'source' => $options['source'],
      'discovery_notes' => $options['notes'],
      'token_hash' => $token['hash'],
      'token_expires' => $token['expires'],
      'token_revoked' => FALSE,
      'status' => 'new',
    ]);
    $prospect->save();

    $link = $tokenManager->link($token['raw']);
    $this->logger()->success(dt('Prospect #@id created.', ['@id' => $prospect->id()]));
    $this->io()->writeln('');
    $this->io()->writeln('  Prospect ID : ' . $prospect->id());
    $this->io()->writeln('  Secure link : ' . $link);
    $this->io()->writeln('  Raw token   : ' . $token['raw']);
    $this->io()->writeln('');
    $this->io()->writeln('<comment>Only the SHA-256 hash of the token is stored. Send the link once.</comment>');

    return self::EXIT_SUCCESS;
  }

  /**
   * Expires all active proof campaigns past their expiry timestamp.
   */
  #[CLI\Command(name: 'proof-campaign:expire', aliases: ['pce'])]
  #[CLI\Usage(name: 'drush proof-campaign:expire', description: 'Mark expired proof campaigns and print the count.')]
  public function proofCampaignExpire(): int {
    /** @var \Drupal\famtastic_pipeline\Service\ProofCampaignService $service */
    $service = \Drupal::service('famtastic_pipeline.proof_campaign_service');
    $count = $service->expireActive();
    $this->logger()->success(dt('Expired @count proof campaign(s).', ['@count' => $count]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Generates the Site Studio request (brief + JSON) for a prospect.
   */
  #[CLI\Command(name: 'famtastic:studio-generate', aliases: ['fsg'])]
  #[CLI\Argument(name: 'prospectId', description: 'The prospect entity id.')]
  #[CLI\Usage(name: 'drush fsg 1', description: 'Generate the Site Studio request for prospect 1.')]
  public function studioGenerate(int $prospectId): int {
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      $this->logger()->error(dt('Prospect @id not found.', ['@id' => $prospectId]));
      return self::EXIT_FAILURE;
    }
    /** @var \Drupal\famtastic_pipeline\Service\StudioRequestGenerator $generator */
    $generator = \Drupal::service('famtastic_pipeline.studio_generator');
    $result = $generator->generate($prospect);
    $this->logger()->success(dt('Project #@id generated.', ['@id' => $result['project']->id()]));
    $this->io()->writeln('  Exported to : ' . ($result['handoff']['location'] ?? 'n/a'));
    return self::EXIT_SUCCESS;
  }

}
