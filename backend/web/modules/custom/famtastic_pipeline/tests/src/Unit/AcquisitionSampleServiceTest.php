<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleGuard;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleSchema;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleService;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleSequenceService;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleMemoryAdapter;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Real SQLite query tests; synthetic recipients only, no shared Drupal boot. */
final class AcquisitionSampleServiceTest extends UnitTestCase {

  private Connection $db;
  private AcquisitionSampleService $samples;
  private AcquisitionSampleSequenceService $sequences;
  private OperationalLedger $ledger;
  private int $now = 1800000000;
  private \Drupal\Core\Config\ConfigFactoryInterface $factory;
  private string $smtpFrom = 'sender@example.test';
  private string|false|null $previousGenericSecret = NULL;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $schemas = AcquisitionSampleSchema::tables() + _famtastic_pipeline_automation_schema() + _famtastic_pipeline_customer_portal_schema();
    foreach (['famtastic_acquisition_sample', 'famtastic_acquisition_sequence', 'famtastic_acquisition_message', 'famtastic_acquisition_request', 'famtastic_acquisition_dispatch', 'famtastic_customer', 'famtastic_consent', 'famtastic_event', 'famtastic_campaign', 'famtastic_email_message'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    $this->db->query('CREATE TABLE famtastic_prospect (id INTEGER PRIMARY KEY, public_email TEXT, campaign TEXT, business_name TEXT, business_category TEXT, contact_name TEXT)');
    $this->db->query("INSERT INTO famtastic_prospect VALUES (1,'owner@example.test','acquisition-199','Juniper Hair Studio','Beauty, Hair Styling & Braiding','Unknown owner'),(2,'other@example.test','acquisition-199','Other Fixture','Personal services','Unverified person')");
    $this->db->insert('famtastic_campaign')->fields(['id' => 1, 'campaign_key' => 'acquisition-199', 'name' => 'Synthetic sample test', 'status' => 'draft', 'created' => 1, 'changed' => 1])->execute();
    foreach ([1 => 'owner@example.test', 2 => 'other@example.test'] as $id => $email) $this->db->insert('famtastic_customer')->fields(['id' => $id, 'public_id' => 'fixture-' . $id, 'uid' => $id, 'prospect_id' => $id, 'display_name' => 'Fixture', 'email' => $email, 'created' => 1, 'changed' => 1])->execute();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn(): int => $this->now);
    $time->method('getCurrentTime')->willReturnCallback(fn(): int => $this->now);
    $this->ledger = new OperationalLedger($this->db, $time);
    $config = $this->createMock(\Drupal\Core\Config\ImmutableConfig::class);
    $config->method('get')->willReturn('123 Fictional Test Street, Example City, FL 00000');
    $factory = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $smtp = $this->createMock(\Drupal\Core\Config\ImmutableConfig::class);
    $smtp->method('get')->willReturnCallback(fn(string $key): mixed => ['smtp_on'=>TRUE,'smtp_host'=>'smtp.synthetic.invalid','smtp_port'=>587,'smtp_username'=>$this->smtpFrom,'smtp_from'=>$this->smtpFrom,'smtp_protocol'=>'tls'][$key] ?? NULL);
    $factory->method('get')->willReturnCallback(static fn(string $name): mixed => $name === 'smtp.settings' ? $smtp : $config);
    $this->factory = $factory;
    $this->samples = new AcquisitionSampleService($this->db, $time, $this->ledger, $factory);
    $this->sequences = new AcquisitionSampleSequenceService($this->db, $time, $this->ledger, $factory);
  }

  protected function tearDown(): void {
    if ($this->previousGenericSecret !== NULL) putenv($this->previousGenericSecret === FALSE ? 'FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET' : 'FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET=' . $this->previousGenericSecret);
    parent::tearDown();
  }

  private function recipes(): array {
    return array_map(static fn(string $id): array => ['id' => $id, 'version' => 1, 'title' => $id, 'artifact_path' => 'marketing/campaigns/acquisition-199/templates/' . $id . '.html', 'sha256' => hash_file('sha256', dirname(__DIR__, 8) . '/marketing/campaigns/acquisition-199/templates/' . $id . '.html'), 'review' => ['status' => 'approved', 'reviewer' => 'synthetic-test-only', 'receipt' => 'local-test'], 'recipe_ref' => ['owner' => 'component-studio', 'id' => $id, 'version' => 1, 'status' => 'registered']], ['beauty_editorial', 'beauty_service_first']);
  }

  private function issue(string $key = 'fixture:first'): array {
    return $this->samples->issue($key, 'owner@example.test', 1, 1, 'beauty_hair', $this->recipes(), ['business_name' => 'Juniper Fixture', 'locality' => 'Fictional City'], ['business_verified' => TRUE, 'niche_confirmed' => TRUE, 'confirmed_niche' => 'beauty_hair', 'contact_owned' => TRUE, 'jurisdiction_eligible' => TRUE, 'provider_eligible' => TRUE, 'history_reconciled' => TRUE, 'bindings_verified' => TRUE, 'receipt' => 'synthetic-qualification', 'sha256' => str_repeat('b', 64)], $this->now + 86400);
  }

  private function drafts(): array {
    $root = dirname(__DIR__, 8);
    $all = json_decode((string) file_get_contents($root . '/marketing/campaigns/acquisition-199/messages.json'), TRUE, 32, JSON_THROW_ON_ERROR);
    $drafts = [];
    foreach ($all['messages'] as $draft) if ($draft['niche'] === 'beauty_hair') $drafts[$draft['day']] = $draft + ['qr_sha256' => hash_file('sha256', $root . '/marketing/campaigns/acquisition-199/assets/connect-qr.png')];
    return $drafts;
  }

  public function testOpaqueHashAndReadOnlyScannerResolution(): void {
    $invite = $this->issue();
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $invite['token']);
    $snapshot = $this->db->select('famtastic_acquisition_sample', 's')->fields('s')->execute()->fetchAssoc();
    for ($i = 0; $i < 3; $i++) $this->assertTrue($this->samples->resolve($invite['token'])['illustrative']);
    $this->assertSame($snapshot, $this->db->select('famtastic_acquisition_sample', 's')->fields('s')->execute()->fetchAssoc());
    $this->assertStringNotContainsString($invite['token'], json_encode($snapshot));
    $this->assertSame(0, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
    $this->assertNull($this->samples->resolve(str_repeat('f', 64)));
  }

