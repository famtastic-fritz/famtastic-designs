<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\SupportDraftService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Owner decision form for L0 support drafts — approve edits + queues the
 * reply through the reviewed outbox; reject archives it. Nothing sends
 * automatically (AUTONOMOUS_CUSTOMER_SERVICE step B2).
 */
final class SupportDraftDecisionForm extends FormBase {

  public function __construct(
    private readonly SupportDraftService $supportDrafts,
    private readonly \Drupal\Core\Database\Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.support_drafts'),
      $container->get('database'),
    );
  }

  public function getFormId(): string {
    return 'famtastic_support_draft_decision';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $id = 0): array {
    $draft = $this->database->select('famtastic_support_draft', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->execute()->fetchAssoc();
    if (!$draft) {
      $form['missing'] = ['#markup' => '<p>' . $this->t('Draft #@id does not exist.', ['@id' => $id]) . '</p>'];
      return $form;
    }
    if ($draft['status'] !== 'pending') {
      $form['decided'] = ['#markup' => '<p>' . $this->t('This draft was already @status.', ['@status' => $draft['status']]) . '</p>'];
      return $form;
    }

    $form['summary'] = [
      '#markup' => sprintf(
        '<p><strong>%s</strong> · %d%% confidence %s</p>',
        $this->badge((string) $draft['intent']),
        (int) round(((float) $draft['confidence']) * 100),
        $draft['escalate'] ? '· <em>low confidence — personal attention recommended</em>' : '',
      ),
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Draft reply (editable)'),
      '#default_value' => (string) $draft['body'],
      '#rows' => 6,
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve & queue'),
      '#button_type' => 'primary',
      '#submit' => ['::approveSubmit'],
    ];
    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#submit' => ['::rejectSubmit'],
      '#limit_validation_errors' => [],
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to queue'),
      '#url' => Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'support-drafts']),
    ];
    $form['draft_id'] = ['#type' => 'value', '#value' => $id];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Only the approve path requires an editable body.
    $trigger = (string) $form_state->getTriggeringElement()['#submit'][0];
    if ($trigger === '::approveSubmit' && trim((string) $form_state->getValue('body')) === '') {
      $form_state->setErrorByName('body', $this->t('The reply body cannot be empty.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Decisions are handled by the button-specific submit handlers.
  }

  public function approveSubmit(array &$form, FormStateInterface $form_state): void {
    $this->decide($form, $form_state, TRUE);
  }

  public function rejectSubmit(array &$form, FormStateInterface $form_state): void {
    $this->decide($form, $form_state, FALSE);
  }

  private function decide(array &$form, FormStateInterface $form_state, bool $approve): void {
    $ok = $this->supportDrafts->decide(
      (int) $form_state->getValue('draft_id'),
      (int) $this->currentUser()->id(),
      $approve,
      trim((string) $form_state->getValue('body')),
    );
    if ($ok) {
      $this->messenger()->addStatus($approve
        ? $this->t('Draft approved and queued through the reviewed outbox path.')
        : $this->t('Draft rejected and archived.'));
    }
    else {
      $this->messenger()->addError($this->t('That draft is no longer pending.'));
    }
    $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'support-drafts']);
  }

  /** Minimal inline badge consistent with operations pages. */
  private function badge(string $status): string {
    return '<span class="famtastic-badge famtastic-badge--' . mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'x') . '">' . htmlspecialchars($status, ENT_QUOTES) . '</span>';
  }

}
