<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\famtastic_pipeline\Service\MicrositeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Staff-only owner-account binding for a managed microsite. */
final class MicrositeOwnerForm extends FormBase {

  public function __construct(
    private readonly MicrositeService $microsites,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.microsites'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'famtastic_microsite_owner_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $site_key = 'thirst-trap-772'): array {
    $ownerId = $this->microsites->ownerId($site_key);
    $owner = $ownerId ? $this->entityTypeManager->getStorage('user')->load($ownerId) : NULL;
    $form['site_key'] = ['#type' => 'value', '#value' => $site_key];
    $form['summary'] = [
      '#markup' => '<p>' . $this->t('Bind an existing verified Drupal account to this microsite. The owner then manages products, events, and messages through the branded owner console; no CMS administration permission is granted.') . '</p>',
    ];
    $form['owner'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Microsite owner account'),
      '#default_value' => $owner,
      '#description' => $this->t('Leave blank to revoke owner-console access.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save owner binding'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $owner = $form_state->getValue('owner');
    $ownerId = is_scalar($owner) && (int) $owner > 0 ? (int) $owner : NULL;
    $this->microsites->assignOwner((string) $form_state->getValue('site_key'), $ownerId);
    $this->messenger()->addStatus($ownerId
      ? $this->t('The verified account is now bound to the microsite owner console.')
      : $this->t('Microsite owner access was revoked.'));
  }

}