  public function testPreferenceIdempotencyAndCrossRecipientRecipeRejection(): void {
    $invite = $this->issue();
    $this->samples->preference($invite['token'], 'beauty_editorial');
    $this->samples->preference($invite['token'], 'beauty_editorial');
    $this->assertSame(1, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
    $this->expectException(\InvalidArgumentException::class);
    $this->samples->preference($invite['token'], 'detailing_precision');
  }

  public function testPendingContinuationSurvivesCrossDeviceVerificationAndPublicExpiry(): void {
    $invite = $this->issue();
    $this->samples->preference($invite['token'], 'beauty_editorial');
    $this->samples->beginRegistration('owner@example.test', $invite['token']);
    $this->samples->attachPending('owner@example.test', 1);
    $this->samples->endRegistration('owner@example.test');
    $this->assertNull($this->samples->continuation(1));
    $this->now += 86401;
    $this->assertNull($this->samples->resolve($invite['token']));
    $this->db->update('famtastic_customer')->fields(['verified_at' => $this->now])->condition('id', 1)->execute();
    $this->assertSame('beauty_editorial', $this->samples->claim(1)['recipe_id']);
    $this->samples->claim(1);
    $this->samples->revoke($invite['id']);
    $this->assertSame(AcquisitionSampleGuard::RETURN_PATH, $this->samples->continuation(1)['return_path']);
    $this->assertSame(1, (int) $this->db->select('famtastic_event', 'e')->condition('event_type', 'acquisition.sample_claimed')->countQuery()->execute()->fetchField());
  }

  public function testClaimRequiresVerifiedExactEmail(): void {
    $invite = $this->issue();
    $this->db->update('famtastic_customer')->fields(['verified_at' => $this->now])->condition('id', 2)->execute();
    $this->expectException(\InvalidArgumentException::class);
    $this->samples->claim(2, $invite['token']);
  }

  public function testUnverifiedClaimIsRejected(): void {
    $invite = $this->issue();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('verification_required');
    $this->samples->claim(1, $invite['token']);
  }

  public function testIssueIdempotentAndChangedReplayRejected(): void {
    $invite = $this->issue();
    $retry = $this->issue();
    $this->assertSame($invite['id'], $retry['id']);
    $this->assertNull($retry['token']);
    $this->now++;
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('invitation_replay_changed');
    $this->issue();
  }

  public function testZeroThreeSevenHeldScheduleAndImmediateConsentExit(): void {
    $invite = $this->issue();
    $sequence = $this->sequences->stage($invite['id'], 'owner@example.test', $invite['token'], $this->drafts());
    $this->assertSame([], $this->sequences->due($sequence['sequence_id']));
    $this->sequences->activate($sequence['sequence_id'], $this->now, 'synthetic-schedule-review');
    $this->assertSame([0], array_column($this->sequences->due($sequence['sequence_id']), 'day'));
    // Extend this synthetic invitation for the seven-day schedule test.
    $this->db->update('famtastic_acquisition_sample')->fields(['expires' => $this->now + 10 * 86400])->condition('id', $invite['id'])->execute();
    $this->now += 3 * 86400;
    $this->assertSame([0, 3], array_column($this->sequences->due($sequence['sequence_id']), 'day'));
    $this->ledger->recordConsent('owner@example.test', 'unsubscribed', 1);
    $this->assertSame([], $this->sequences->due($sequence['sequence_id']));
    $this->assertSame(3, (int) $this->db->select('famtastic_email_message', 'm')->condition('status', 'suppressed')->countQuery()->execute()->fetchField());
  }

  public function testTrustedReplyPurchaseComplaintBounceExitIsRecipientIsolated(): void {
    foreach (['email.replied', 'payment.fulfillment_started', 'email.complained', 'email.bounced'] as $index => $type) {
      $invite = $this->issue('fixture:event:' . $index);
      $sequence = $this->sequences->stage($invite['id'], 'owner@example.test', $invite['token'], $this->drafts());
      $this->ledger->recordEvent('fixture:event:' . $index, $type, [], 2);
      $this->assertSame('held', $this->db->select('famtastic_acquisition_sequence', 's')->fields('s', ['status'])->condition('id', $sequence['sequence_id'])->execute()->fetchField());
      $this->ledger->recordEvent('fixture:owner:' . $index, $type, [], 1);
      $this->assertSame('stopped', $this->db->select('famtastic_acquisition_sequence', 's')->fields('s', ['status'])->condition('id', $sequence['sequence_id'])->execute()->fetchField());
      $this->db->delete('famtastic_event')->execute();
    }
  }

  public function testCandidateRecipesAndUnqualifiedRecipientsFailClosed(): void {
    $recipes = $this->recipes();
    $recipes[0]['recipe_ref']['status'] = 'import_request_pending';
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('component_recipe_registration_required');
    AcquisitionSampleGuard::recipes($recipes);
  }

  public function testUnsafeBindingsAndUrlsCannotEnterHtml(): void {
    foreach (['javascript:alert(1)', 'https://user:pass@example.com', 'https://127.0.0.1', 'https://a.local', 'https://example.com/?token=private'] as $url) {
      try { AcquisitionSampleGuard::bindings(['business_name' => 'Fixture', 'booking_url' => $url]); $this->fail('Unsafe URL admitted.'); }
      catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
    }
    $html = AcquisitionSampleGuard::render('<h1>{{business_name}}</h1><a href="{{booking_url}}">Book</a>', ['business_name' => 'Juniper & "Friends"', 'booking_url' => 'https://booksy.com/example']);
    $this->assertStringContainsString('Juniper &amp; &quot;Friends&quot;', $html);
    $this->expectException(\InvalidArgumentException::class);
    AcquisitionSampleGuard::bindings(['business_name' => '<script>bad</script>']);
  }

  public function testFullCreativeSnapshotSyntheticAuthCaptureAndReplay(): void {
    $invite = $this->issue();
    $root = dirname(__DIR__, 8);
    $data = json_decode((string) file_get_contents($root . '/marketing/campaigns/acquisition-199/messages.json'), TRUE, 32, JSON_THROW_ON_ERROR);
    $drafts = [];
    foreach ($data['messages'] as $draft) if ($draft['niche'] === 'beauty_hair') $drafts[$draft['day']] = $draft + ['qr_sha256' => hash_file('sha256', $root . '/marketing/campaigns/acquisition-199/assets/connect-qr.png')];
    $sequence = $this->sequences->stage($invite['id'], 'owner@example.test', $invite['token'], $drafts);
    $this->assertSame($sequence['message_ids'], $this->sequences->stage($invite['id'], 'owner@example.test', $invite['token'], $drafts)['message_ids']);
    $this->sequences->activate($sequence['sequence_id'], $this->now, 'synthetic-exact-schedule');
    $message = $sequence['message_ids'][0];
    $hash = $this->db->select('famtastic_acquisition_message', 'm')->fields('m', ['content_hash'])->condition('message_id', $message)->execute()->fetchField();
    $manifest = ['transport' => 'synthetic_memory_only', 'sequence_id' => $sequence['sequence_id'], 'approval_ref' => 'synthetic-content-review', 'expires' => $this->now + 3600, 'messages' => [(string) $message => ['recipient' => 'owner@example.test', 'content_hash' => $hash]]];
    $time = $this->createMock(TimeInterface::class); $time->method('getRequestTime')->willReturnCallback(fn(): int => $this->now);$time->method('getCurrentTime')->willReturnCallback(fn(): int => $this->now);
    $adapter = new AcquisitionSampleMemoryAdapter($this->db, $time, $this->ledger, $this->sequences);
    $previous = getenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET');
    $secret = str_repeat('synthetic-local-only-', 3);
    putenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET=' . $secret);
    try {
      try { $adapter->capture($sequence['sequence_id'], $message, $manifest, str_repeat('0', 64)); $this->fail('Bad signature admitted.'); }
      catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
      $signature = hash_hmac('sha256', json_encode($manifest, JSON_THROW_ON_ERROR), $secret);
      $captured = $adapter->capture($sequence['sequence_id'], $message, $manifest, $signature);
      $this->assertFalse($captured['inbox_delivery']);
      $this->assertStringContainsString('data-famtastic-email-brand=', $captured['captured']['html']);
      $this->assertStringContainsString('cid:connect-qr', $captured['captured']['html']);
      $this->assertTrue($adapter->capture($sequence['sequence_id'], $message, $manifest, $signature)['duplicate']);
      $this->assertSame(1, (int) $this->db->select('famtastic_event', 'e')->condition('event_type', 'email.sent')->countQuery()->execute()->fetchField());
      $bad = $manifest; $bad['messages'][(string) $message]['recipient'] = 'customer@example.com';
      try { $adapter->capture($sequence['sequence_id'], $message, $bad, hash_hmac('sha256', json_encode($bad, JSON_THROW_ON_ERROR), $secret)); $this->fail('Non-synthetic recipient admitted.'); }
      catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
    }
    finally { putenv($previous === FALSE ? 'FAMTASTIC_ACQUISITION_MEMORY_SECRET' : 'FAMTASTIC_ACQUISITION_MEMORY_SECRET=' . $previous); }
  }

  public function testReviewedObservationAndNativeReplyQualityDedup(): void {
    $observation=['text'=>'Your public page lists braiding appointments.','source_url'=>'https://example.test/services','checked_at'=>1800000000,'classification'=>'verified_public_fact','owner_approved'=>TRUE];
    $bindings=AcquisitionSampleGuard::bindings(['business_name'=>'Juniper Fixture','research_observation'=>$observation]);
    $draft=$this->drafts()[0];
    $snapshot=\Drupal\famtastic_pipeline\Service\AcquisitionSampleEmail::compile($draft,$bindings,str_repeat('a',64),str_repeat('b',48),$draft['qr_sha256']);
    $this->assertStringContainsString($observation['text'],$snapshot['body']);
    $this->assertSame($observation,$snapshot['research_observation']);
    $invite=$this->issue('fixture:reply-quality');$hash=$this->ledger->contactHash('owner@example.test');
    AcquisitionSampleSequenceService::recordReply($this->db,$hash,'incoming:fixture',FALSE,$this->now);
    AcquisitionSampleSequenceService::recordReply($this->db,$hash,'incoming:fixture',FALSE,$this->now);
    AcquisitionSampleSequenceService::recordReply($this->db,$hash,'portal:fixture',TRUE,$this->now);
    $this->assertSame(1,(int)$this->db->select('famtastic_event','e')->condition('event_type','acquisition.reply_received')->countQuery()->execute()->fetchField());
    $payload=$this->db->select('famtastic_event','e')->fields('e',['payload'])->condition('event_type','acquisition.reply_received')->execute()->fetchField();
    $this->assertSame('unknown',json_decode($payload,TRUE)['machine_activity']);
    $this->assertSame(1,(int)$this->db->select('famtastic_event','e')->condition('event_type','acquisition.human_reply')->countQuery()->execute()->fetchField());
    $observation['owner_approved']=FALSE;
    $this->expectException(\InvalidArgumentException::class);
    AcquisitionSampleGuard::bindings(['business_name'=>'Juniper Fixture','research_observation'=>$observation]);
  }

  public function testMappedRequestPurchaseAndClaimedCustomerReconciliation(): void {
    $invite = $this->issue('fixture:purchase');
    $this->db->update('famtastic_customer')->fields(['verified_at' => $this->now, 'prospect_id' => 999])->condition('id',1)->execute();
    $this->samples->claim(1, $invite['token']);
    $seq = $this->sequences->stage($invite['id'], 'owner@example.test', $invite['token'], $this->drafts());
    $this->sequences->activate($seq['sequence_id'], $this->now, 'synthetic-purchase');
    $this->db->insert('famtastic_acquisition_request')->fields(['request_id'=>42,'invitation_id'=>$invite['id'],'customer_id'=>1,'request_prospect_id'=>999,'created'=>$this->now])->execute();
    $this->ledger->recordEvent('synthetic:mapped:payment', 'payment.fulfillment_started', [], 999);
    $this->assertSame([], $this->sequences->due($seq['sequence_id']));
    $this->assertSame('purchase', $this->db->select('famtastic_acquisition_sequence','s')->fields('s',['stop_reason'])->condition('id',$seq['sequence_id'])->execute()->fetchField());
    $second = $this->issue('fixture:commerce-history');
    $this->samples->claim(1, $second['token']);
    $this->db->query('CREATE TABLE famtastic_commerce_fulfillment (id INTEGER PRIMARY KEY, customer_id INTEGER)');
    $this->db->query('INSERT INTO famtastic_commerce_fulfillment VALUES (1,1)');
    $secondSeq = $this->sequences->stage($second['id'], 'owner@example.test', $second['token'], $this->drafts());
    $this->assertSame('purchase', $this->db->select('famtastic_acquisition_sequence','s')->fields('s',['stop_reason'])->condition('id',$secondSeq['sequence_id'])->execute()->fetchField());
  }

  public function testFrozenGreetingMediaAndExactDisabledSenderReservationReplay(): void {
    $invite = $this->issue('fixture:exact');
    $this->db->update('famtastic_acquisition_sample')->fields(['bindings'=>json_encode(['business_name'=>'Juniper Fixture','recipient_name'=>'Verified Jane'])])->condition('id',$invite['id'])->execute();
    $seq = $this->sequences->stage($invite['id'],'owner@example.test',$invite['token'],$this->drafts());
    $this->sequences->activate($seq['sequence_id'],$this->now,'synthetic-exact-schedule');
    $message = $seq['message_ids'][0];
    $content = $this->db->select('famtastic_acquisition_message','c')->fields('c')->condition('message_id',$message)->execute()->fetchAssoc();
    $snapshot = json_decode($content['snapshot'],TRUE);
    $this->assertSame('verified_recipient_name',$snapshot['greeting_source']);
    $this->assertStringContainsString('Hello, Verified Jane,',$snapshot['body']);
    $this->assertCount(3,$snapshot['attachments']);
    $this->assertStringContainsString('/web/api/pipeline/email/click/', $snapshot['body']);
    $this->assertStringContainsString('/web/api/pipeline/email/open/', $snapshot['html']);
    foreach ($snapshot['attachments'] as $attachment) $this->assertSame($attachment['sha256'],hash('sha256',base64_decode($attachment['bytes_base64'])));
    $sample = $this->db->select('famtastic_acquisition_sample','s')->fields('s')->condition('id',$invite['id'])->execute()->fetchAssoc();
    $receipt=['status'=>'owner_reviewed','reference'=>'synthetic-only','sha256'=>str_repeat('c',64)];
    $manifest=['schema'=>'famtastic.acquisition-exact-send.v1','transport'=>'native_smtp','cap'=>1,'sequence_id'=>$seq['sequence_id'],'message_id'=>$message,'recipient'=>'owner@example.test','from'=>'sender@example.test','content_id'=>$content['content_id'],'content_hash'=>$content['content_hash'],'qualification_ref'=>$sample['qualification_ref'],'invitation_evidence_hash'=>$sample['evidence_hash'],'approval_ref'=>'synthetic-exact-owner-review','expires'=>$this->now+3600,'provider_permission_receipt'=>$receipt,'history_receipt'=>$receipt,'release_proof'=>$receipt];
    $time=$this->createMock(TimeInterface::class); $time->method('getRequestTime')->willReturn($this->now);$time->method('getCurrentTime')->willReturn($this->now);
    $mailer=$this->createMock(\Drupal\famtastic_pipeline\Service\OutreachMailer::class);
    $mailer->method('fromAddress')->willReturn('sender@example.test');
    $mailer->method('assertAcquisitionTransportAllowed')->willThrowException(new \RuntimeException('acquisition_real_dispatch_disabled'));
    $adapter=new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($this->db,$time,$this->ledger,$this->sequences,$mailer);
    $secret=str_repeat('synthetic-owner-key-',3);$previous=getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET='.$secret);
    try {
      $signature=hash_hmac('sha256',json_encode($manifest),$secret);
      $this->db->update('famtastic_email_message')->fields(['recipient_address'=>'drifted@example.test'])->condition('id',$message)->execute();
      $drifted=$manifest;$drifted['recipient']='drifted@example.test';
      try {$adapter->dispatch($drifted,hash_hmac('sha256',json_encode($drifted),$secret));$this->fail('Drifted recipient admitted.');}catch(\InvalidArgumentException $e){$this->assertSame('acquisition_exact_binding_required',$e->getMessage());}
      $this->db->update('famtastic_email_message')->fields(['recipient_address'=>'owner@example.test'])->condition('id',$message)->execute();
      $native=$this->db->select('famtastic_email_message','m')->fields('m',['unsubscribe_key'])->condition('id',$message)->execute()->fetchField();
      $this->db->update('famtastic_email_message')->fields(['unsubscribe_key'=>str_repeat('e',48)])->condition('id',$message)->execute();
      try {$adapter->dispatch($manifest,$signature);$this->fail('Changed unsubscribe header admitted.');}catch(\InvalidArgumentException $e){$this->assertSame('acquisition_native_header_content_drift',$e->getMessage());}
      $this->db->update('famtastic_email_message')->fields(['unsubscribe_key'=>$native])->condition('id',$message)->execute();
      try {$adapter->dispatch($manifest,str_repeat('0',64));$this->fail('Bad owner signature accepted.');}catch(\InvalidArgumentException){$this->addToAssertionCount(1);}
      try {$adapter->dispatch($manifest,$signature);$this->fail('Disabled dispatch accepted.');}catch(\RuntimeException $e){$this->assertSame('acquisition_real_dispatch_disabled',$e->getMessage());}
      $this->assertSame(0,(int)$this->db->select('famtastic_acquisition_dispatch','d')->countQuery()->execute()->fetchField());
      $capture=$this->createMock(\Drupal\famtastic_pipeline\Service\OutreachMailer::class);
      $capture->method('fromAddress')->willReturn('sender@example.test');
      $capture->expects($this->once())->method('sendFrozenAcquisition')->willReturn('<synthetic-exact@example.test>');
      $adapter=new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($this->db,$time,$this->ledger,$this->sequences,$capture);
      $this->assertFalse($adapter->dispatch($manifest,$signature)['inbox_delivery']);
      $this->assertTrue($adapter->dispatch($manifest,$signature)['duplicate']);
      $this->db->update('famtastic_acquisition_dispatch')->fields(['status'=>'uncertain'])->condition('message_id',$message)->execute();
      try {$adapter->dispatch($manifest,$signature);$this->fail('Uncertain dispatch retried.');}catch(\RuntimeException $e){$this->assertSame('acquisition_dispatch_reserved_or_uncertain_no_retry',$e->getMessage());}
      $this->now += 3*86400;
      $this->db->update('famtastic_acquisition_sample')->fields(['expires'=>$this->now+86400])->condition('id',$invite['id'])->execute();
      $follow=$this->db->select('famtastic_acquisition_message','c')->fields('c')->condition('message_id',$seq['message_ids'][1])->execute()->fetchAssoc();
      $followManifest=$manifest;$followManifest['message_id']=$seq['message_ids'][1];$followManifest['content_id']=$follow['content_id'];$followManifest['content_hash']=$follow['content_hash'];$followManifest['expires']=$this->now+3600;
      $currentTime=$this->createMock(TimeInterface::class);$currentTime->method('getRequestTime')->willReturn($this->now);$currentTime->method('getCurrentTime')->willReturn($this->now);
      $failure=$this->createMock(\Drupal\famtastic_pipeline\Service\OutreachMailer::class);$failure->method('fromAddress')->willReturn('sender@example.test');
      $failure->expects($this->once())->method('sendFrozenAcquisition')->willThrowException(new \RuntimeException('synthetic-connection-uncertain'));
      $adapter=new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($this->db,$currentTime,$this->ledger,$this->sequences,$failure);
      try {$adapter->dispatch($followManifest,hash_hmac('sha256',json_encode($followManifest),$secret));$this->fail('Approval cap bypassed.');}catch(\RuntimeException $e){$this->assertSame('acquisition_approval_cap_exhausted',$e->getMessage());}
      $followManifest['approval_ref']='synthetic-followup-distinct-approval';
      $followSignature=hash_hmac('sha256',json_encode($followManifest),$secret);
      try {$adapter->dispatch($followManifest,$followSignature);$this->fail('Synthetic failure ignored.');}catch(\RuntimeException $e){$this->assertSame('acquisition_dispatch_uncertain_manual_reconciliation_required',$e->getMessage());}
      $this->assertSame('uncertain',$this->db->select('famtastic_acquisition_dispatch','d')->fields('d',['status'])->condition('message_id',$followManifest['message_id'])->execute()->fetchField());
      try {$adapter->dispatch($followManifest,$followSignature);$this->fail('Uncertain retry admitted.');}catch(\RuntimeException $e){$this->assertSame('acquisition_dispatch_reserved_or_uncertain_no_retry',$e->getMessage());}

    } finally {putenv($previous===FALSE?'FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET':'FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET='.$previous);}
  }

  public function testExplicitEnvironmentZeroKeepsRealTransportDisabled(): void {
    $instance=(new \ReflectionClass(\Drupal\Core\Site\Settings::class))->getProperty('instance')->getValue();
    $settings=$instance ? \Drupal\Core\Site\Settings::getAll() : [];
    $prior=getenv('FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH');
    $global=getenv('FAMTASTIC_ALLOW_REAL_OUTREACH');
    new \Drupal\Core\Site\Settings(array_replace($settings, ['famtastic_allow_real_outreach'=>TRUE,'famtastic_allow_acquisition_real_outreach'=>TRUE]));
    putenv('FAMTASTIC_ALLOW_REAL_OUTREACH=1');putenv('FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH=0');
    try {
      $mailer=new \Drupal\famtastic_pipeline\Service\OutreachMailer($this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class),$this->createMock(\Psr\Log\LoggerInterface::class));
      $this->expectExceptionMessage('acquisition_real_dispatch_disabled');$mailer->assertAcquisitionTransportAllowed();
    } finally {
      new \Drupal\Core\Site\Settings($settings);
      putenv($prior===FALSE?'FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH':'FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH='.$prior);
      putenv($global===FALSE?'FAMTASTIC_ALLOW_REAL_OUTREACH':'FAMTASTIC_ALLOW_REAL_OUTREACH='.$global);
    }
  }

