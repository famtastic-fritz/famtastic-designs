<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Staff-only issuance for immutable owner-hosted customer invoices.
 */
final class CustomerInvoiceCommands extends DrushCommands {

  /**
   * Issues or replays one exact account-scoped invoice snapshot.
   */
  #[CLI\Command(name: 'famtastic:customer-invoice-issue')]
  #[CLI\Option(name: 'request', description: 'Exact website-request public UUID.')]
  #[CLI\Option(name: 'customer', description: 'Expected verified customer id.')]
  #[CLI\Option(name: 'organization', description: 'Expected owner organization id.')]
  #[CLI\Option(name: 'invoice-number', description: 'Unique customer-facing invoice number, for example TR-KAO-001.')]
  #[CLI\Option(name: 'staging-hash', description: 'Exact deployed staging receipt SHA-256.')]
  #[CLI\Option(name: 'staff-uid', description: 'Active staff account with pipeline administration permission.')]
  #[CLI\Option(name: 'actor', description: 'Actual staff/agent actor issuing the invoice.')]
  #[CLI\Option(name: 'authority', description: 'Owner instruction or approved plan authorizing issuance.')]
  #[CLI\Option(name: 'due-at', description: 'Optional Unix due timestamp; omit for no expiry.')]
  public function issue(
    array $options = [
      'request' => '',
      'customer' => 0,
      'organization' => 0,
      'invoice-number' => '',
      'staging-hash' => '',
      'staff-uid' => 0,
      'actor' => '',
      'authority' => '',
      'due-at' => 0,
    ],
  ): int {
    try {
      $staffUid = (int) ($options['staff-uid'] ?? 0);
      $staff = \Drupal::entityTypeManager()->getStorage('user')->load($staffUid);
      if (!$staff || !$staff->isActive() || !$staff->hasPermission('administer famtastic pipeline')) {
        throw new \RuntimeException('Choose an active staff execution account with pipeline administration permission.');
      }
      $switcher = \Drupal::service('account_switcher');
      $switcher->switchTo($staff);
      try {
        $invoice = \Drupal::service('famtastic_pipeline.customer_invoices')->issueOwnerHostedInvoice(
          (string) ($options['request'] ?? ''),
          (int) ($options['customer'] ?? 0),
          (int) ($options['organization'] ?? 0),
          (string) ($options['invoice-number'] ?? ''),
          (string) ($options['staging-hash'] ?? ''),
          $staffUid,
          (string) ($options['actor'] ?? ''),
          (string) ($options['authority'] ?? ''),
          ((int) ($options['due-at'] ?? 0)) > 0 ? (int) $options['due-at'] : NULL,
        );
      }
      finally {
        $switcher->switchBack();
      }
      // Deliberately return identifiers and immutable totals, never account
      // credentials, provider secrets, or hosting access material.
      $this->io()->writeln(json_encode([
        'public_id' => $invoice['public_id'],
        'invoice_number' => $invoice['invoice_number'],
        'website_request_public_id' => $invoice['website_request_public_id'],
        'status' => $invoice['status'],
        'currency' => $invoice['currency'],
        'list_amount_minor' => $invoice['list_amount_minor'],
        'credit_amount_minor' => $invoice['credit_amount_minor'],
        'total_amount_minor' => $invoice['total_amount_minor'],
        'snapshot_sha256' => $invoice['snapshot_sha256'],
        'owner_hosting_status' => $invoice['owner_hosting']['status'] ?? NULL,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Previews or sends the exact owner-hosted launch message for one invoice.
   */
  #[CLI\Command(name: 'famtastic:customer-invoice-message')]
  #[CLI\Option(name: 'request', description: 'Exact website-request public UUID.')]
  #[CLI\Option(name: 'customer', description: 'Expected verified customer id.')]
  #[CLI\Option(name: 'organization', description: 'Expected owner organization id.')]
  #[CLI\Option(name: 'invoice-number', description: 'Exact issued invoice number.')]
  #[CLI\Option(name: 'secondary-email', description: 'Second owner-authorized recipient address.')]
  #[CLI\Option(name: 'origin', description: 'Exact public FAMtastic origin.')]
  #[CLI\Option(name: 'staff-uid', description: 'Active staff account with pipeline administration permission.')]
  #[CLI\Option(name: 'authority', description: 'Owner approval reference for this exact customer communication.')]
  #[CLI\Option(name: 'send', description: 'Queue and dispatch only the two exact idempotent message keys.')]
  #[CLI\Option(name: 'confirm', description: 'For --send, must exactly repeat the issued invoice public UUID.')]
  public function message(array $options = [
    'request' => '',
    'customer' => 0,
    'organization' => 0,
    'invoice-number' => '',
    'secondary-email' => '',
    'origin' => 'https://famtasticdesigns.com',
    'staff-uid' => 0,
    'authority' => '',
    'send' => FALSE,
    'confirm' => '',
  ]): int {
    try {
      $requestPublicId = strtolower(trim((string) $options['request']));
      $customerId = (int) $options['customer'];
      $organizationId = (int) $options['organization'];
      $invoiceNumber = strtoupper(trim((string) $options['invoice-number']));
      $secondary = mb_strtolower(trim((string) $options['secondary-email']));
      $origin = rtrim(trim((string) $options['origin']), '/');
      if (!preg_match('/^[0-9a-f-]{36}$/', $requestPublicId)
        || $customerId < 1 || $organizationId < 1
        || !filter_var($secondary, FILTER_VALIDATE_EMAIL)
        || !in_array($origin, ['https://famtasticdesigns.com', 'https://www.famtasticdesigns.com'], TRUE)) {
        throw new \InvalidArgumentException('Exact request, ownership, recipient, and FAMtastic origin are required.');
      }
      /** @var \Drupal\famtastic_pipeline\Service\CustomerInvoiceService $invoices */
      $invoices = \Drupal::service('famtastic_pipeline.customer_invoices');
      $invoice = $invoices->invoiceForRequest($customerId, $organizationId, $requestPublicId);
      if (!$invoice || !hash_equals($invoiceNumber, (string) $invoice['invoice_number']) || !in_array((string) $invoice['status'], ['issued', 'checkout_pending'], TRUE)) {
        throw new \RuntimeException('The exact active invoice is unavailable.');
      }
      $database = \Drupal::database();
      $customer = $database->select('famtastic_customer', 'c')->fields('c')
        ->condition('id', $customerId)->execute()->fetchAssoc();
      $membership = $database->select('famtastic_membership', 'm')->fields('m', ['id'])
        ->condition('customer_id', $customerId)->condition('organization_id', $organizationId)
        ->condition('status', 'active')->execute()->fetchField();
      $request = $database->select('famtastic_project_request', 'r')->fields('r')
        ->condition('public_id', $requestPublicId)->condition('customer_id', $customerId)
        ->condition('organization_id', $organizationId)->execute()->fetchAssoc();
      $primary = mb_strtolower(trim((string) ($customer['email'] ?? '')));
      if (!$customer || empty($customer['verified_at']) || !$membership || !$request
        || !filter_var($primary, FILTER_VALIDATE_EMAIL) || hash_equals($primary, $secondary)) {
        throw new \RuntimeException('The exact verified account recipients are unavailable.');
      }
      $receipt = json_decode((string) ($request['staging_receipt_json'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR);
      $stagingUrl = trim((string) ($receipt['staging_url'] ?? ''));
      if (!filter_var($stagingUrl, FILTER_VALIDATE_URL) || strtolower((string) parse_url($stagingUrl, PHP_URL_SCHEME)) !== 'https') {
        throw new \RuntimeException('The exact staging receipt is unavailable.');
      }
      $paymentUrl = $origin . '/buy?sku=' . rawurlencode((string) $invoice['sku']) . '&invoice=' . rawurlencode((string) $invoice['public_id']);
      $portalUrl = $origin . '/portal/?section=projects&request=' . rawurlencode($requestPublicId);
      $subject = 'Kofi, The Reckoning is ready for its next step';
      $body = $this->ownerHostedLaunchBody($paymentUrl, $stagingUrl, $portalUrl);
      $draft = [
        'from' => 'hello@famtasticdesigns.com',
        'recipients' => [$primary, $secondary],
        'subject' => $subject,
        'body' => $body,
        'template' => 'standard/v2',
        'invoice_public_id' => $invoice['public_id'],
        'notification_status' => 'not_queued',
      ];
      if (empty($options['send'])) {
        $this->io()->writeln(json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return self::EXIT_SUCCESS;
      }
      if (!hash_equals((string) $invoice['public_id'], trim((string) $options['confirm'])) || trim((string) $options['authority']) === '') {
        throw new \RuntimeException('Sending requires --confirm=<exact-invoice-public-id> and the owner approval reference.');
      }
      $staff = \Drupal::entityTypeManager()->getStorage('user')->load((int) $options['staff-uid']);
      if (!$staff || !$staff->isActive() || !$staff->hasPermission('administer famtastic pipeline')) {
        throw new \RuntimeException('Choose an active staff execution account.');
      }
      /** @var \Drupal\famtastic_pipeline\Service\OutreachMailer $mailer */
      $mailer = \Drupal::service('famtastic_pipeline.mailer');
      if (!hash_equals('hello@famtasticdesigns.com', mb_strtolower($mailer->fromAddress()))) {
        throw new \RuntimeException('The configured transactional From address is not hello@famtasticdesigns.com.');
      }
      if (\Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive()) {
        throw new \RuntimeException('Exact customer message dispatch is unavailable while the pilot dispatch lock is active; no outbox row was created.');
      }
      $switcher = \Drupal::service('account_switcher');
      $switcher->switchTo($staff);
      try {
        /** @var \Drupal\famtastic_pipeline\Service\CustomerPortalService $portal */
        $portal = \Drupal::service('famtastic_pipeline.customer_portal');
        $keyRoot = 'customer-invoice:' . (int) $invoice['id'] . ':owner-launch:';
        $keys = [$keyRoot . 'primary', $keyRoot . 'secondary'];
        $portal->queueNotification($keys[0], 'transactional', $primary, $subject, $body);
        $portal->queueNotification($keys[1], 'transactional', $secondary, $subject, $body);
        $database->update('famtastic_notification_outbox')->fields(['max_attempts' => 1])
          ->condition('notification_key', $keys, 'IN')->execute();
        /** @var \Drupal\famtastic_pipeline\Service\LifecycleOperationsService $operations */
        $operations = \Drupal::service('famtastic_pipeline.lifecycle_operations');
        $dispatch = $operations->dispatchNotifications(2, $keys);
      }
      finally {
        $switcher->switchBack();
      }
      $rows = $database->select('famtastic_notification_outbox', 'n')
        ->fields('n', ['notification_key', 'recipient', 'status', 'attempts', 'provider_message_id'])
        ->condition('notification_key', $keys, 'IN')->orderBy('notification_key')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($rows) !== 2 || count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'sent')) !== 2) {
        throw new \RuntimeException('Both exact customer notifications were not accepted by the configured provider.');
      }
      $draft['notification_status'] = 'accepted_by_provider';
      $draft['authority'] = (string) $options['authority'];
      $draft['dispatch'] = $dispatch;
      $draft['outbox'] = $rows;
      $this->io()->writeln(json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /** Exact reviewed plain-text copy; URLs render through standard/v2. */
  private function ownerHostedLaunchBody(string $paymentUrl, string $stagingUrl, string $portalUrl): string {
    return "Hi Kofi,\n\nWe've got you.\n\nYour cinematic website, Command Center, Digital Author Card, Reader Circle, press paths, business roadmap, and move-to-your-hosting plan are already built. We also created the review, payment, and handoff process around them so you can see exactly what is complete and what happens next.\n\nText-message threads can make it easy for details to get scattered 😄. That is why we built your portal. You can review the work, submit changes, approve the release, pay, provide access, and follow the move from one place. Fritz is still available whenever you need him, and Shay can keep every routine step moving in the portal.\n\nYour itemized invoice shows the normal $499 package value and everything included. FAMtastic applied a $399 Community Sponsorship Credit, leaving the agreed one-time project contribution of $100.\n\nYou do not need to send a Zelle payment. Your secure card invoice is ready in your portal.\n\nOnce your payment is verified, the system will issue your receipt, open your hosting-access checklist, start the hosting audit, and prepare the private installation on your hosting.\n\nTwo items will still require your participation:\n\n• To test payments from your readers, you will connect and verify your own Stripe account. Those credentials stay under your control.\n• To connect the permanent domain, you will grant DNS access or complete your chosen domain transfer. A transfer is not always required if secure DNS access is available.\n\nIf you are ready to move this forward today, this is the only action we need from you right now:\n\n{$paymentUrl}\n\nAfter that, Shay will guide the remaining steps through your portal.\n\nWe believe in the message behind The Reckoning, and we're excited to help you move it from staging into a platform you own.\n\nReview the current staging release:\n{$stagingUrl}\n\nOpen your FAMtastic portal:\n{$portalUrl}\n\nShay\nFAMtastic Designs";
  }

}
