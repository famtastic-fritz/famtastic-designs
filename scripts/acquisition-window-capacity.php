<?php
declare(strict_types=1);

/** Read-only fresh usage and owner-authorized conservative pacing budget.
 * This class never connects to SMTP, reads passwords, or reports provider quota.
 */
final class AcquisitionWindowCapacity {
  public const MODE = 'published_limit_with_reserved_budget';
  public const LIMIT_SOURCE = 'https://www.godaddy.com/en-uk/help/hosting-email-relay-limits-27150?sc_lang=en-GB';

  public static function read(array $config): array {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('capacity_cli_only');
    $now = time(); // Fresh wall clock for long-running Drush ticks, not request time.
    $account = \Drupal::service('famtastic_pipeline.acquisition_sample_sequences')->senderAccount();
    $smtp = \Drupal::config('smtp.settings');
    if (($account['provider'] ?? '') !== 'godaddy_cpanel' || ($account['transport'] ?? '') !== 'native_smtp' || ($account['from'] ?? '') !== 'hello@famtasticdesigns.com' || $smtp->get('smtp_username') !== 'hello@famtasticdesigns.com' || !in_array(strtolower(trim((string)$smtp->get('smtp_host'))), ['famtasticdesigns.com','mail.famtasticdesigns.com','p3plzcpnl497512.prod.phx3.secureserver.net'], TRUE)) throw new RuntimeException('capacity_native_hello_account_required');
    self::validate($config, (string)$account['account_sha256']);
    $db = \Drupal::database();
    $schema = $db->schema();
    $today = self::easternStart($now);
    $usage = ['checked_at'=>$now, 'sender_account_sha256'=>$account['account_sha256'], 'sources'=>[], 'unresolved_exact_transport'=>0];
    foreach (['famtastic_email_message'=>['sent_at','status','provider'], 'famtastic_notification_outbox'=>['sent_at','status']] as $table=>$fields) {
      if (!$schema->tableExists($table)) throw new RuntimeException('capacity_native_usage_source_missing');
      foreach ($fields as $field) if (!$schema->fieldExists($table,$field)) throw new RuntimeException('capacity_native_usage_field_missing');
      $source = [];
      foreach (['eastern_today'=>$today,'rolling_24h'=>$now-86400,'rolling_1h'=>$now-3600] as $window=>$start) {
        // Include all providers: possible double-counting only reduces the budget.
        // Never count future-dated records as already observed usage.
        $source[$window] = (int)$db->select($table,'u')->condition('sent_at',$start,'>=')->condition('sent_at',$now,'<=')->countQuery()->execute()->fetchField();
      }
      $source['pending_or_retry_records'] = (int)$db->select($table,'u')->condition('status',['queued','retry','processing'],'IN')->countQuery()->execute()->fetchField();
      $usage['sources'][$table] = $source;
    }
    if (!$schema->tableExists('famtastic_acquisition_dispatch') || !$schema->fieldExists('famtastic_acquisition_dispatch','status')) throw new RuntimeException('capacity_exact_attempt_source_missing');
    // An accepted-but-unrecorded transport cannot be retried or budgeted as zero.
    $usage['unresolved_exact_transport'] = (int)$db->select('famtastic_acquisition_dispatch','d')->condition('status',['reserved','uncertain'],'IN')->countQuery()->execute()->fetchField();
    return self::budget($usage,$config,$now);
  }

  public static function easternStart(int $now): int {
    return (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('America/New_York'))->setTime(0,0)->getTimestamp();
  }

  /** Aggregate input seam for synthetic tests; read() always collects it fresh. */
  public static function budget(array $usage, array $config, int $now): array {
    self::validate($config,(string)($usage['sender_account_sha256'] ?? ''));
    if (!is_int($usage['checked_at'] ?? NULL) || $usage['checked_at'] > $now || $usage['checked_at'] < $now-300 || !is_int($usage['unresolved_exact_transport'] ?? NULL) || $usage['unresolved_exact_transport'] < 0) throw new RuntimeException('capacity_fresh_native_usage_required');
    $today=0;$rolling=0;$hour=0;
    foreach (['famtastic_email_message','famtastic_notification_outbox'] as $table) {
      $source=$usage['sources'][$table] ?? [];
      foreach (['eastern_today','rolling_24h','rolling_1h','pending_or_retry_records'] as $field) if (!is_int($source[$field] ?? NULL) || $source[$field] < 0) throw new RuntimeException('capacity_complete_aggregate_counts_required');
      if ($source['rolling_1h'] > $source['rolling_24h']) throw new RuntimeException('capacity_inconsistent_aggregate_counts');
      $today += $source['eastern_today'];$rolling += $source['rolling_24h'];$hour += $source['rolling_1h'];
    }
    $observed=max($today,$rolling); // Provider daily reset is unknown; cover both windows.
    $dayReserve=$config['transactional_unobserved_day_reserve'];
    $hourReserve=$config['transactional_unobserved_hour_reserve'];
    $availableDay=max(0,min(200,500-$dayReserve-$observed));
    $availableHour=max(0,min(50,500-$hourReserve-$hour));
    if ($usage['unresolved_exact_transport'] > 0) $availableDay=$availableHour=0;
    $result=['schema'=>'famtastic.acquisition-window-capacity.v1','checked_at'=>$usage['checked_at'],'capacity_mode'=>self::MODE,'budget_kind'=>'conservative_budget','verified'=>FALSE,'outside_usage_unknown'=>TRUE,'sender_account_sha256'=>$usage['sender_account_sha256'],'available_today'=>$availableDay,'available_hour'=>$availableHour,'published_mailbox_day_limit'=>500,'published_account_hour_limit'=>500,'transactional_unobserved_day_reserve'=>$dayReserve,'transactional_unobserved_hour_reserve'=>$hourReserve,'observed_today'=>$observed,'observed_hour'=>$hour,'observed_eastern_today'=>$today,'observed_rolling_24h'=>$rolling,'sources'=>$usage['sources'],'unresolved_exact_transport'=>$usage['unresolved_exact_transport'],'reference'=>self::LIMIT_SOURCE,'provider_remaining_verified'=>FALSE,'safe_lower_bound_proved'=>FALSE,'provider_reset_timezone'=>'unknown','usage_policy'=>'Sum observed native message and notification timestamps; possible overlaps reduce budget. Unobserved mailbox, other-application, queued and account traffic is covered only by an expressly authorized reserve, not measured completeness.','enforcement'=>'Campaign quota separately enforces 200/day including followups and 50/window; no catchup; halt on first SMTP failure/uncertain result.'];
    $result['sha256']=hash('sha256',json_encode($result,JSON_THROW_ON_ERROR));
    return $result;
  }

  private static function validate(array $config, string $account): void {
    if (($config['capacity_mode'] ?? '') !== self::MODE || ($config['timezone'] ?? '') !== 'America/New_York' || ($config['day_cap'] ?? 0) !== 200 || ($config['window_cap'] ?? 0) !== 50 || !preg_match('/^[a-f0-9]{64}$/D',$account) || !hash_equals($account,(string)($config['sender_account_sha256'] ?? '')) || !is_int($config['transactional_unobserved_day_reserve'] ?? NULL) || $config['transactional_unobserved_day_reserve'] < 250 || !is_int($config['transactional_unobserved_hour_reserve'] ?? NULL) || $config['transactional_unobserved_hour_reserve'] < 400) throw new RuntimeException('capacity_owner_bound_budget_config_required');
  }
}