  public function testCanonicalDraftBodyCannotDriftUnderSameContentIdentity(): void {
    $invite=$this->issue('fixture:canonical-draft');$drafts=$this->drafts();$drafts[0]['paragraphs'][0]='Unreviewed arbitrary replacement';
    $this->expectExceptionMessage('canonical_sample_draft_required');
    $this->sequences->stage($invite['id'],'owner@example.test',$invite['token'],$drafts);
  }

  public function testVerifiedNicheCannotMismatchInvitation(): void {
    $qualification=['business_verified'=>TRUE,'contact_owned'=>TRUE,'jurisdiction_eligible'=>TRUE,'provider_eligible'=>TRUE,'history_reconciled'=>TRUE,'bindings_verified'=>TRUE,'niche_confirmed'=>TRUE,'confirmed_niche'=>'mobile_detailing','receipt'=>'synthetic-mismatch-only','sha256'=>str_repeat('a',64)];
    $this->expectExceptionMessage('verified_niche_binding_required');
    $this->samples->issue('fixture:niche-mismatch','owner@example.test',1,1,'beauty_hair',$this->recipes(),['business_name'=>'Juniper Fixture'],$qualification,$this->now+3600);
  }

  public function testBusinessOnlyRenderingOmitsUnknownContactPathsAcrossAllSixTemplates(): void {
    $root = dirname(__DIR__, 8);
    foreach (['beauty_editorial', 'beauty_service_first', 'detailing_precision', 'detailing_route_ready', 'baking_signature', 'catering_table_story'] as $id) {
      $template = (string) file_get_contents($root . '/marketing/campaigns/acquisition-199/templates/' . $id . '.html');
      $rendered = AcquisitionSampleGuard::render($template, ['business_name' => 'Juniper Hair Studio']);
      $this->assertStringContainsString('Juniper Hair Studio', $rendered, $id);
      $this->assertStringNotContainsString('Contact number:', $rendered, $id);
      $this->assertStringNotContainsString('Approved booking path', $rendered, $id);
      $this->assertStringNotContainsString('Approved inquiry path', $rendered, $id);
      $this->assertStringNotContainsString('href=""', $rendered, $id);
      $this->assertStringNotContainsString('{{', $rendered, $id);
    }
  }

