<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Uses the existing branded Drupal/Commerce form system, including CSRF. */
final class PrivatePurchaseForm extends FormBase {
  public function getFormId(): string { return 'famtastic_private_purchase'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $website_request = NULL): array {
    try { $context = (new PrivatePurchaseService())->context($this->currentUser(), (string) $website_request); }
    catch (\Throwable) { throw new NotFoundHttpException('This private purchase is not available to this account.'); }
    $scope = $context['scope'];
    $form['#cache'] = ['max-age' => 0, 'contexts' => ['user']];
    $form['#attributes']['class'][] = 'famtastic-private-purchase';
    $form['#attached']['library'][] = 'famtastic_pipeline/private_purchase';
    $form['project'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('@title', ['@title' => $scope['title']])];
    $form['scope'] = ['#type' => 'details', '#title' => $this->t('Your agreed scope'), '#open' => TRUE];
    $form['scope']['included'] = ['#theme' => 'item_list', '#items' => array_map(static fn($text): array => ['#plain_text' => (string) $text], $scope['included'])];
    $form['scope']['boundaries'] = ['#theme' => 'item_list', '#items' => array_map(static fn($text): array => ['#plain_text' => (string) $text], $scope['boundaries'] ?? [$scope['cost_boundary']])];
    $form['scope']['policy'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('This is a one-time private scope. No recurring payment is authorized. Confirming these details is not approval of the finished website or permission to launch it.')];
    if (!empty($scope['awaiting_customer_confirmation'])) {
      $form['scope']['pending'] = ['#theme' => 'item_list', '#title' => $this->t('Still to confirm with you'), '#items' => array_map(static fn($text): array => ['#plain_text' => ucfirst((string) $text)], $scope['awaiting_customer_confirmation'])];
    }
    $form['back'] = ['#type' => 'link', '#title' => $this->t('Back to my project'), '#url' => Url::fromUri(\Drupal::request()->getSchemeAndHttpHost() . '/portal?section=projects&request=' . rawurlencode((string) $website_request))];
    // GET may not write Form API cache. Sign the displayed scope instead; the
    // normal Form API CSRF token remains independently required on submission.
    $snapshot = NULL;
    if ($context['kind'] === 'reunion') {
      try { $snapshot = PrivatePurchaseService::selection($context['request']); }
      catch (\RuntimeException) { /* Unselected requests have no purchase action. */ }
    }
    $displayed = ['request' => $website_request, 'version' => $scope['version'], 'hash' => $context['scope_hash'],
      'selection_snapshot' => $snapshot, 'uid' => (int) $this->currentUser()->id(), 'issued_at' => time()];
    $form_state->set('private_scope', $displayed);
    $encoded = rtrim(strtr(base64_encode(json_encode($displayed, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $form['scope_snapshot'] = ['#type' => 'hidden', '#default_value' => $encoded . '.' . hash_hmac('sha256', 'private-purchase-form-v1|' . $encoded, Settings::getHashSalt())];
    if ($context['kind'] === 'prepaid') {
      $receipt = $context['receipt'];
      $form['status'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Order @number: $200.00 received by Zelle, $0.00 outstanding. Recorded from Fritz’s confirmation; the bank-transfer date was not supplied. This form cannot charge you again.', ['@number' => $receipt['order_number']])];
      $completion = $context['data']['completion'] ?? [];
      if (($completion['state'] ?? '') === 'consumed') {
        $form['saved'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Your scope and domain preferences are saved on this same paid order. Final website acceptance and launch checks are still separate.')];
        return $form;
      }
      if (($completion['state'] ?? '') !== 'issued' || (int) ($completion['expires_at'] ?? 0) <= time()) {
        $form['waiting'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('A valid completion code is not available yet. Your payment is recorded. Continue reviewing your project; contact us in your private project conversation when you are ready for the details step.')];
        return $form;
      }
      $form['completion_code'] = ['#type' => 'password', '#title' => $this->t('One-time completion code'), '#required' => TRUE, '#maxlength' => 48,
        '#attributes' => ['autocomplete' => 'one-time-code', 'spellcheck' => 'false'], '#description' => $this->t('Use the code provided for this purchase. It is not a discount code or a new payment.')];
    }
    else {
      $paid = $context['order'] && $context['order']->isPaid();
      $form['status'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $paid
        ? $this->t('Payment is recorded on your existing $199.00 order. No new payment is needed here. Final website approval is still separate.')
        : $this->t('$199.00 USD, one time. Payment has not been confirmed. After choosing a direction, you can review this scope and continue to secure checkout. Proofs and staging do not require a domain purchase.')];
      if ($paid) return $form;
      try { PrivatePurchaseService::selection($context['request']); }
      catch (\RuntimeException) {
        $form['waiting'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Choose your direction in the project first. Nothing has been charged or ordered by opening this page.')];
        return $form;
      }
      if (!PrivatePurchaseService::checkoutEnabled()) {
        $form['waiting'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Your direction is saved. We are verifying the private checkout connection; payment is not open yet. Your staging work can continue.')];
        return $form;
      }
    }
    $form['domain_choice'] = ['#type' => 'select', '#title' => $this->t('Website address'), '#options' => ['undecided' => $this->t('Confirm with FAMtastic later'), 'new_domain' => $this->t('Request a new domain'), 'existing_domain' => $this->t('Use my existing domain')], '#default_value' => 'undecided', '#required' => TRUE];
    $form['domain'] = ['#type' => 'textfield', '#title' => $this->t('Preferred domain (optional)'), '#maxlength' => 253, '#description' => $this->t('For example, yourbusiness.com. This saves a preference; it does not check availability, purchase a domain, or change DNS.')];
    $form['accept_terms'] = ['#type' => 'checkbox', '#title' => $this->t('I acknowledge the exact one-time scope shown above and understand that final website approval remains separate.'), '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#button_type' => 'primary', '#value' => $context['kind'] === 'prepaid' ? $this->t('Save details — no charge') : $this->t('Continue to secure $199 checkout')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $displayed = $form_state->get('private_scope') ?: [];
    try {
      $context = (new PrivatePurchaseService())->context($this->currentUser(), (string) ($displayed['request'] ?? ''));
      PrivatePurchaseService::assertDetails($this->input($form_state), $context['scope']['version'], $context['scope_hash']);
      if ($context['kind'] === 'prepaid') {
        $flood = \Drupal::service('flood');
        $key = 'uid:' . $this->currentUser()->id();
        if (!$flood->isAllowed('famtastic.prepaid_completion', 10, 600, $key)) throw new \RuntimeException('retry_later');
        $flood->register('famtastic.prepaid_completion', 600, $key);
        OfflinePrepaymentService::assertCompletion($context['data'], (string) $form_state->getValue('completion_code'), $this->input($form_state), time());
      }
      else {
        if (($this->input($form_state)['selection_snapshot'] ?? NULL) !== PrivatePurchaseService::selection($context['request'])) {
          throw new \RuntimeException('private_scope_selection_changed');
        }
      }
    }
    catch (\Throwable) { $form_state->setErrorByName('accept_terms', $this->t('We could not verify the current scope, account, domain or completion code. Nothing was charged. Refresh this page or use your project conversation for help.')); }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $request = (string) $form_state->get('private_scope')['request'];
    try {
      $service = new PrivatePurchaseService();
      $context = $service->context($this->currentUser(), $request);
      if ($context['kind'] === 'prepaid') {
        (new OfflinePrepaymentService())->complete((int) $context['order']->id(), $this->currentUser(), (string) $form_state->getValue('completion_code'), $this->input($form_state));
        $form_state->setRedirect('famtastic_pipeline.private_purchase', ['website_request' => $request]);
      }
      else {
        $order = $service->startReunion($this->currentUser(), $request, $this->input($form_state));
        $form_state->setRedirectUrl(Url::fromUri($order->isPaid() ? \Drupal::request()->getSchemeAndHttpHost() . '/portal?section=billing' : 'internal:/checkout/' . (int) $order->id()));
      }
    }
    catch (\Throwable) {
      $this->messenger()->addError($this->t('This purchase could not be continued. No payment was made by this form. Refresh to check the existing order before trying again.'));
      $form_state->setRebuild();
    }
    $form_state->setValue('completion_code', '');
  }

  private function input(FormStateInterface $state): array {
    // Hidden-element defaults are recomputed during POST reconstruction. Only
    // the actual submitted string proves possession of the displayed snapshot.
    $token = $state->getUserInput()['scope_snapshot'] ?? NULL;
    if (!is_string($token) || $token === '') throw new \RuntimeException('private_scope_snapshot_invalid');
    if (strlen($token) > 16000 || substr_count($token, '.') !== 1) throw new \RuntimeException('private_scope_snapshot_invalid');
    [$encoded, $signature] = explode('.', $token, 2);
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $encoded) || !preg_match('/^[a-f0-9]{64}$/D', $signature)
      || !hash_equals(hash_hmac('sha256', 'private-purchase-form-v1|' . $encoded, Settings::getHashSalt()), $signature)) {
      throw new \RuntimeException('private_scope_snapshot_invalid');
    }
    $displayed = json_decode(base64_decode(strtr($encoded, '-_', '+/'), TRUE), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($displayed) || ($displayed['uid'] ?? NULL) !== (int) $this->currentUser()->id()
      || ($displayed['request'] ?? NULL) !== ($state->get('private_scope')['request'] ?? NULL)
      || !is_int($displayed['issued_at'] ?? NULL) || $displayed['issued_at'] > time() + 60 || $displayed['issued_at'] < time() - 21600) {
      throw new \RuntimeException('private_scope_snapshot_invalid');
    }
    return ['accept_terms' => (bool) $state->getValue('accept_terms'), 'terms_version' => $displayed['version'] ?? '', 'scope_hash' => $displayed['hash'] ?? '',
      'domain_choice' => (string) $state->getValue('domain_choice'), 'domain' => (string) $state->getValue('domain'),
      'selection_snapshot' => $displayed['selection_snapshot'] ?? NULL];
  }
}
