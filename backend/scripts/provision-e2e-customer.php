<?php

/**
 * Provision or refresh the controlled browser-QA customer.
 *
 * Required environment: FAMTASTIC_E2E_CUSTOMER_EMAIL and
 * FAMTASTIC_E2E_CUSTOMER_PASSWORD. This script never prints either value.
 */

use Drupal\user\Entity\User;

$email = mb_strtolower(trim((string) getenv('FAMTASTIC_E2E_CUSTOMER_EMAIL')));
$password = (string) getenv('FAMTASTIC_E2E_CUSTOMER_PASSWORD');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 16) {
  throw new RuntimeException('Controlled E2E email and a 16+ character password are required.');
}

$storage = \Drupal::entityTypeManager()->getStorage('user');
$matches = $storage->loadByProperties(['mail' => $email]);
/** @var \Drupal\user\UserInterface $user */
$user = $matches ? reset($matches) : User::create([
  'name' => $email,
  'mail' => $email,
  'status' => TRUE,
  'roles' => ['authenticated'],
]);
$user->setPassword($password)->activate()->save();

// Display fields are overridable so automated crawls never seed strings that
// their own synthetic-content assertions would flag in customer-visible UI.
$name = getenv('FAMTASTIC_E2E_CUSTOMER_NAME') ?: 'FAMtastic Mobile QA';
$business = getenv('FAMTASTIC_E2E_CUSTOMER_BUSINESS') ?: 'FAMtastic Synthetic QA';

/** @var \Drupal\famtastic_pipeline\Service\CustomerPortalService $portal */
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$customer = $portal->createCustomer($user, [
  'name' => $name,
  'business_name' => $business,
  'source' => 'controlled_e2e',
  'marketing_opt_out' => TRUE,
]);
$portal->markVerified((int) $customer['id']);

echo "Controlled E2E customer is ready.\n";
