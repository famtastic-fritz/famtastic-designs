<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Requeues a failed/dead-lettered notification: resets attempts and returns
 * it to the queued state for the next dispatch cycle. One-click triage for
 * the mail pipeline (BRUTAL-REVIEW-2026-08-24 gap #8).
 */
final class NotificationRetryForm extends ConfirmFormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('datetime.time'));
  }

  private array $row = [];

  public function getFormId(): string {
    return 'famtastic_notification_retry';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $id = NULL): array {
    $this->row = $this->database->select('famtastic_notification_outbox', 'n')
      ->fields('n')
      ->condition('id', (int) $id)
      ->execute()->fetchAssoc() ?: [];
    if (!$this->row) {
      $form['missing'] = ['#markup' => '<p>' . $this->t('Notification #@id not found.', ['@id' => $id]) . '</p>'];
      return $form;
    }
    $form['context'] = [
      '#markup' => '<p><strong>' . Html::escape((string) $this->row['subject']) . '</strong><br>'
        . Html::escape((string) $this->row['recipient']) . '<br><small>'
        . $this->t('status @status after @attempts attempt(s). Last error: @err', [
          '@status' => $this->row['status'],
          '@attempts' => $this->row['attempts'],
          '@err' => $this->row['last_error'] ?: 'none',
        ]) . '</small></p>',
    ];
    return $form + parent::buildForm($form, $form_state);
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Retry notification #@id', ['@id' => $this->row['id'] ?? '?']);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'notifications']);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_notification_outbox')
      ->fields(['status' => 'queued', 'attempts' => 0, 'available_at' => $now, 'last_error' => NULL, 'changed' => $now])
      ->condition('id', (int) $this->row['id'])
      ->execute();
    $this->messenger()->addStatus($this->t('Notification #@id requeued for the next dispatch cycle.', ['@id' => $this->row['id']]));
    $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'notifications']);
  }

}
