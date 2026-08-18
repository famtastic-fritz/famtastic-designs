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
    $form['proofs'] = ['#theme' => 'item_list', '#title' => $this->t('Safe, Wild, and OMG'), '#items' => $items, '#empty' => $this->t('No complete proof set is attached yet.')];
    $notification = $campaignId ? $this->database->select('famtastic_notification_outbox', 'n')->fields('n')->condition('notification_key', 'website-request:' . $website_request . ':proofs:' . $campaignId)->execute()->fetchAssoc() : NULL;
    $form['delivery'] = ['#type' => 'item', '#title' => $this->t('Customer delivery'), '#markup' => '<p>' . ($notification ? 'Notification status: <strong>' . htmlspecialchars((string) $notification['status']) . '</strong>' : '<strong>Not queued.</strong> The customer has not been sent these proofs.') . '</p>'];
    if (count($items) === 3 && $this->requestRow['proof_review_status'] === 'owner_review') {
      $form['confirm'] = ['#type' => 'checkbox', '#title' => $this->t('I reviewed all three working previews and approve showing them in this customer account.'), '#required' => TRUE];
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Approve and queue customer email'), '#button_type' => 'primary'];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->portal->approveWebsiteRequestProof((int) $this->requestRow['id'], (int) $this->account->id());
    $this->messenger()->addStatus($this->t('Proofs approved and one customer notification queued.'));
    $form_state->setRedirect('famtastic_pipeline.website_request_proof_review', ['website_request' => (int) $this->requestRow['id']]);
  }

}
