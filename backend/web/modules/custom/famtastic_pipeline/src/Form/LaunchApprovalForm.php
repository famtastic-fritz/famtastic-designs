<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Records owner decisions without automatically enabling live billing. */
final class LaunchApprovalForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected Connection $database,
    protected AccountProxyInterface $account,
  ) { parent::__construct($configFactory, $typedConfigManager); }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'), $container->get('database'), $container->get('current_user'));
  }

  public function getFormId(): string { return 'famtastic_launch_approval'; }

  protected function getEditableConfigNames(): array { return ['famtastic_pipeline.launch_approval']; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('famtastic_pipeline.launch_approval');
    $catalog = (string) file_get_contents(dirname(\Drupal::root()) . '/config/famtastic-products.json');
    $deals = (string) file_get_contents(dirname(\Drupal::root()) . '/config/famtastic-deal-terms.json');
    $catalogChecksum = hash('sha256', $catalog . "\n" . $deals);
    $terms = $this->database->select('famtastic_terms_version', 't')->fields('t', ['version', 'checksum'])
      ->condition('terms_key', 'website_service')->condition('active', 1)->orderBy('version', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    $form['notice'] = ['#markup' => '<div class="messages messages--warning"><strong>Approval does not turn on live billing.</strong> It records the owner decision against exact catalog and terms checksums. Production activation remains a separate deployment gate.</div>'];
    $form['evidence'] = ['#type' => 'details', '#title' => $this->t('Version evidence'), '#open' => TRUE];
    $form['evidence']['catalog'] = ['#markup' => '<p><strong>Catalog/deals:</strong> <code>' . substr($catalogChecksum, 0, 20) . '…</code></p>'];
    $form['evidence']['terms'] = ['#markup' => '<p><strong>Terms:</strong> v' . (int) ($terms['version'] ?? 0) . ' <code>' . substr((string) ($terms['checksum'] ?? ''), 0, 20) . '…</code></p>'];
    $labels = [
      'catalog' => 'I approve the names, prices, scope, deliverables, and exclusions for every published SKU.',
      'web_basics' => 'I approve the $199 Web Basics Bundle promise.',
      'hosting' => 'I approve $9.99/month basic hosting after the included first year and separate recurring authorization.',
      'domain' => 'I approve first-year standard-domain inclusion and separate prepaid annual renewal at the disclosed registrar price.',
      'response' => 'I approve the three-business-day response target as a target, not a guaranteed resolution time.',
      'refunds' => 'I approve the provisional cancellation, refund, payment-failure, grace, and suspension rules.',
      'ownership' => 'I approve customer domain/content ownership and FAMtastic-managed hosting language.',
      'communications' => 'I approve required transactional notices and separate optional marketing consent.',
      'provider_tests' => 'I reviewed the Stripe sandbox success, decline, 3DS, subscription, and cancellation evidence.',
    ];
    $form['decisions'] = ['#type' => 'fieldset', '#title' => $this->t('Business decisions')];
    foreach ($labels as $key => $label) {
      $form['decisions'][$key] = ['#type' => 'checkbox', '#title' => $this->t($label), '#default_value' => (bool) $config->get($key)];
    }
    $form['legal_status'] = ['#type' => 'select', '#title' => $this->t('Legal review status'), '#required' => TRUE,
      '#options' => ['pending' => 'Pending qualified legal review', 'counsel_reviewed' => 'Reviewed by qualified counsel', 'owner_decision' => 'Owner documented a decision to proceed without counsel review'],
      '#default_value' => $config->get('legal_status') ?: 'pending'];
    $form['notes'] = ['#type' => 'textarea', '#title' => $this->t('Approval notes'), '#default_value' => $config->get('notes') ?: '', '#description' => $this->t('Record revisions, attorney details, exceptions, or decisions that require another catalog version.')];
    $form['production_activation'] = ['#type' => 'checkbox', '#title' => $this->t('I authorize a separate production-activation review after every launch gate passes.'), '#default_value' => (bool) $config->get('production_activation')];
    $form['catalog_checksum'] = ['#type' => 'hidden', '#value' => $catalogChecksum];
    $form['terms_checksum'] = ['#type' => 'hidden', '#value' => (string) ($terms['checksum'] ?? '')];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('famtastic_pipeline.launch_approval');
    foreach (['catalog', 'web_basics', 'hosting', 'domain', 'response', 'refunds', 'ownership', 'communications', 'provider_tests', 'production_activation'] as $key) {
      $config->set($key, (bool) $form_state->getValue($key));
    }
    $config->set('legal_status', $form_state->getValue('legal_status'))->set('notes', trim((string) $form_state->getValue('notes')))
      ->set('catalog_checksum', $form_state->getValue('catalog_checksum'))->set('terms_checksum', $form_state->getValue('terms_checksum'))
      ->set('approved_by_uid', (int) $this->account->id())->set('recorded_at', \Drupal::time()->getRequestTime())->save(TRUE);
    parent::submitForm($form, $form_state);
  }
}
