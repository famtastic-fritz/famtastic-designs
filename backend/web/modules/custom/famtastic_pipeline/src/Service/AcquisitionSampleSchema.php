<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Additive native storage; recipient rows and bearer values never belong in Git. */
final class AcquisitionSampleSchema {

  public static function tables(): array {
    $int = ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE];
    $text = ['type' => 'text', 'size' => 'big', 'not null' => TRUE];
    $v = static fn(int $length, bool $required = TRUE): array => ['type' => 'varchar', 'length' => $length, 'not null' => $required];
    return [
      'famtastic_acquisition_sample' => [
        'fields' => [
          'id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
          'invitation_key' => $v(128), 'token_hash' => $v(64), 'recipient_hash' => $v(64), 'context_id' => $v(32),
          'campaign_id' => $int, 'prospect_id' => $int, 'niche' => $v(32), 'experiment_arm' => $v(16),
          'recipe_snapshot' => $text, 'bindings' => $text, 'evidence_hash' => $v(64),
          'qualification_ref' => $v(128), 'eligible_at' => $int,
          'preferred_recipe' => $v(96, FALSE), 'pending_customer_id' => $int, 'customer_id' => $int,
          'expires' => $int, 'revoked_at' => $int, 'created' => $int, 'changed' => $int,
        ],
        'primary key' => ['id'], 'unique keys' => ['token' => ['token_hash'], 'invitation' => ['invitation_key'], 'context' => ['context_id']],
        'indexes' => ['recipient' => ['recipient_hash'], 'customer' => ['customer_id'], 'pending_customer' => ['pending_customer_id'], 'campaign' => ['campaign_id']],
      ],
      'famtastic_acquisition_sequence' => [
        'fields' => [
          'id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
          'invitation_id' => $int, 'recipient_hash' => $v(64), 'prospect_id' => $int, 'campaign_id' => $int,
          'status' => $v(16), 'started_at' => $int, 'stopped_at' => $int, 'stop_reason' => $v(32, FALSE),
          'approval_ref' => $v(128, FALSE), 'created' => $int, 'changed' => $int,
        ],
        'primary key' => ['id'], 'unique keys' => ['invitation' => ['invitation_id']],
        'indexes' => ['recipient' => ['recipient_hash'], 'prospect' => ['prospect_id'], 'campaign' => ['campaign_id'], 'status' => ['status']],
      ],
      'famtastic_acquisition_message' => [
        'fields' => [
          'message_id' => array_replace($int, ['not null' => TRUE]), 'invitation_id' => $int, 'day' => $int,
          'content_id' => $v(128), 'content_hash' => $v(64), 'draft_hash' => $v(64),
          'snapshot' => $text,
        ],
        'primary key' => ['message_id'], 'indexes' => ['invitation' => ['invitation_id'], 'content' => ['content_id']],
      ],
      'famtastic_acquisition_request' => [
        'fields' => [
          'request_id' => array_replace($int, ['not null' => TRUE]), 'invitation_id' => array_replace($int, ['not null' => TRUE]),
          'customer_id' => $int, 'request_prospect_id' => $int, 'created' => $int,
        ],
        'primary key' => ['request_id'], 'unique keys' => ['invitation' => ['invitation_id']],
        'indexes' => ['customer' => ['customer_id'], 'request_prospect' => ['request_prospect_id']],
      ],
      'famtastic_acquisition_dispatch' => [
        'fields' => [
          'message_id' => array_replace($int, ['not null' => TRUE]), 'content_hash' => $v(64),
          'manifest_hash' => $v(64), 'approval_ref' => $v(128), 'status' => $v(16),
          'provider_message_id' => $v(255, FALSE), 'created' => $int, 'changed' => $int,
        ],
        'primary key' => ['message_id'], 'unique keys' => ['approval' => ['approval_ref']], 'indexes' => ['status' => ['status']],
      ],
    ];
  }

}
