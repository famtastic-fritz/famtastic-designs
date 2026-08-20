<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\GrantCodeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates one private, scoped grant code and reveals it only once. */
final class GrantCodeForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly GrantCodeService $grantCodes,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('famtastic_pipeline.grant_codes'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'famtastic_grant_code_create';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['explanation'] = [
      '#markup' => '<p>Grant codes are private, hashed, and ownership checked. Use CUSTOMER_GRANT for one customer request and OWNER_COMP only for an approved owner account. The raw code is shown once after creation.</p>',
    ];
    $form['grant_class'] = [
      '#type' => 'select', '#title' => $this->t('Code class'), '#required' => TRUE,
      '#options' => array_combine(GrantCodeService::CLASSES, GrantCodeService::CLASSES),
      '#default_value' => 'CUSTOMER_GRANT',
    ];
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Internal label'), '#required' => TRUE, '#maxlength' => 255];
    $form['customer_email'] = ['#type' => 'email', '#title' => $this->t('Customer email'), '#description' => $this->t('Required for OWNER_COMP and CUSTOMER_GRANT.')];
    $form['website_request'] = ['#type' => 'textfield', '#title' => $this->t('Website request UUID'), '#description' => $this->t('Required for CUSTOMER_GRANT. Ownership is inferred from the request.')];
    $form['sku'] = [
      '#type' => 'select', '#title' => $this->t('Covered SKU'), '#required' => TRUE,
      '#options' => ['FAM-FOOT-199' => 'FAM-FOOT-199 — Web Basics', 'FAM-BUSINESS-499' => 'FAM-BUSINESS-499 — Business Website'],
    ];
    $form['discount_type'] = [
      '#type' => 'select', '#title' => $this->t('Discount rule'), '#required' => TRUE,
      '#options' => ['free' => 'Free initial SKU', 'percent' => 'Percentage', 'fixed' => 'Fixed amount'], '#default_value' => 'free',
    ];
    $form['discount_value'] = [
      '#type' => 'number', '#title' => $this->t('Percent or dollars'), '#min' => 0, '#step' => '0.01',
      '#description' => $this->t('Ignored for Free. Enter 25 for 25 percent or 75 for $75.'),
    ];
    $form['max_redemptions'] = ['#type' => 'number', '#title' => $this->t('Maximum redemptions'), '#min' => 1, '#max' => 1000, '#default_value' => 1, '#required' => TRUE];
    $form['expires_days'] = ['#type' => 'number', '#title' => $this->t('Expires in days'), '#min' => 1, '#max' => 3650, '#default_value' => 30, '#required' => TRUE];
    $form['renewal_notice'] = ['#markup' => '<p><strong>Renewals are not included.</strong> Ongoing hosting sponsorship requires a separate approved service contract.</p>'];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Create private grant code'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $email = mb_strtolower(trim((string) $form_state->getValue('customer_email')));
    $requestPublicId = trim((string) $form_state->getValue('website_request'));
    $request = $requestPublicId === '' ? NULL : $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $requestPublicId)->execute()->fetchAssoc();
    $customer = $email === '' ? NULL : $this->database->select('famtastic_customer', 'c')->fields('c')->condition('email', $email)->execute()->fetchAssoc();
    if ($requestPublicId !== '' && !$request) $form_state->setErrorByName('website_request', $this->t('Website request not found.'));
    if ($email !== '' && !$customer) $form_state->setErrorByName('customer_email', $this->t('Customer account not found.'));
    if ($request && $customer && (int) $request['customer_id'] !== (int) $customer['id']) $form_state->setErrorByName('website_request', $this->t('That request does not belong to the selected customer.'));
    $class = (string) $form_state->getValue('grant_class');
    if (in_array($class, ['OWNER_COMP', 'CUSTOMER_GRANT'], TRUE) && !$customer && !$request) $form_state->setErrorByName('customer_email', $this->t('This code class requires an exact customer account.'));
    if ($class === 'CUSTOMER_GRANT' && !$request) $form_state->setErrorByName('website_request', $this->t('CUSTOMER_GRANT requires an exact website request.'));
    $form_state->set('grant_request', $request ?: []);
    $form_state->set('grant_customer', $customer ?: []);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $request = (array) $form_state->get('grant_request');
    $customer = (array) $form_state->get('grant_customer');
    if ($request && !$customer) {
      $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', (int) $request['customer_id'])->execute()->fetchAssoc() ?: [];
    }
    $type = (string) $form_state->getValue('discount_type');
    $displayValue = (float) $form_state->getValue('discount_value');
    $value = $type === 'percent' ? (int) round($displayValue * 100) : (int) round($displayValue * 100);
    $result = $this->grantCodes->create([
      'grant_class' => (string) $form_state->getValue('grant_class'),
      'label' => (string) $form_state->getValue('label'),
      'customer_id' => $customer['id'] ?? NULL,
      'organization_id' => $request['organization_id'] ?? NULL,
      'website_request_id' => $request['id'] ?? NULL,
      'sku' => (string) $form_state->getValue('sku'),
      'discount_type' => $type,
      'discount_value' => $value,
      'max_redemptions' => (int) $form_state->getValue('max_redemptions'),
      'expires_at' => \Drupal::time()->getRequestTime() + ((int) $form_state->getValue('expires_days') * 86400),
      'covers_renewal' => FALSE,
    ], (int) $this->account->id());
    $this->messenger()->addStatus($this->t('Private code created. Copy it now; it cannot be recovered: @code', ['@code' => $result['code']]));
    $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'grant-codes']);
  }

}
