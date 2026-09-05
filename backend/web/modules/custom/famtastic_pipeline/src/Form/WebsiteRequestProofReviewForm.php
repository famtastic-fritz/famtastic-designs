<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Owner-only approval gate for one account-owned website proof set. */
final class WebsiteRequestProofReviewForm extends FormBase {

  private array $requestRow = [];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly CustomerPortalService $portal,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('famtastic_pipeline.customer_portal'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'famtastic_website_request_proof_review';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $website_request = NULL): array {
    $this->requestRow = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $website_request)->execute()->fetchAssoc() ?: [];
    if (!$this->requestRow) return ['missing' => ['#markup' => '<p>Website request not found.</p>']];
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', (int) $this->requestRow['customer_id'])->execute()->fetchAssoc();
    $form['summary'] = [
      '#type' => 'item', '#title' => $this->t('@project — @status', ['@project' => $this->requestRow['project_name'], '@status' => str_replace('_', ' ', $this->requestRow['proof_review_status'])]),
      '#markup' => '<p><strong>Customer:</strong> ' . htmlspecialchars((string) ($customer['display_name'] ?? 'Unknown')) . ' · ' . htmlspecialchars((string) ($customer['email'] ?? '')) . '</p><p>This page is the customer-send gate. Reviewing previews does not notify the customer.</p>',
    ];
    $campaignId = (int) ($this->requestRow['proof_campaign_id'] ?? 0);
    $ids = $campaignId ? $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', $campaignId)->sort('direction_id')->execute() : [];
    $items = [];
    foreach ($this->entities->getStorage('proof_variant')->loadMultiple($ids) as $variant) {
      $direction = (string) $variant->get('direction_id')->value;
      $name = (string) $variant->get('direction_name')->value;
      $items[] = Link::fromTextAndUrl($name . ' — open owner preview', Url::fromRoute('famtastic_pipeline.website_request_proof_admin_preview', ['website_request' => $website_request, 'direction' => $direction], ['attributes' => ['target' => '_blank', 'rel' => 'noopener']]))->toRenderable();
    }
    $proofCount = count($items);
    $complete = in_array($proofCount, [3, 6], TRUE);
    $proofTitle = $proofCount === 6 ? $this->t('Six working website concepts') : $this->t('Safe, Wild, and OMG');
    $form['proofs'] = ['#theme' => 'item_list', '#title' => $proofTitle, '#items' => $items, '#empty' => $this->t('No complete proof set is attached yet.')];
    $notification = $campaignId && $complete ? $this->database->select('famtastic_notification_outbox', 'n')->fields('n')->condition('notification_key', 'website-request:' . $website_request . ':proofs:' . $campaignId . ':' . $proofCount)->execute()->fetchAssoc() : NULL;
    $form['delivery'] = ['#type' => 'item', '#title' => $this->t('Customer delivery'), '#markup' => '<p>' . ($notification ? 'Notification status: <strong>' . htmlspecialchars((string) $notification['status']) . '</strong>' : '<strong>Not queued.</strong> The customer has not been sent these proofs.') . '</p>'];
    if ($complete && $this->requestRow['proof_review_status'] === 'owner_review') {
      $research = $this->portal->websiteRequestProofResearchSnapshot((int) $this->requestRow['id']) ?? [];
      $rationale = is_array($research['direction_rationale'] ?? NULL) ? $research['direction_rationale'] : [];
      $lines = static fn(mixed $value): string => is_array($value) ? implode("\n", $value) : '';
      $form['research'] = ['#type' => 'details', '#title' => $this->t('Research & opportunity snapshot shown with these proofs'), '#open' => TRUE];
      $form['research']['intro'] = ['#markup' => '<p>This becomes the customer-readable explanation for the directions. Record observed signals and labeled opportunities; do not promise growth results.</p>'];
      $form['research']['overview'] = ['#type' => 'textarea', '#title' => $this->t('Research overview'), '#required' => TRUE, '#default_value' => (string) ($research['overview'] ?? ''), '#rows' => 3];
      foreach (['a' => 'Safe', 'b' => 'Wild', 'c' => 'OMG'] as $direction => $label) {
        $form['research']['rationale_' . $direction] = ['#type' => 'textarea', '#title' => $this->t('@label rationale', ['@label' => $label]), '#required' => TRUE, '#default_value' => (string) ($rationale[$direction] ?? ''), '#rows' => 2];
      }
      $form['research']['market_signals'] = ['#type' => 'textarea', '#title' => $this->t('Market signals (one per line)'), '#default_value' => $lines($research['market_signals'] ?? []), '#rows' => 3];
      $form['research']['opportunities'] = ['#type' => 'textarea', '#title' => $this->t('Growth opportunities this site supports (one per line)'), '#default_value' => $lines($research['opportunities'] ?? []), '#rows' => 3];
      $form['research']['sources'] = ['#type' => 'textarea', '#title' => $this->t('Research sources or evidence (one per line)'), '#required' => TRUE, '#default_value' => $lines($research['sources'] ?? []), '#rows' => 3];
      $form['research']['researched_at'] = ['#type' => 'date', '#title' => $this->t('Research date'), '#required' => TRUE, '#default_value' => (string) ($research['researched_at'] ?? '')];
      $form['confirm'] = ['#type' => 'checkbox', '#title' => $this->t('I reviewed all @count working previews and the research snapshot, and approve showing them in this customer account.', ['@count' => $proofCount]), '#required' => TRUE];
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Approve and queue customer email'), '#button_type' => 'primary', '#proof_action' => 'approve'];
    }
    elseif ($complete && in_array($this->requestRow['proof_review_status'], ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) {
      $share = $this->portal->websiteProofShareStatus((int) $this->requestRow['id']);
      $status = $share['enabled'] ? '<strong>On.</strong> Anyone with the unlisted link can view the proofs.' : '<strong>Off.</strong> The proofs still require customer sign-in.';
      $form['sharing'] = ['#type' => 'details', '#title' => $this->t('Unlisted proof sharing'), '#open' => TRUE];
      $form['sharing']['status'] = ['#markup' => '<p>' . $status . ' Visitors cannot select, request revisions, purchase, or see account information.</p>'];
      if ($share['enabled']) {
        $form['sharing']['url'] = ['#type' => 'textfield', '#title' => $this->t('Share link'), '#value' => $share['url'], '#attributes' => ['readonly' => 'readonly']];
      }
      $form['sharing']['actions']['#type'] = 'actions';
      $form['sharing']['actions']['toggle'] = [
        '#type' => 'submit',
        '#value' => $share['enabled'] ? $this->t('Turn off unlisted sharing') : $this->t('Create unlisted share link'),
        '#proof_action' => $share['enabled'] ? 'disable' : 'enable',
        '#limit_validation_errors' => [],
      ];
      if ($share['enabled']) {
        $form['sharing']['actions']['rotate'] = [
          '#type' => 'submit', '#value' => $this->t('Revoke and create a new link'), '#proof_action' => 'rotate', '#limit_validation_errors' => [],
        ];
      }
      if (in_array($this->requestRow['proof_review_status'], ['selected', 'revision_requested'], TRUE) && empty($this->requestRow['commerce_order_id'])) {
        $form['selection'] = ['#type' => 'details', '#title' => $this->t('Locked customer choice'), '#open' => TRUE];
        $form['selection']['detail'] = ['#markup' => '<p>The customer’s chosen direction is <strong>' . htmlspecialchars(strtoupper((string) $this->requestRow['selected_proof_direction'])) . '</strong>. Reopening clears that recorded choice and returns this proof set to the customer for a fresh decision. This cannot be used after checkout begins.</p>'];
        $form['selection']['actions']['#type'] = 'actions';
        $form['selection']['actions']['reopen'] = [
          '#type' => 'submit',
          '#value' => $this->t('Reopen customer proof choice'),
          '#proof_action' => 'reopen_selection',
          '#limit_validation_errors' => [],
        ];
      }
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $action = (string) ($trigger['#proof_action'] ?? 'approve');
    if ($action === 'approve') {
      $toLines = static fn(string $value): array => array_values(array_filter(array_map('trim', preg_split('/\R/', $value) ?: [])));
      $this->portal->saveWebsiteRequestProofResearchSnapshot((int) $this->requestRow['id'], (int) $this->account->id(), [
        'overview' => (string) $form_state->getValue('overview'),
        'direction_rationale' => [
          'a' => (string) $form_state->getValue('rationale_a'),
          'b' => (string) $form_state->getValue('rationale_b'),
          'c' => (string) $form_state->getValue('rationale_c'),
        ],
        'market_signals' => $toLines((string) $form_state->getValue('market_signals')),
        'opportunities' => $toLines((string) $form_state->getValue('opportunities')),
        'sources' => $toLines((string) $form_state->getValue('sources')),
        'researched_at' => (string) $form_state->getValue('researched_at'),
      ]);
      $this->portal->approveWebsiteRequestProof((int) $this->requestRow['id'], (int) $this->account->id());
      $this->messenger()->addStatus($this->t('Proofs approved and one customer notification queued.'));
    }
    elseif ($action === 'reopen_selection') {
      $this->portal->reopenWebsiteRequestProofSelection((int) $this->requestRow['id'], (int) $this->account->id());
      $this->messenger()->addStatus($this->t('The customer proof choice was reopened. No checkout or provider action was changed.'));
    }
    else {
      $share = $this->portal->manageWebsiteProofShare((int) $this->requestRow['id'], $action, (int) $this->account->id());
      $message = $action === 'disable' ? 'Unlisted sharing is off. The previous link has been revoked.' : ($action === 'rotate' ? 'The previous link was revoked and a new unlisted link was created.' : 'Unlisted proof sharing is on.');
      $this->messenger()->addStatus($this->t($message));
      if (!empty($share['url'])) $this->messenger()->addStatus($share['url']);
    }
    $form_state->setRedirect('famtastic_pipeline.website_request_proof_review', ['website_request' => (int) $this->requestRow['id']]);
  }

}
