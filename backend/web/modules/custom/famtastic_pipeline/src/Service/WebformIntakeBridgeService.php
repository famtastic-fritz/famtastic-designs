<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Bridges public decoupled intakes & Solution Finder submissions into
 * native Drupal Webform submissions for audit, CRM handlers, and export.
 */
final class WebformIntakeBridgeService {

  private readonly LoggerChannelInterface $logger;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('famtastic_pipeline');
  }

  /**
   * Records a webform submission if a matching webform exists in Drupal.
   */
  public function recordSubmission(string $webformId, array $values): ?int {
    try {
      if (!$this->entityTypeManager->hasDefinition('webform') || !$this->entityTypeManager->hasDefinition('webform_submission')) {
        return NULL;
      }

      $webformStorage = $this->entityTypeManager->getStorage('webform');
      $webform = $webformStorage->load($webformId);
      if (!$webform) {
        return NULL;
      }

      $submissionStorage = $this->entityTypeManager->getStorage('webform_submission');
      /** @var \Drupal\webform\WebformSubmissionInterface $submission */
      $submission = $submissionStorage->create([
        'webform_id' => $webformId,
        'data' => $values,
      ]);
      $submission->save();

      return (int) $submission->id();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not record webform submission for @webform: @message', [
        '@webform' => $webformId,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