  public function testVerifiedContactRenderingRetainsExistingFilledOutputAcrossAllSixTemplates(): void {
    $root = dirname(__DIR__, 8);
    $bindings = ['business_name' => 'Juniper Hair Studio', 'locality' => 'Example City', 'phone' => '+1 (555) 010-0000', 'booking_url' => 'https://example.test/book', 'inquiry_url' => 'https://example.test/inquire'];
    $expectedBindings = [];
    foreach ($bindings as $key => $value) $expectedBindings['{{' . $key . '}}'] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    foreach (['beauty_editorial', 'beauty_service_first', 'detailing_precision', 'detailing_route_ready', 'baking_signature', 'catering_table_story'] as $id) {
      $template = (string) file_get_contents($root . '/marketing/campaigns/acquisition-199/templates/' . $id . '.html');
      $rendered = AcquisitionSampleGuard::render($template, $bindings);
      $this->assertSame(strtr($template, $expectedBindings), $rendered, $id);
      $this->assertStringContainsString('<p>Contact number: +1 (555) 010-0000</p>', $rendered, $id);
      $this->assertStringContainsString('href="https://example.test/book"', $rendered, $id);
      $this->assertStringContainsString('href="https://example.test/inquire"', $rendered, $id);
      $partial = AcquisitionSampleGuard::render($template, array_replace($bindings, ['booking_url' => '']));
      $this->assertStringNotContainsString('Approved booking path', $partial, $id);
      $this->assertStringContainsString('href="https://example.test/inquire"', $partial, $id);
      $this->assertStringContainsString('Contact number: +1 (555) 010-0000', $partial, $id);
      $this->assertStringNotContainsString('href=""', $partial, $id);
    }
  }

