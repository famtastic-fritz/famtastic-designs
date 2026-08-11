<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Lets staff assign a packaged scope and one-customer price to an intake. */
final class WebsiteRequestOfferForm extends FormBase {

  private array $requestRow = [];

  public function __construct(private readonly Connection $database, private readonly UuidInterface $uuid, private readonly TimeInterface $time) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('uuid'), $container->get('datetime.time'));
  }

  public function getFormId(): string { return 'famtastic_website_request_offer'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $website_request = NULL): array {
    $this->requestRow = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $website_request)->execute()->fetchAssoc() ?: [];
    if (!$this->requestRow) throw new NotFoundHttpException('Website request not found.');
    $intake = json_decode((string) $this->requestRow['intake_data'], TRUE) ?: [];
    $recommended = (string) ($intake['recommendation']['recommended_sku'] ?? 'FAM-FOOT-199');
    $prices = ['FAM-FOOT-199' => 19900, 'FAM-BUSINESS-499' => 49900];
    $form['summary'] = ['#markup' => '<p><strong>' . $this->t('@name', ['@name' => $this->requestRow['project_name']]) . '</strong><br>' . $this->t('System recommendation: @path', ['@path' => $intake['recommendation']['label'] ?? 'Custom review']) . '</p>'];
    $form['sku'] = ['#type' => 'select', '#title' => $this->t('Approved package'), '#options' => ['FAM-FOOT-199' => 'Web Basics Bundle — $199 list', 'FAM-BUSINESS-499' => 'Business Website Bundle — $499 list'], '#default_value' => isset($prices[$recommended]) ? $recommended : 'FAM-FOOT-199', '#required' => TRUE];
    $form['offered_price'] = ['#type' => 'number', '#title' => $this->t('Customer price (USD)'), '#min' => 1, '#step' => '0.01', '#default_value' => isset($prices[$recommended]) ? number_format($prices[$recommended] / 100, 2, '.', '') : '199.00', '#required' => TRUE, '#description' => $this->t('The list price and special price are both preserved on the offer and order snapshot.')];
    $form['reason'] = ['#type' => 'textfield', '#title' => $this->t('Internal/customer-visible reason'), '#maxlength' => 512, '#placeholder' => $this->t('Friend launch price, community partner, approved promotion…')];
    $form['expires_days'] = ['#type' => 'number', '#title' => $this->t('Expires in days'), '#min' => 1, '#max' => 365, '#default_value' => 30, '#required' => TRUE];
    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Activate private offer'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sku = (string) $form_state->getValue('sku');
    $prices = ['FAM-FOOT-199' => 19900, 'FAM-BUSINESS-499' => 49900];
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_private_offer')->fields(['status' => 'revoked', 'changed' => $now])->condition('website_request_id', $this->requestRow['id'])->condition('status', 'active')->execute();
    $this->database->insert('famtastic_private_offer')->fields([
      'public_id' => $this->uuid->generate(), 'website_request_id' => $this->requestRow['id'],
      'organization_id' => $this->requestRow['organization_id'], 'customer_id' => $this->requestRow['customer_id'],
      'sku' => $sku, 'list_amount_minor' => $prices[$sku],
      'offered_amount_minor' => (int) round(((float) $form_state->getValue('offered_price')) * 100),
      'currency' => 'usd', 'reason' => trim((string) $form_state->getValue('reason')), 'status' => 'active',
      'expires_at' => $now + ((int) $form_state->getValue('expires_days') * 86400),
      'created_by_uid' => (int) $this->currentUser()->id(), 'created' => $now, 'changed' => $now,
    ])->execute();
    $this->messenger()->addStatus($this->t('The private offer is active and visible only to this customer workspace.'));
    $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'website-requests']);
  }
}
