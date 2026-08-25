<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\LifecycleOperationsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Owner reply to a customer support case (T1 command-center action).
 *
 * Wraps LifecycleOperationsService::staffReply(): posts the staff message to
 * the portal thread, flips the case to waiting_on_customer, and queues the
 * customer notification through the reviewed outbox.
 */
final class SupportReplyForm extends ConfirmFormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly LifecycleOperationsService $lifecycle,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('famtastic_pipeline.lifecycle_operations'));
  }

  private array $case = [];

  public function getFormId(): string {
    return 'famtastic_support_reply';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $case_number = NULL): array {
    $this->case = $this->database->select('famtastic_support_case', 's')
      ->fields('s')
      ->condition('case_number', (string) $case_number)
      ->execute()->fetchAssoc() ?: [];
    if (!$this->case) {
      $form['missing'] = ['#markup' => '<p>' . $this->t('Support case @n not found.', ['@n' => $case_number]) . '</p>'];
      return $form;
    }
    $thread = $this->database->select('famtastic_portal_thread', 't')
      ->fields('t', ['subject'])
      ->condition('id', $this->case['thread_id'])
      ->execute()->fetchAssoc();
    $form['context'] = [
      '#markup' => '<p><strong>' . Html::escape((string) ($thread['subject'] ?? $this->case['case_number'])) . '</strong> · '
        . $this->t('status @status', ['@status' => $this->case['status']]) . '</p>',
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your reply (sent to the customer through the reviewed outbox)'),
      '#rows' => 6,
      '#required' => TRUE,
      '#description' => $this->t('Plain text. The customer can reply directly to the emailed message.'),
    ];
    return $form + parent::buildForm($form, $form_state);
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Reply to case @case', ['@case' => $this->case['case_number'] ?? '?']);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'support']);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('body')) === '') {
      $form_state->setErrorByName('body', $this->t('The reply cannot be empty.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $result = $this->lifecycle->staffReply(
        (string) $this->case['case_number'],
        trim((string) $form_state->getValue('body')),
        (int) $this->currentUser()->id(),
      );
      $this->messenger()->addStatus($this->t('Reply sent on @case — customer notified, case now @status.', [
        '@case' => $result['case_number'], '@status' => $result['status'],
      ]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Reply failed: @m', ['@m' => $e->getMessage()]));
    }
    $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'support']);
  }

}
