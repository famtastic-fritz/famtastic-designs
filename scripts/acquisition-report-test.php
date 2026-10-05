<?php
declare(strict_types=1);

require dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/AcquisitionCampaignReportService.php';
use Drupal\famtastic_pipeline\Service\AcquisitionCampaignReportService as Report;

function check(bool $ok, string $name): void {
  if (!$ok) throw new RuntimeException($name);
}

$payment = ['id' => 1, 'order_id' => 100, 'state' => 'completed', 'gateway' => 'synthetic-gateway', 'mode' => 'live', 'remote_id' => 'controlled-capture', 'currency' => 'USD', 'amount_minor' => 19900, 'refunded_minor' => 0];
// These are in-memory synthetic receipts, never real gateway records.
$duplicate = $payment;
$duplicate['id'] = 2;
$money = Report::reconcilePayments([$payment, $duplicate], [100 => 10]);
check($money['revenue_minor'] === 19900 && $money['paid_customers'] === 1, 'Capture replay must not duplicate revenue/customer.');
$partial = array_replace($payment, ['state' => 'partially_refunded', 'refunded_minor' => 2500]);
$money = Report::reconcilePayments([$partial], [100 => 10]);
check($money['revenue_minor'] === 17400 && $money['refunds'] === 1, 'Commerce partial refund must reduce captured revenue.');
$full = array_replace($payment, ['state' => 'refunded', 'refunded_minor' => 19900]);
check(Report::reconcilePayments([$full], [100 => 10])['revenue_minor'] === 0, 'Commerce full refund must remove net revenue.');
check(Report::reconcilePayments([$payment], [999 => 20])['revenue_minor'] === 0, 'Unrelated order must be isolated.');
check(Report::reconcilePayments([$payment, $partial], [100 => 10])['revenue_minor'] === NULL, 'Conflicting duplicate payment evidence must withhold money.');
check(Report::reconcilePayments(NULL, [100 => 10])['paid_customers'] === NULL, 'Missing Commerce authority must remain unknown.');
check(Report::reconcilePayments([array_replace($payment, ['currency' => 'EUR'])], [100 => 10])['revenue_minor'] === NULL, 'Mixed or unsupported currency must not aggregate.');
check(Report::reconcilePayments([array_replace($payment, ['state' => 'authorization'])], [100 => 10])['revenue_minor'] === 0, 'Authorization is not captured revenue.');
check(Report::reconcilePayments([array_replace($payment, ['mode' => 'test'])], [100 => 10])['revenue_minor'] === 0, 'Sandbox capture is excluded from live totals.');
check(Report::reconcilePayments([array_replace($payment, ['amount_minor' => 0])], [100 => 10])['paid_customers'] === 0, 'Zero-dollar sponsored journey is not a paid customer.');
check(Report::usdMinor('199.00', 'USD') === 19900 && Report::usdMinor('0.55', 'USD') === 55, 'Decimal money must convert exactly.');
check(Report::usdMinor('199.001', 'USD') === NULL && Report::usdMinor('199.00', 'JPY') === NULL, 'Unsupported money must fail closed.');