  private function genericRecipe(): array {
    $path = 'marketing/campaigns/acquisition-199/generic-review/beauty-template.html';
    return ['id' => 'beauty_soft_power_acquisition', 'version' => 1, 'niche' => 'beauty_hair', 'title' => 'Soft Power', 'summary' => 'Illustrative beauty direction', 'artifact_path' => $path, 'sha256' => hash_file('sha256', dirname(__DIR__, 8) . '/' . $path), 'review' => ['status' => 'candidate'], 'recipe_ref' => ['owner' => 'component-studio', 'id' => 'beauty_soft_power_acquisition', 'version' => 1, 'status' => 'import_request_pending']];
  }

  private function prepare(string $key = 'fixture:generic'): array {
    $instance = (new \ReflectionClass(\Drupal\Core\Site\Settings::class))->getProperty('instance')->getValue();
    $settings = $instance ? \Drupal\Core\Site\Settings::getAll() : [];
    new \Drupal\Core\Site\Settings($settings + ['famtastic_acquisition_internal_preparation' => TRUE]);
    try { return $this->samples->prepareGeneric($key, 1, 1, $this->genericRecipe(), $this->now + 3600, TRUE); }
    finally { new \Drupal\Core\Site\Settings($settings); }
  }

  public function testGenericStoredContextIsSuppliedOpaqueAndNeverContactVerified(): void {
    $invitation = $this->prepare();
    $sample = $this->samples->resolve($invitation['token']);
    $this->assertSame('supplied_generic_preparation', $sample['context_classification']);
    $this->assertSame('Juniper Hair Studio', $sample['business_name']);
    $this->assertSame('Beauty, Hair Styling & Braiding', $sample['industry']);
    $this->assertFalse($sample['context_provenance']['niche_verified']);
    $this->assertSame('unknown', $sample['context_provenance']['contact_ownership']);
    $this->assertSame('', $sample['bindings']['recipient_name']);
    $this->assertSame('', $sample['bindings']['phone']);
    $this->assertSame('', $sample['bindings']['booking_url']);
    $this->assertCount(1, $sample['recipes']);
    $this->assertStringNotContainsString('owner@example.test', json_encode($sample));
    $this->assertStringNotContainsString('Unknown owner', json_encode($sample));
    $this->assertStringContainsString('Juniper Hair Studio', $this->samples->preview($invitation['token'], $sample['recipes'][0]['id']));
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $invitation['token']);
    $row = $this->db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitation['id'])->execute()->fetchAssoc();
    $this->assertNull($row['eligible_at']);
    $this->assertSame('', $row['qualification_ref']);
    $this->assertStringNotContainsString($invitation['token'], json_encode($row));
    $this->assertTrue($this->prepare()['duplicate']);
    $this->assertNull($this->prepare()['token']);
  }

  public function testGenericCandidateIsDisabledOutsideExplicitInternalFixture(): void {
    new \Drupal\Core\Site\Settings([]);
    $this->expectExceptionMessage('internal_sample_preparation_disabled');
    $this->samples->prepareGeneric('fixture:held', 1, 1, $this->genericRecipe(), $this->now + 3600, TRUE);
  }

  public function testGenericPreparationCannotStageOutreach(): void {
    $invitation = $this->prepare();
    try {
      $this->sequences->stage($invitation['id'], 'owner@example.test', $invitation['token'], $this->drafts());
      $this->fail('Generic preparation staged outreach.');
    }
    catch (\InvalidArgumentException $error) { $this->assertSame('sample_outreach_qualification_required', $error->getMessage()); }
    $this->assertSame(0, (int) $this->db->select('famtastic_acquisition_sequence', 's')->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $this->db->select('famtastic_email_message', 'm')->countQuery()->execute()->fetchField());
  }

  public function testGenericKnownInformationSurvivesVerificationAndPublicExpiry(): void {
    $invitation = $this->prepare();
    $this->samples->preference($invitation['token'], 'beauty_soft_power_acquisition');
    $this->samples->beginRegistration('owner@example.test', $invitation['token']);
    $this->samples->attachPending('owner@example.test', 1);
    $this->samples->endRegistration('owner@example.test');
    $this->assertNull($this->samples->continuation(1));
    $this->now += 3601;
    $this->db->update('famtastic_customer')->fields(['verified_at' => $this->now])->condition('id', 1)->execute();
    $continuation = $this->samples->claim(1);
    $this->assertSame(['business_name' => 'Juniper Hair Studio', 'industry' => 'Beauty, Hair Styling & Braiding', 'business_category' => 'Beauty, Hair Styling & Braiding'], $continuation['known_information']);
    $this->assertSame('supplied_generic_preparation', $continuation['context_classification']);
    $this->assertNull($this->samples->resolve($invitation['token']));
    $this->assertStringNotContainsString($invitation['token'], json_encode($continuation));
    $context = $this->samples->requestContext(1, $continuation['context_id']);
    $this->assertSame($continuation['known_information'], $context['known_information']);
    $this->assertFalse($context['formal_proof_selection']);
    $this->samples->revoke($invitation['id']);
    $this->assertSame($continuation['known_information'], $this->samples->continuation(1)['known_information']);
  }

  public function testBroadUnrelatedIndustryCannotUseBeautyCandidate(): void {
    $this->db->update('famtastic_prospect')->fields(['business_category' => 'Personal services'])->condition('id', 1)->execute();
    $this->expectExceptionMessage('preparation_recipe_industry_mismatch');
    $this->prepare();
  }

  public function testBroadBeautyRemainsSuppliedAndNotNicheQualification(): void {
    $this->db->update('famtastic_prospect')->fields(['business_category' => 'Beauty'])->condition('id', 1)->execute();
    $invitation = $this->prepare();
    $sample = $this->samples->resolve($invitation['token']);
    $this->assertSame('Beauty', $sample['industry']);
    $this->assertFalse($sample['context_provenance']['niche_verified']);
    $this->assertSame('stored_supplied', $sample['context_provenance']['industry_provenance']);
  }

  public function testKnownBarberCannotUseHairBeautyCandidate(): void {
    $this->db->update('famtastic_prospect')->fields(['business_category' => 'Barber'])->condition('id', 1)->execute();
    $this->expectExceptionMessage('preparation_recipe_industry_mismatch');
    $this->prepare();
  }

  public function testGenericUnknownBusinessUsesDisplayFallbackWithoutInventingKnownName(): void {
    $this->db->update('famtastic_prospect')->fields(['business_name' => '  '])->condition('id', 1)->execute();
    $invitation = $this->prepare();
    $sample = $this->samples->resolve($invitation['token']);
    $this->assertSame('Your business', $sample['business_name']);
    $this->assertSame('unknown', $sample['context_provenance']['business_name_provenance']);
    $this->assertStringContainsString('Your business', $this->samples->preview($invitation['token'], 'beauty_soft_power_acquisition'));
    $this->db->update('famtastic_customer')->fields(['verified_at' => $this->now])->condition('id', 1)->execute();
    $continuation = $this->samples->claim(1, $invitation['token']);
    $this->assertSame('', $continuation['known_information']['business_name']);
    $this->assertSame('Beauty, Hair Styling & Braiding', $continuation['known_information']['industry']);
    $this->assertSame('', $this->samples->requestContext(1, $continuation['context_id'])['known_information']['business_name']);
  }

  private function genericAuthorizationReceipt(array $invitation): array {
    $sample = $this->db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitation['id'])->execute()->fetchAssoc();
    $account = AcquisitionSampleGuard::senderAccount($this->factory);
    $binding = ['invitation_id'=>(int)$sample['id'],'prospect_id'=>(int)$sample['prospect_id'],'campaign_id'=>(int)$sample['campaign_id'],'recipient_hash'=>$sample['recipient_hash'],'invitation_evidence_hash'=>$sample['evidence_hash'],'account_sha256'=>$account['account_sha256'],'from'=>$account['from']];
    $receipt = ['status'=>'owner_reviewed','reference'=>'synthetic-source-proof-not-real-permission','sha256'=>str_repeat('c',64),'checked_at'=>$this->now,'binding'=>$binding];
    return ['schema'=>'famtastic.acquisition-generic-authorization.v1','issued_at'=>$this->now,'expires'=>$this->now+3600,'binding'=>$binding,'sender'=>$account,'provider_permission_receipt'=>$receipt+['provider'=>'godaddy_cpanel','policy'=>'opt_in_only','permitted_use'=>TRUE,'written_opt_in_reference'=>'synthetic-only','written_opt_in_sha256'=>str_repeat('d',64)],'history_receipt'=>$receipt+['classification'=>'actual_native_history_reconciled','coverage_complete'=>TRUE,'eligible_for_new_outreach'=>TRUE,'known_stop_reasons'=>[]],'creative_approval'=>['reference'=>'docs/research/acquisition-199/CREATIVE-APPROVAL.json','sha256'=>hash_file('sha256',dirname(__DIR__,8).'/docs/research/acquisition-199/CREATIVE-APPROVAL.json')],'content_id'=>'acquisition-199:beauty_soft_power_generic_d0:v2'];
  }

  private function genericSignature(array $value): string {
    if ($this->previousGenericSecret === NULL) $this->previousGenericSecret = getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');
    $secret = str_repeat('synthetic-generic-key-',3);
    putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET=' . $secret);
    return hash_hmac('sha256',json_encode($value,JSON_THROW_ON_ERROR),$secret);
  }

  public function testGenericPostalMultilineNormalizesBeforeFreezing(): void {
    $invitation=$this->prepare();$authorization=$this->genericAuthorizationReceipt($invitation);
    $snapshot=\Drupal\famtastic_pipeline\Service\AcquisitionSampleEmail::compileGeneric($authorization,$invitation['token'],str_repeat('a',48),str_repeat('b',48),"  123 Fictional Test Street\r\nSuite\t42\nExample City, FL 00000  ");
    $expected='123 Fictional Test Street Suite 42 Example City, FL 00000';
    $this->assertSame($expected,$snapshot['postal_address']);
    $this->assertStringContainsString($expected,$snapshot['body']);
    $this->assertStringContainsString($expected,$snapshot['html']);
    $registration='https://famtasticdesigns.com/login?mode=register&sample_continuation='.$invitation['token'];
    $this->assertSame('acquisition-199:beauty_soft_power_generic_d0:v2',$snapshot['content_id']);
    $this->assertStringContainsString($registration,$snapshot['body']);
    $this->assertStringContainsString(htmlspecialchars($registration,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'),$snapshot['html']);
    $this->assertStringNotContainsString('registration-not-bound',$snapshot['html'].$snapshot['body']);
    $this->assertStringContainsString('Create your free account and complete your website interview.',$snapshot['body']);
    $this->assertStringContainsString('We aim for same-day launch once your site is approved, payment is complete and the domain is ready.',$snapshot['body']);
    $single=\Drupal\famtastic_pipeline\Service\AcquisitionSampleEmail::compileGeneric($authorization,$invitation['token'],str_repeat('a',48),str_repeat('b',48),$expected);
    $this->assertSame($single,$snapshot);
  }

  public function testGenericPostalRejectsOtherControlsAndMarkup(): void {
    $invitation=$this->prepare();$authorization=$this->genericAuthorizationReceipt($invitation);
    foreach (["123 Fictional\x00Street","123 Fictional\x01Street","123 Fictional\x0bStreet","123 Fictional\x0cStreet","123 Fictional\x1fStreet","123 Fictional\x7fStreet","\x00Street","Street\x0b",'<script>Street</script>',"\r\n\t "] as $postal) {
      try { \Drupal\famtastic_pipeline\Service\AcquisitionSampleEmail::compileGeneric($authorization,$invitation['token'],str_repeat('a',48),str_repeat('b',48),$postal);$this->fail('Unsafe or empty postal address admitted.'); }
      catch (\InvalidArgumentException $error) { $this->assertSame('generic_native_bindings_required',$error->getMessage()); }
    }
  }

  private function coldAuthorizationReceipt(array $invitation): array {
    $this->smtpFrom = 'hello@famtasticdesigns.com';
    $authorization = $this->genericAuthorizationReceipt($invitation);
    $owner = $authorization['provider_permission_receipt'];
    unset($authorization['provider_permission_receipt']);
    $authorization += ['authorization_basis' => 'owner_authorized_cold_outreach', 'provider_policy_conflict' => TRUE, 'recipient_opt_in' => FALSE, 'provider_permission_proved' => FALSE, 'owner_authorization_receipt' => array_intersect_key($owner, array_flip(['status', 'reference', 'sha256', 'checked_at', 'binding'])) + ['approved_by' => 'Fritz Medine']];
    $path='docs/research/acquisition-199/OWNER-COLD-SEND-AUTHORIZATION.json';
    $authorization['owner_authorization_record']=file_get_contents(dirname(__DIR__,8).'/'.$path);
    $authorization['owner_authorization_receipt']['reference']=$path;
    $authorization['owner_authorization_receipt']['sha256']=hash('sha256',$authorization['owner_authorization_record']);
    return $authorization;
  }

  public function testColdOwnerBasisRequiresTruthfulFreshExactOwnerAndHistoryEvidence(): void {
    $invitation = $this->prepare();
    $authorization = $this->coldAuthorizationReceipt($invitation);
    $mutations = [
      static function(array $a): array { unset($a['owner_authorization_receipt']); return $a; },
      static function(array $a): array { $a['owner_authorization_receipt']['approved_by']='Other owner'; return $a; },
      static function(array $a): array { $a['owner_authorization_receipt']['reference']=''; return $a; },
      static function(array $a): array { $a['owner_authorization_receipt']['sha256']='invalid'; return $a; },
      static function(array $a): array { $a['owner_authorization_receipt']['checked_at']-=3601; return $a; },
      static function(array $a): array { $a['owner_authorization_receipt']['binding']['prospect_id']=2; return $a; },
      static function(array $a): array { $a['owner_authorization_receipt']['binding']['account_sha256']=str_repeat('e',64); return $a; },
      static function(array $a): array { $a['sender']['account_sha256']=str_repeat('e',64); return $a; },
      static function(array $a): array { $a['provider_policy_conflict']=FALSE; return $a; },
      static function(array $a): array { $a['recipient_opt_in']=TRUE; return $a; },
      static function(array $a): array { $a['provider_permission_proved']=TRUE; return $a; },
      static function(array $a): array { $a['provider_permission_receipt']=[]; return $a; },
      static function(array $a): array { $a['authorization_basis']='unknown'; return $a; },
      static function(array $a): array { unset($a['owner_authorization_record']); return $a; },
      static function(array $a): array { $a['owner_authorization_record'].='changed'; return $a; },
      static function(array $a): array { $a['history_receipt']['known_stop_reasons']=['prior_reply']; return $a; },
    ];
    foreach ($mutations as $mutate) {
      $bad=$mutate($authorization);
      try { $this->samples->authorizeGeneric($invitation['id'],$bad,$this->genericSignature($bad)); $this->fail('Invalid owner cold evidence admitted.'); }
      catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
      $this->assertNull($this->db->select('famtastic_acquisition_sample','s')->fields('s',['eligible_at'])->condition('id',$invitation['id'])->execute()->fetchField());
    }
    $this->assertFalse($this->samples->authorizeGeneric($invitation['id'],$authorization,$this->genericSignature($authorization))['staged']);
    $this->assertTrue($this->samples->authorizeGeneric($invitation['id'],$authorization,$this->genericSignature($authorization))['duplicate']);
    $changed=$authorization; $changed['owner_authorization_receipt']['checked_at']--;
    try { $this->samples->authorizeGeneric($invitation['id'],$changed,$this->genericSignature($changed)); $this->fail('Changed cold approval replay admitted.'); }
    catch (\InvalidArgumentException $e) { $this->assertSame('generic_authorization_replay_changed',$e->getMessage()); }
    $this->assertSame(0,(int)$this->db->select('famtastic_email_message','m')->countQuery()->execute()->fetchField());
    $sequence=$this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token']);
    $snapshot=json_decode($this->db->select('famtastic_acquisition_message','m')->fields('m',['snapshot'])->condition('message_id',$sequence['message_ids'][0])->execute()->fetchField(),TRUE);
    $this->assertSame($authorization,$snapshot['authorization']);
    $this->assertArrayNotHasKey('provider_permission_receipt',$snapshot['authorization']);
    $this->assertFalse($snapshot['authorization']['recipient_opt_in']);
    $this->assertFalse($snapshot['authorization']['provider_permission_proved']);
    $this->assertTrue($snapshot['authorization']['provider_policy_conflict']);
    $this->assertSame(0,(int)$this->db->select('famtastic_consent','c')->countQuery()->execute()->fetchField());
  }

  public function testGenericD0RequiresFreshExactEvidenceAndKeepsPreparationUnstaged(): void {
    $invitation = $this->prepare();
    $authorization = $this->genericAuthorizationReceipt($invitation);
    $mutations = [
      static function(array $a):array {$a['binding']['recipient_hash']=str_repeat('e',64);return $a;},
      static function(array $a):array {$a['sender']['account_sha256']=str_repeat('e',64);return $a;},
      static function(array $a):array {$a['provider_permission_receipt']['binding']['prospect_id']=2;return $a;},
      static function(array $a):array {$a['provider_permission_receipt']['permitted_use']=FALSE;return $a;},
      static function(array $a):array {$a['provider_permission_receipt']['written_opt_in_sha256']='';return $a;},
      static function(array $a):array {$a['history_receipt']['coverage_complete']=FALSE;return $a;},
      static function(array $a):array {$a['history_receipt']['eligible_for_new_outreach']=FALSE;return $a;},
      static function(array $a):array {$a['history_receipt']['known_stop_reasons']=['prior_reply'];return $a;},
      static function(array $a):array {$a['history_receipt']['checked_at']-=3601;return $a;},
      static function(array $a):array {$a['expires']-=3601;return $a;},
      static function(array $a):array {$a['creative_approval']['sha256']=str_repeat('e',64);return $a;},
    ];
    foreach ($mutations as $mutate) {
      $bad=$mutate($authorization);
      try {$this->samples->authorizeGeneric($invitation['id'],$bad,$this->genericSignature($bad));$this->fail('Invalid generic evidence accepted.');}
      catch (\InvalidArgumentException) {$this->addToAssertionCount(1);}
      $this->assertNull($this->db->select('famtastic_acquisition_sample','s')->fields('s',['eligible_at'])->condition('id',$invitation['id'])->execute()->fetchField());
    }
    try {$this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token']);$this->fail('Bare preparation staged.');}
    catch(\InvalidArgumentException $error){$this->assertSame('sample_outreach_qualification_required',$error->getMessage());}
    $this->assertSame(0,(int)$this->db->select('famtastic_email_message','m')->countQuery()->execute()->fetchField());
  }

  public function testApprovedGenericD0FreezesNewBytesOnlyAndMemoryCaptureIsIdempotent(): void {
    $invitation=$this->prepare();$authorization=$this->genericAuthorizationReceipt($invitation);$signature=$this->genericSignature($authorization);
    $this->assertFalse($this->samples->authorizeGeneric($invitation['id'],$authorization,$signature)['staged']);
    $this->assertTrue($this->samples->authorizeGeneric($invitation['id'],$authorization,$signature)['duplicate']);
    try {$this->sequences->stage($invitation['id'],'owner@example.test',$invitation['token'],$this->drafts());$this->fail('Retired draft lane admitted.');}
    catch(\InvalidArgumentException $error){$this->assertSame('generic_d0_stage_required',$error->getMessage());}
    $sequence=$this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token']);
    $this->assertCount(1,$sequence['message_ids']);
    $this->assertTrue($this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token'])['duplicate']);
    $content=$this->db->select('famtastic_acquisition_message','c')->fields('c')->condition('message_id',$sequence['message_ids'][0])->execute()->fetchAssoc();$snapshot=json_decode($content['snapshot'],TRUE);
    $this->assertSame('neutral',$snapshot['greeting_source']);$this->assertSame(1,$snapshot['sample_image_count']);$this->assertFalse($snapshot['component_studio_registration_proved']);
    $this->assertSame('See what a website could look like — FAMtastic Designs',$snapshot['subject']);
    foreach(['See what your website could look like','Same Energy. Different Message.','Always FAMtastic.','$199 upfront','Shay-Shay','cid:sample-beauty_soft_power_acquisition','cid:connect-qr'] as $text)$this->assertStringContainsString($text,$snapshot['html']);
    foreach(['beauty_editorial','beauty_service_first','example.invalid','beauty-lab.html','Owner review candidate','unbound review placeholder'] as $text)$this->assertStringNotContainsString($text,$snapshot['html'].$snapshot['body']);
    foreach($snapshot['attachments'] as $media)$this->assertSame($media['sha256'],hash('sha256',base64_decode($media['bytes_base64'])));
    $this->assertStringContainsString('/web/api/pipeline/email/click/',$snapshot['body']);$this->assertStringContainsString('/web/api/pipeline/email/open/',$snapshot['html']);
    $this->assertSame('supplied_generic_preparation',$this->samples->resolve($invitation['token'])['context_classification']);
    $this->sequences->activate($sequence['sequence_id'],$this->now,'synthetic-generic-schedule');
    $time=$this->createMock(TimeInterface::class);$time->method('getRequestTime')->willReturn($this->now);$time->method('getCurrentTime')->willReturn($this->now);
    $memory=new AcquisitionSampleMemoryAdapter($this->db,$time,$this->ledger,$this->sequences);
    $old=getenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET');$secret=str_repeat('synthetic-memory-key-',3);putenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET='.$secret);
    try {
      $manifest=['transport'=>'synthetic_memory_only','sequence_id'=>$sequence['sequence_id'],'expires'=>$this->now+3600,'approval_ref'=>'synthetic-generic-memory','messages'=>[(string)$content['message_id']=>['recipient'=>'owner@example.test','content_hash'=>$content['content_hash']]]];$sig=hash_hmac('sha256',json_encode($manifest),$secret);
      $this->assertFalse($memory->capture($sequence['sequence_id'],(int)$content['message_id'],$manifest,$sig)['inbox_delivery']);$this->assertTrue($memory->capture($sequence['sequence_id'],(int)$content['message_id'],$manifest,$sig)['duplicate']);
    }finally{putenv($old===FALSE?'FAMTASTIC_ACQUISITION_MEMORY_SECRET':'FAMTASTIC_ACQUISITION_MEMORY_SECRET='.$old);}
  }

  public function testGenericD0CurrentHistoryAndAuthorizationExpiryStop(): void {
    foreach(['permission_expired','history_reply','suppressed'] as $reason){
      $invitation=$this->prepare('fixture:generic:'.$reason);$authorization=$this->genericAuthorizationReceipt($invitation);$this->samples->authorizeGeneric($invitation['id'],$authorization,$this->genericSignature($authorization));
      $sequence=$this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token']);$this->sequences->activate($sequence['sequence_id'],$this->now,'synthetic-generic-schedule');
      if($reason==='permission_expired')$this->now+=3601;
      elseif($reason==='history_reply')$this->ledger->recordEvent('synthetic:generic:reply','email.replied',[],1,1);
      else $this->ledger->recordConsent('owner@example.test','unsubscribed',1);
      $this->assertSame([],$this->sequences->due($sequence['sequence_id']));
      $this->assertSame('stopped',$this->db->select('famtastic_acquisition_sequence','s')->fields('s',['status'])->condition('id',$sequence['sequence_id'])->execute()->fetchField());
      $this->db->delete('famtastic_event')->condition('event_type','email.replied')->execute();
    }
  }

  public function testApprovedCampaignRecipeNeedsNoInventedComponentRegistrationOrCandidateFlag(): void {
    $recipe=$this->genericRecipe();
    $recipe['review']=['status'=>'approved_campaign_artifact','approval_record'=>['reference'=>'docs/research/acquisition-199/CREATIVE-APPROVAL.json','sha256'=>hash_file('sha256',dirname(__DIR__,8).'/docs/research/acquisition-199/CREATIVE-APPROVAL.json')]];
    $invitation=$this->samples->prepareGeneric('fixture:approved-artifact',1,1,$recipe,$this->now+3600);
    $sample=$this->samples->resolve($invitation['token']);
    $this->assertSame('owner_approved_campaign_artifact',$sample['context_provenance']['recipe_review']);
    $this->assertFalse($sample['context_provenance']['component_studio_registration_proved']);
    $this->assertFalse($sample['context_provenance']['niche_verified']);
    $this->assertNull($this->db->select('famtastic_acquisition_sample','s')->fields('s',['eligible_at'])->condition('id',$invitation['id'])->execute()->fetchField());
  }

  public function testGenericExactAdapterRequiresMatchingReceiptAndPreservesCapAndUncertainRetry(): void {
    $this->assertGenericExactAdapterBasis(FALSE);
  }

  public function testColdExactAdapterRequiresMatchingOwnerBasisAndPreservesCapAndUncertainRetry(): void {
    $this->assertGenericExactAdapterBasis(TRUE);
  }

  private function assertGenericExactAdapterBasis(bool $cold): void {
    $invitation=$this->prepare();$auth=$cold ? $this->coldAuthorizationReceipt($invitation) : $this->genericAuthorizationReceipt($invitation);$this->samples->authorizeGeneric($invitation['id'],$auth,$this->genericSignature($auth));
    $sequence=$this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token']);$this->sequences->activate($sequence['sequence_id'],$this->now,'synthetic-generic-schedule');
    $content=$this->db->select('famtastic_acquisition_message','c')->fields('c')->condition('message_id',$sequence['message_ids'][0])->execute()->fetchAssoc();
    $sample=$this->db->select('famtastic_acquisition_sample','s')->fields('s')->condition('id',$invitation['id'])->execute()->fetchAssoc();
    $manifest=['schema'=>'famtastic.acquisition-exact-send.v1','transport'=>'native_smtp','cap'=>1,'sequence_id'=>$sequence['sequence_id'],'message_id'=>(int)$content['message_id'],'recipient'=>'owner@example.test','from'=>'sender@example.test','content_id'=>$content['content_id'],'content_hash'=>$content['content_hash'],'qualification_ref'=>$sample['qualification_ref'],'invitation_evidence_hash'=>$sample['evidence_hash'],'approval_ref'=>'synthetic-generic-cap1','expires'=>$this->now+3600,'history_receipt'=>$auth['history_receipt'],'release_proof'=>['status'=>'owner_reviewed','reference'=>'synthetic-release-not-hosted','sha256'=>str_repeat('e',64)],'sender_account_sha256'=>$auth['sender']['account_sha256'],'generic_authorization_hash'=>$sample['qualification_ref']];
    $permissionKey=$cold ? 'owner_authorization_receipt' : 'provider_permission_receipt';
    $manifest[$permissionKey]=$auth[$permissionKey];
    if ($cold) foreach (['authorization_basis','provider_policy_conflict','recipient_opt_in','provider_permission_proved'] as $key) $manifest[$key]=$auth[$key];
    $manifest['from']=$auth['sender']['from'];
    $time=$this->createMock(TimeInterface::class);$time->method('getRequestTime')->willReturn($this->now);$time->method('getCurrentTime')->willReturn($this->now);
    $mailer=$this->createMock(\Drupal\famtastic_pipeline\Service\OutreachMailer::class);$mailer->method('fromAddress')->willReturn($auth['sender']['from']);$mailer->expects($this->once())->method('sendFrozenAcquisition')->willReturn('<synthetic-generic-not-real@example.test>');
    $adapter=new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($this->db,$time,$this->ledger,$this->sequences,$mailer);
    $bad=$manifest;$bad[$permissionKey]['reference']='different-permission';
    try{$adapter->dispatch($bad,$this->genericSignature($bad));$this->fail('Different provider receipt admitted.');}catch(\InvalidArgumentException $e){$this->assertSame('generic_dispatch_receipt_binding_required',$e->getMessage());}
    $this->assertSame(0,(int)$this->db->select('famtastic_acquisition_dispatch','d')->countQuery()->execute()->fetchField());
    if ($cold) {
      foreach (['missing_owner','wrong_account','wrong_row','false_permission','wrong_basis'] as $case) {
        $bad=$manifest;
        if ($case==='missing_owner') unset($bad[$permissionKey]);
        elseif ($case==='wrong_account') $bad[$permissionKey]['binding']['account_sha256']=str_repeat('e',64);
        elseif ($case==='wrong_row') $bad[$permissionKey]['binding']['prospect_id']=2;
        elseif ($case==='false_permission') $bad['provider_permission_proved']=TRUE;
        else unset($bad['authorization_basis']);
        try { $adapter->dispatch($bad,$this->genericSignature($bad)); $this->fail('Invalid cold manifest admitted.'); }
        catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        $this->assertSame(0,(int)$this->db->select('famtastic_acquisition_dispatch','d')->countQuery()->execute()->fetchField());
      }
    }
    $this->assertFalse($adapter->dispatch($manifest,$this->genericSignature($manifest))['inbox_delivery']);
    $this->assertTrue($adapter->dispatch($manifest,$this->genericSignature($manifest))['duplicate']);
    $this->db->update('famtastic_acquisition_dispatch')->fields(['status'=>'uncertain'])->condition('message_id',$manifest['message_id'])->execute();
    try{$adapter->dispatch($manifest,$this->genericSignature($manifest));$this->fail('Uncertain generic send retried.');}catch(\RuntimeException $e){$this->assertSame('acquisition_dispatch_reserved_or_uncertain_no_retry',$e->getMessage());}
  }

  public function testGenericContactExistingVerifiedCustomerPurchaseStopsBeforeClaim(): void {
    $this->db->query('CREATE TABLE famtastic_commerce_fulfillment (id INTEGER PRIMARY KEY, customer_id INTEGER)');
    $this->db->query('INSERT INTO famtastic_commerce_fulfillment VALUES (1,1)');
    $this->db->update('famtastic_customer')->fields(['verified_at'=>$this->now,'prospect_id'=>2])->condition('id',1)->execute();
    $invitation=$this->prepare();$auth=$this->genericAuthorizationReceipt($invitation);$this->samples->authorizeGeneric($invitation['id'],$auth,$this->genericSignature($auth));
    $sequence=$this->sequences->stageGenericD0($invitation['id'],'owner@example.test',$invitation['token']);
    $this->assertSame('purchase',$this->db->select('famtastic_acquisition_sequence','s')->fields('s',['stop_reason'])->condition('id',$sequence['sequence_id'])->execute()->fetchField());
    $this->assertSame([],$this->sequences->due($sequence['sequence_id']));
  }

  public function testDisabledNativeSmtpCannotAuthorizeGenericEvidence(): void {
    $invitation=$this->prepare();$auth=$this->genericAuthorizationReceipt($invitation);
    $smtp=$this->createMock(\Drupal\Core\Config\ImmutableConfig::class);$smtp->method('get')->willReturnCallback(static fn(string $key):mixed=>['smtp_on'=>FALSE,'smtp_host'=>'smtp.synthetic.invalid','smtp_port'=>587,'smtp_username'=>'sender@example.test','smtp_from'=>'sender@example.test','smtp_protocol'=>'tls'][$key]??NULL);
    $factory=$this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);$factory->method('get')->willReturn($smtp);
    $time=$this->createMock(TimeInterface::class);$time->method('getRequestTime')->willReturn($this->now);$time->method('getCurrentTime')->willReturn($this->now);
    $samples=new AcquisitionSampleService($this->db,$time,$this->ledger,$factory);
    $this->expectExceptionMessage('acquisition_native_account_required');
    $samples->authorizeGeneric($invitation['id'],$auth,$this->genericSignature($auth));
  }

}
