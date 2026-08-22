<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Owner gate for one anonymous, pre-registration concept-room delivery. */
final class PublicPreviewDeliveryReviewForm extends FormBase {

  private array $delivery = [];

  public function __construct(
    private readonly Connection $database,
    private readonly PublicPreviewDeliveryService $previews,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('famtastic_pipeline.public_preview_deliveries'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'famtastic_public_preview_delivery_review';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $preview_delivery = NULL): array {
    $this->delivery = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('id', $preview_delivery)->execute()->fetchAssoc() ?: [];
    if (!$this->delivery) return ['missing' => ['#markup' => '<p>Preview delivery not found.</p>']];
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Public preview delivery #@id — @state', ['@id' => $preview_delivery, '@state' => str_replace('_', ' ', (string) $this->delivery['state'])]),
      '#markup' => '<p><strong>Recipient:</strong> ' . htmlspecialchars((string) $this->delivery['recipient_address_snapshot']) . '</p><p>Nothing is customer-visible or sent until the explicit approval action below. The room is read-only and contains no price, offer, selection, checkout, or intake data.</p>',
    ];
    if (in_array($this->delivery['state'], ['lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review'], TRUE)) {
      $form['proof_campaign_id'] = ['#type' => 'number', '#title' => $this->t('Ready core proof campaign ID'), '#min' => 1, '#required' => TRUE];
      $form['build_dna_id'] = ['#type' => 'textfield', '#title' => $this->t('Registered Build DNA ID'), '#required' => TRUE, '#maxlength' => 170];
      $form['build_dna_hash'] = ['#type' => 'textfield', '#title' => $this->t('Build DNA SHA-256'), '#required' => TRUE, '#maxlength' => 64];
      $form['confirm_stage'] = ['#type' => 'checkbox', '#title' => $this->t('I verified three real Safe, Medium FAMtastic, and Ultra FAMtastic proofs, their research, Browser QA, independent review, and registered Build DNA.'), '#required' => TRUE];
      $form['actions']['#type'] = 'actions';
      $form['actions']['stage'] = ['#type' => 'submit', '#value' => $this->t('Stage private preview email'), '#preview_action' => 'stage', '#button_type' => 'primary'];
    }
    elseif ($this->delivery['state'] === 'email_staged') {
      $form['subject'] = ['#type' => 'textfield', '#title' => $this->t('Frozen subject'), '#default_value' => $this->delivery['subject_snapshot'], '#attributes' => ['readonly' => 'readonly']];
      $form['body'] = ['#type' => 'textarea', '#title' => $this->t('Frozen text alternative'), '#default_value' => $this->delivery['text_snapshot'], '#attributes' => ['readonly' => 'readonly'], '#rows' => 18];
      $form['confirm_send'] = ['#type' => 'checkbox', '#title' => $this->t('I reviewed the working concepts and exact email. Approve one transactional preview invitation.'), '#required' => TRUE];
      $form['actions']['#type'] = 'actions';
      $form['actions']['approve'] = ['#type' => 'submit', '#value' => $this->t('Approve and queue preview email'), '#preview_action' => 'approve', '#button_type' => 'primary'];
    }
    else {
      $form['status'] = ['#type' => 'item', '#title' => $this->t('Delivery state'), '#markup' => '<p><strong>' . htmlspecialchars((string) $this->delivery['state']) . '</strong></p>'];
      if (in_array($this->delivery['state'], ['email_queued', 'email_accepted', 'concept_room_viewed', 'signup_started', 'account_verified_and_claimed', 'request_submitted'], TRUE)) {
        $form['actions']['#type'] = 'actions';
        $form['actions']['revoke'] = ['#type' => 'submit', '#value' => $this->t('Revoke concept-room link'), '#preview_action' => 'revoke', '#button_type' => 'danger', '#limit_validation_errors' => []];
      }
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = (string) ($form_state->getTriggeringElement()['#preview_action'] ?? '');
    $id = (int) $this->delivery['id'];
    if ($action === 'stage') {
      $this->previews->stage($id, (int) $form_state->getValue('proof_campaign_id'), (string) $form_state->getValue('build_dna_id'), (string) $form_state->getValue('build_dna_hash'));
      $this->messenger()->addStatus($this->t('The preview email is staged. Review the exact frozen content before sending.'));
    }
    elseif ($action === 'approve') {
      $this->previews->approveAndQueue($id, (int) $this->account->id());
      $this->messenger()->addStatus($this->t('One transactional preview email was queued. SMTP acceptance is tracked separately from inbox delivery.'));
    }
    elseif ($action === 'revoke') {
      $this->previews->revoke($id, (int) $this->account->id());
      $this->messenger()->addStatus($this->t('The concept-room link was revoked.'));
    }
    $form_state->setRedirect('famtastic_pipeline.public_preview_delivery_review', ['preview_delivery' => $id]);
  }

}