$samples = [['id' => 1, 'prospect_id' => 10, 'niche' => 'beauty', 'experiment_arm' => 'generic', 'customer_id' => 70, 'preferred_recipe' => 'beauty-a', 'recipient_hash' => 'private', 'bindings' => '{"email":"person@example.test"}', 'token_hash' => 'secret']];
$message = ['id' => 9, 'prospect_id' => 10, 'template_key' => 'acquisition_sample_v1', 'message_key' => 'sample-v1:1:day:0', 'sent_at' => 1, 'opened_at' => 2, 'clicked_at' => 3];
$events = [['event_type' => 'acquisition.sample_preference', 'prospect_id' => 10], ['event_type' => 'acquisition.sample_preference', 'prospect_id' => 10], ['event_type' => 'acquisition.sample_claimed', 'prospect_id' => 10], ['event_type' => 'email.replied', 'prospect_id' => 999]];
$request = ['id' => 80, 'customer_id' => 70, 'prospect_id' => 20, 'submitted_at' => 5, 'commerce_order_id' => 100];
$mapping = ['request_id' => 80, 'invitation_id' => 1, 'customer_id' => 70, 'request_prospect_id' => 20];
$report = Report::project('controlled', $samples, [$message, $message], $events, [['id' => 70, 'verified_at' => 4]], [$request], [['commerce_order_id' => 100, 'prospect_id' => 20, 'amount_minor' => 99999999]], [$payment], [], [$mapping]);
check($report['totals']['sends'] === 1 && $report['totals']['recorded_clicks'] === 1, 'Native message rows must deduplicate.');
check($report['totals']['verified_registrations'] === 1 && $report['totals']['completed_interviews'] === 1, 'Verified account and submitted interview must join.');
check($report['totals']['revenue_minor'] === 19900, 'Commerce payment, not fulfillment snapshot or legacy price, controls revenue.');
check($report['totals']['replies'] === NULL && $report['totals']['eligible_recipients'] === NULL, 'Unattributed reply and missing eligibility receipt remain unknown.');
check($report['interaction_quality']['meaningful_preferences'] === 1 && $report['interaction_quality']['human_clicks'] === NULL, 'Raw click is not meaningful preference; repeated preference deduplicates.');
check($report['breakdowns']['message'][0]['totals']['revenue_minor'] === NULL, 'Message level paid attribution must not be invented.');
$encoded = json_encode($report, JSON_THROW_ON_ERROR);
check(!str_contains($encoded, 'person@example.test') && !str_contains($encoded, 'token_hash') && !str_contains($encoded, 'recipient_hash'), 'Aggregate JSON must omit private identity and tokens.');
$unverified = Report::project('controlled', $samples, [], [], [['id' => 70, 'verified_at' => 0]], [$request], [], [], [], [$mapping]);
check($unverified['totals']['verified_registrations'] === 0 && $unverified['totals']['completed_interviews'] === 0, 'Unverified account cannot count as completed authenticated funnel.');
$unmapped = Report::project('controlled', $samples, [], [], [['id' => 70, 'verified_at' => 4]], [$request], [['commerce_order_id' => 100, 'prospect_id' => 20]], [$payment]);
check($unmapped['totals']['completed_interviews'] === 0 && $unmapped['totals']['revenue_minor'] === 0, 'Same account purchase without exact sample request mapping is not attributed.');
$wrongCustomer = Report::project('controlled', $samples, [], [], [['id' => 70, 'verified_at' => 4]], [array_replace($request, ['customer_id' => 999])], [], [$payment], [], [$mapping]);
check($wrongCustomer['totals']['revenue_minor'] === 0, 'Cross-customer mapped request is isolated.');
$secondInvitation = array_replace($samples[0], ['id' => 2, 'experiment_arm' => 'tailored']);
$twoInvitations = Report::project('controlled', [$samples[0], $secondInvitation], [$message], [], [['id' => 70, 'verified_at' => 4]], [$request], [], [$payment], [], [$mapping], [['message_id' => 9, 'invitation_id' => 1, 'day' => 0, 'content_id' => 'beauty_hair-day0-v1']]);
check($twoInvitations['totals']['sends'] === 1 && $twoInvitations['totals']['paid_customers'] === 1, 'Repeated invitations cannot duplicate sends/customer revenue.');
$arms = array_column($twoInvitations['breakdowns']['experiment_arm'], 'totals', 'key');
check($arms['generic']['sends'] === 1 && $arms['tailored']['sends'] === 0 && $arms['unknown']['paid_customers'] === 1, 'Exact message invitation owns send; conflicting arm assignment owns no duplicated customer.');
check($twoInvitations['breakdowns']['message'][0]['key'] === 'beauty_hair-day0-v1', 'Frozen content identity must survive message breakdown.');
$sandboxReport = Report::project('controlled', $samples, [array_replace($message, ['provider' => 'acquisition_memory'])], [], [], [], [], []);
check($sandboxReport['totals']['sends'] === 0 && $sandboxReport['totals']['recorded_opens'] === 0 && $sandboxReport['totals']['recorded_clicks'] === 0 && $sandboxReport['breakdowns']['message'] === [], 'Memory capture cannot inflate campaign send/open/click grains.');
check($sandboxReport['controlled_sandbox']['messaging'] === ['sends' => 1, 'recorded_opens' => 1, 'recorded_clicks' => 1] && $sandboxReport['controlled_sandbox']['counts_toward_campaign'] === FALSE, 'Memory message outcomes remain visible separately as controlled.');
$stubReport = Report::project('controlled', $samples, [array_replace($message, ['provider' => 'stub'])], [], [], [], [], []);
check($stubReport['totals']['sends'] === 0 && $stubReport['controlled_sandbox']['messaging']['sends'] === 1, 'Stub provider is also controlled.');
print "PASS: 30 synthetic acquisition report checks; no real payment, provider or recipient used.\n";
