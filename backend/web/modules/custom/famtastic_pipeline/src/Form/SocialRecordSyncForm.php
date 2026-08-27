<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * In-admin sync for the canonical 17-day campaign manifest.
 *
 * Reads marketing/campaigns/55-cents-17-day/manifest.json and updates
 * famtastic_social_record idempotently, preserving owner approval decisions.
 */
final class SocialRecordSyncForm extends ConfirmFormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  public function getFormId(): string {
    return 'famtastic_social_records_sync';
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Sync campaign manifest records?');
  }

  public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('This imports or updates all 68 campaign moments from the canonical manifest. Existing approval decisions in the database are preserved.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.marketing');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $candidates = [
      dirname(\Drupal::root(), 2) . '/marketing/campaigns/55-cents-17-day/manifest.json',
      dirname(\Drupal::root()) . '/marketing/campaigns/55-cents-17-day/manifest.json',
      \Drupal::root() . '/../marketing/campaigns/55-cents-17-day/manifest.json',
    ];

    $manifestPath = NULL;
    foreach ($candidates as $path) {
      if (is_file($path)) {
        $manifestPath = $path;
        break;
      }
    }

    if (!$manifestPath) {
      $this->messenger()->addError($this->t('Campaign manifest file could not be found.'));
      $form_state->setRedirect('famtastic_pipeline.marketing');
      return;
    }

    $raw = file_get_contents($manifestPath);
    $manifest = $raw ? json_decode($raw, TRUE) : NULL;
    if (!is_array($manifest) || empty($manifest['records'])) {
      $this->messenger()->addError($this->t('Invalid or empty manifest file.'));
      $form_state->setRedirect('famtastic_pipeline.marketing');
      return;
    }

    $now = $this->time->getRequestTime();
    $count = 0;
    foreach ($manifest['records'] as $record) {
      if (empty($record['content_id'])) {
        continue;
      }
      $existing = $this->database->select('famtastic_social_record', 'r')
        ->fields('r', ['id', 'approval_content', 'approval_media', 'approval_publish'])
        ->condition('content_id', (string) $record['content_id'])
        ->execute()->fetchAssoc();

      $fields = [
        'day' => (int) ($record['day'] ?? 0),
        'moment' => (string) ($record['moment'] ?? ''),
        'theme' => (string) ($record['theme'] ?? ''),
        'promise' => (string) ($record['promise'] ?? ''),
        'scheduled_time_et' => (string) ($record['suggested_time_et'] ?? ''),
        'state' => (string) ($record['state'] ?? 'idea'),
        'postiz_draft_id' => (string) ($record['provider_ids']['postiz_draft_id'] ?? ''),
        'asset_variants' => json_encode($record['asset_variants'] ?? [], JSON_THROW_ON_ERROR),
        'changed' => $now,
      ];

      if ($existing) {
        $fields['approval_content'] = (int) $existing['approval_content'];
        $fields['approval_media'] = (int) $existing['approval_media'];
        $fields['approval_publish'] = (int) $existing['approval_publish'];
        $this->database->update('famtastic_social_record')->fields($fields)->condition('id', $existing['id'])->execute();
      }
      else {
        $fields += [
          'content_id' => (string) $record['content_id'],
          'approval_content' => (int) (!empty($record['approval']['content'])),
          'approval_media' => (int) (!empty($record['approval']['media'])),
          'approval_publish' => (int) (!empty($record['approval']['publish'])),
        ];
        $this->database->insert('famtastic_social_record')->fields($fields)->execute();
      }
      $count++;
    }

    $this->messenger()->addStatus($this->t('Successfully synchronized @count campaign records from the manifest.', ['@count' => $count]));
    $form_state->setRedirect('famtastic_pipeline.marketing');
  }

}
