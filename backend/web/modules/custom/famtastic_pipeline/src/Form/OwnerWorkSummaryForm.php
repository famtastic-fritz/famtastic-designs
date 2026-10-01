<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\StaffAiTaskService;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Explicit, aggregate-only owner work summary; it cannot send or publish. */
final class OwnerWorkSummaryForm extends FormBase {

  public function __construct(protected Connection $database, protected StaffAiTaskService $ai) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('famtastic_pipeline.staff_ai_tasks'));
  }

  public function getFormId(): string { return 'famtastic_owner_work_summary'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache']['max-age'] = 0;
    $form['back'] = ['#type' => 'link', '#title' => $this->t('Back to my command center'), '#url' => Url::fromRoute('famtastic_pipeline.operations')];
    $form['intro'] = ['#markup' => '<p>Ask AI to suggest where to start using current queue counts. This sends counts only, with no customer names, emails or conversation text. It creates a suggestion for you to review.</p>'];
    $ready = $this->ai->readiness('needs_me');
    $form['readiness'] = ['#plain_text' => $ready['message']];
    $form['setup'] = ['#type' => 'link', '#title' => $this->t('Review AI setup'), '#url' => Url::fromRoute('famtastic_pipeline.staff_ai_settings')];
    if (!empty($ready['ready'])) {
      $form['provider'] = ['#plain_text' => 'Draft service: ' . $ready['provider'] . ' / ' . $ready['model']];
      $form['actions'] = ['#type' => 'actions', 'generate' => ['#type' => 'submit', '#value' => $this->t('Summarize my work'), '#button_type' => 'primary']];
    }
    if ($result = $form_state->get('summary_result')) {
      $form['result'] = ['#type' => 'container', '#attributes' => ['aria-live' => 'polite'],
        'heading' => ['#markup' => '<h2>AI suggestion — review before acting</h2>'],
        'text' => ['#markup' => '<p>' . nl2br(Html::escape((string) $result['text'])) . '</p>'],
        'receipt' => ['#plain_text' => 'Receipt ' . $result['receipt_id'] . ' · ' . $result['provider'] . ' / ' . $result['model'] . ' · Based on the counts recorded when requested.'],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasPermission('administer famtastic pipeline')) throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    $now = \Drupal::time()->getRequestTime();
    $inbox = \Drupal::service('famtastic_pipeline.client_messages')->inbox($this->currentUser(), ['status' => 'needs_reply']);
    $data = ['recorded_at' => $now, 'needs_reply' => count($inbox['threads'])];
    foreach (['review' => ['famtastic_support_draft', ['pending']], 'delivery_attention' => ['famtastic_notification_outbox', ['retry', 'dead_letter']], 'open_exceptions' => ['famtastic_exception', ['open', 'retry']]] as $key => [$table, $states]) {
      $data[$key] = (int) $this->database->select($table, 'r')->condition('status', $states, 'IN')->countQuery()->execute()->fetchField();
    }
    $data['jobs_due'] = (int) $this->database->select('famtastic_job', 'j')->condition('status', ['queued', 'retry'], 'IN')->condition('available_at', $now, '<=')->countQuery()->execute()->fetchField();
    $sources = [['id' => 'owner-work-queue', 'digest' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'data' => $data]];
    try {
      $form_state->set('summary_result', $this->ai->generate($this->currentUser(), 'needs_me', $sources));
    }
    catch (\RuntimeException $error) {
      $this->messenger()->addError($this->t('The summary could not be prepared. Your work queues are unchanged. Review AI setup or try again later.'));
    }
    $form_state->setRebuild();
  }
}
