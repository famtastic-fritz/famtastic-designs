<?php

/**
 * @file
 * Provide views data for custom FAMtastic pipeline tables.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 */
function famtastic_pipeline_views_data(): array {
  $data = [];

  // 1. Notification Outbox (Sent Emails, Queue, Retries, Dead-Letters)
  $data['famtastic_notification_outbox']['table']['group'] = t('FAMtastic Outbox');
  $data['famtastic_notification_outbox']['table']['base'] = [
    'field' => 'id',
    'title' => t('FAMtastic Notification Outbox'),
    'help' => t('Stores all transactional emails, receipts, reminders, and delivery logs.'),
  ];

  $data['famtastic_notification_outbox']['id'] = [
    'title' => t('ID'),
    'help' => t('The unique message ID.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_notification_outbox']['recipient'] = [
    'title' => t('Recipient Email'),
    'help' => t('The recipient email address.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_notification_outbox']['category'] = [
    'title' => t('Category'),
    'help' => t('Notification category (e.g. quote_receipt, onboarding_link).'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_notification_outbox']['status'] = [
    'title' => t('Status'),
    'help' => t('Delivery status (queued, sent, retry, dead_letter, failed).'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_notification_outbox']['provider_message_id'] = [
    'title' => t('Provider Message ID'),
    'help' => t('SMTP or Mailgun provider message ID.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  $data['famtastic_notification_outbox']['attempts'] = [
    'title' => t('Delivery Attempts'),
    'help' => t('Number of delivery attempts.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_notification_outbox']['created'] = [
    'title' => t('Queued Date'),
    'help' => t('The date and time the email was queued.'),
    'field' => ['id' => 'date'],
    'filter' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
  ];

  $data['famtastic_notification_outbox']['changed'] = [
    'title' => t('Updated / Sent Date'),
    'help' => t('The date and time the email was sent or updated.'),
    'field' => ['id' => 'date'],
    'filter' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
  ];

  // 2. Private Offers (Commerce Staff Administration)
  $data['famtastic_private_offer']['table']['group'] = t('FAMtastic Private Offers');
  $data['famtastic_private_offer']['table']['base'] = [
    'field' => 'id',
    'title' => t('FAMtastic Private Offers'),
    'help' => t('Staff-administered custom quotes, discounts, and private offers.'),
  ];

  $data['famtastic_private_offer']['id'] = [
    'title' => t('Offer ID'),
    'help' => t('The unique offer ID.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_private_offer']['public_id'] = [
    'title' => t('Public Reference Key'),
    'help' => t('The public reference token for the customer.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  $data['famtastic_private_offer']['customer_email'] = [
    'title' => t('Customer Email'),
    'help' => t('The target customer email.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_private_offer']['offered_amount_minor'] = [
    'title' => t('Offered Price (Cents)'),
    'help' => t('The custom price in minor units (cents).'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_private_offer']['status'] = [
    'title' => t('Offer Status'),
    'help' => t('Status: active, accepted, expired, revoked.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  $data['famtastic_private_offer']['reason'] = [
    'title' => t('Offer Reason / Scope Description'),
    'help' => t('Staff description of the offer.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  $data['famtastic_private_offer']['created'] = [
    'title' => t('Created Date'),
    'help' => t('Date the offer was created.'),
    'field' => ['id' => 'date'],
    'filter' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
  ];

  return $data;
}
