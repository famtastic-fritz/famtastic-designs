<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

final class WorkerCoordinatorSchema {
  public static function tables(): array {
    $text = ['type' => 'text', 'size' => 'big', 'not null' => TRUE];
    $int = ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0];
    $str = ['type' => 'varchar', 'length' => 191, 'not null' => TRUE, 'default' => ''];
    return [
      'famtastic_worker_mutex' => [
        'description' => 'Fixed singleton row: database transaction ownership only, no authority payload or TTL.',
        'fields' => ['id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE]],
        'primary key' => ['id'],
      ],
      'famtastic_worker_claim' => [
        'description' => 'Explicitly enrolled shared queue jobs. No automatic historical enrollment.',
        'fields' => ['job_id' => $int, 'policy_version' => $str, 'payload_sha256' => $str,
          'capability' => $str, 'reservation_cents' => $int, 'attempt' => $int, 'worker_id' => $str,
          'token_hash' => $str, 'lease_until' => $int, 'attempt_deadline' => $int, 'state' => $str,
          'result_sha256' => $str, 'changed' => $int],
        'primary key' => ['job_id'], 'indexes' => ['lease' => ['state', 'lease_until']],
      ],
      'famtastic_worker_budget' => [
        'description' => 'Conservative immutable per-attempt cost holds; never auto-refund uncertain work.',
        'fields' => ['reservation_key' => $str, 'month' => $str, 'job_id' => $int, 'attempt' => $int,
          'reserved_cents' => $int, 'created' => $int],
        'primary key' => ['reservation_key'], 'indexes' => ['month' => ['month']],
      ],
      'famtastic_worker_nonce' => [
        'description' => 'Short-lived signed worker request replay protection.',
        'fields' => ['nonce_key' => $str, 'expires' => $int], 'primary key' => ['nonce_key'],
        'indexes' => ['expires' => ['expires']],
      ],
    ];
  }
}
