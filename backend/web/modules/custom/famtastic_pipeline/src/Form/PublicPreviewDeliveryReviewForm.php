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
    $proofCount = max(1, min(6, (int) ($this->delivery['package_variant_count'] ?? 3)));
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Public preview delivery #@id — @state', ['@id' => $preview_delivery, '@state' => str_replace('_', ' ', (string) $this->delivery['state'])]),
      '#markup' => '<p><strong>Recipient:</strong> ' . htmlspecialchars((string) $this->delivery['recipient_address_snapshot']) . '</p><p>Nothing is customer-visible or sent until the explicit approval action below. The room is read-only and contains no price, offer, selection, checkout, or intake data.</p>',
    ];
    if (in_array($this->delivery['state'], ['lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review', 'share_revoked'], TRUE)) {
      $form['proof_campaign_id'] = ['#type' => 'number', '#title' => $this->t('Ready proof campaign ID'), '#min' => 1, '#required' => TRUE];
      $form['build_dna_id'] = ['#type' => 'textfield', '#title' => $this->t('Registered Build DNA ID'), '#required' => TRUE, '#maxlength' => 170];
      $form['build_dna_hash'] = ['#type' => 'textfield', '#title' => $this->t('Build DNA SHA-256'), '#required' => TRUE, '#maxlength' => 64];
      $form['public_context'] = ['#type' => 'textarea', '#title' => $this->t('Safe public context for the room (optional)'), '#rows' => 3, '#description' => $this->t('Industry-neutral, factual context shown in the public room. Do not state unverified business facts.')];
      $form['research_teaser'] = ['#type' => 'textarea', '#title' => $this->t('Research teaser for the frozen email and room (optional)'), '#rows' => 4, '#description' => $this->t('If supplied, source summary and exact Build DNA research-artifact SHA-256 are required.')];
      $form['research_sources'] = ['#type' => 'textarea', '#title' => $this->t('Research source summary (optional)'), '#rows' => 3, '#description' => $this->t('Bounded source names or URLs reviewed. This text is frozen with the invitation.')];
      $form['research_report'] = ['#type' => 'textarea', '#title' => $this->t('Verified-customer research report (optional)'), '#rows' => 6, '#description' => $this->t('Only the same-email verified customer can retrieve this snapshot through the customer API.')];
      $form['research_evidence_hash'] = ['#type' => 'textfield', '#title' => $this->t('Build DNA research-artifact SHA-256 (optional)'), '#maxlength' => 64];
      $form['research_evidence_role'] = ['#type' => 'textfield', '#title' => $this->t('Build DNA research-artifact role (optional)'), '#maxlength' => 128, '#description' => $this->t('When research is supplied, use the exact artifact role containing “research” from Build DNA.')];
      $form['confirm_stage'] = ['#type' => 'checkbox', '#title' => $this->t('I verified all @count real direction proofs, research boundaries, Browser QA, independent review, and registered Build DNA.', ['@count' => $proofCount]), '#required' => TRUE];
      $form['actions']['#type'] = 'actions';
      $form['actions']['stage'] = ['#type' => 'submit', '#value' => $this->t('Stage private preview email'), '#preview_action' => 'stage', '#button_type' => 'primary'];
    }
    elseif ($this->delivery['state'] === 'email_staged') {
      $form['subject'] = ['#type' => 'textfield', '#title' => $this->t('Frozen subject'), '#default_value' => $this->delivery['subject_snapshot'], '#attributes' => ['readonly' => 'readonly']];
      $form['body'] = ['#type' => 'textarea', '#title' => $this->t('Frozen text alternative'), '#default_value' => $this->delivery['text_snapshot'], '#attributes' => ['readonly' => 'readonly'], '#rows' => 18];
      $form['confirm_send'] = ['#type' => 'checkbox', '#title' => $this->t('I reviewed the working concepts and exact email. Approve one held transactional preview invitation for an exact-ID dispatch.'), '#required' => TRUE];
      $form['actions']['#type'] = 'actions';
      $form['actions']['approve'] = ['#type' => 'submit', '#value' => $this->t('Approve and hold preview email'), '#preview_action' => 'approve', '#button_type' => 'primary'];
    }
    else {
      $form['status'] = ['#type' => 'item', '#title' => $this->t('Delivery state'), '#markup' => '<p><strong>' . htmlspecialchars((string) $this->delivery['state']) . '</strong></p>'];
      if (in_array($this->delivery['state'], ['email_approved', 'email_dispatch_failed', 'email_accepted', 'email_receipt_unknown', 'concept_room_viewed', 'signup_started', 'account_verified_and_claimed', 'request_submitted'], TRUE)) {
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
      $this->previews->stage($id, (int) $form_state->getValue('proof_campaign_id'), (string) $form_state->getValue('build_dna_id'), (string) $form_state->getValue('build_dna_hash'), [
        'public_context' => (string) $form_state->getValue('public_context'),
        'teaser' => (string) $form_state->getValue('research_teaser'),
        'sources' => (string) $form_state->getValue('research_sources'),
        'report' => (string) $form_state->getValue('research_report'),
        'evidence_hash' => (string) $form_state->getValue('research_evidence_hash'),
        'evidence_role' => (string) $form_state->getValue('research_evidence_role'),
      ]);
      $this->messenger()->addStatus($this->t('The preview email is staged. Review the exact frozen content before sending.'));
    }
    elseif ($action === 'approve') {
      $this->previews->approveAndHold($id, (int) $this->account->id());
      $this->messenger()->addStatus($this->t('One transactional preview email is held. It can be sent only by the bounded exact-ID preview dispatcher; SMTP acceptance is tracked separately from inbox delivery.'));
    }
    elseif ($action === 'revoke') {
      $this->previews->revoke($id, (int) $this->account->id());
      $this->messenger()->addStatus($this->t('The concept-room link was revoked.'));
    }
    $form_state->setRedirect('famtastic_pipeline.public_preview_delivery_review', ['preview_delivery' => $id]);
  }

}
