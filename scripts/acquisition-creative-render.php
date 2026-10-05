<?php
declare(strict_types=1);
// CLI-only pure rendering. This is a draft compiler, never a sending adapter.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/backend/web/modules/custom/famtastic_pipeline/src/Service/BrandedEmail.php';
$out = $root . '/marketing/campaigns/acquisition-199';
$drafts = json_decode(file_get_contents($out . '/messages.json'), true, 512, JSON_THROW_ON_ERROR);
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$localLogo = '../assets/famtastic-designs-logo-v1.png';
$localQr = '../assets/connect-qr.png';
foreach ($drafts['messages'] as $m) {
  $fixtures = ['beauty_hair' => 'Juniper Hair Studio', 'mobile_detailing' => 'Juniper Mobile Detail', 'baking_catering' => 'Juniper Bakes & Table'];
  $business = $fixtures[$m['niche']];
  $paragraphs = '<p>Hello, ' . $esc($business) . ',</p><p><strong>Two illustrative directions for ' . $esc($business) . '.</strong></p>';
  foreach ($m['paragraphs'] as $p) { $paragraphs .= '<p style="margin:0 0 18px;">' . $esc($p) . '</p>'; }
  foreach (($m['sample_previews'] ?? []) as $preview) {
    if (is_file($out . '/' . $preview['path'])) {
      $paragraphs .= '<p style="margin:18px 0;font-size:13px;"><a href="https://example.invalid/invitation-not-bound" style="color:#284500;"><img src="../' . $esc($preview['path']) . '" width="500" alt="' . $esc($preview['alt']) . '" style="display:block;width:100%;max-width:500px;height:auto;border:1px solid #d9ded4;"></a><br>' . $esc($preview['alt']) . '</p>';
    }
  }
  $paragraphs .= '<p style="margin:24px 0 18px;font-size:14px;">' . $esc($m['journey']) . '</p>';
  $paragraphs .= '<p style="margin:18px 0;font-size:13px;line-height:1.6;"><strong>Starter scope and renewal.</strong> ' . $esc($m['terms']) . '</p>';
  $paragraphs .= '<p style="margin:24px 0 10px;"><strong>Shay-Shay</strong><br>FAMtastic Designs assistant</p>';
  $paragraphs .= '<p style="font-size:13px;line-height:1.7;">Get to know us: <a href="https://famtasticdesigns.com/connect" style="color:#284500;text-decoration:underline;display:inline-block;min-height:44px;padding:10px 0;">Digital card</a> · <a href="https://famtasticdesigns.com/connect/commercial.mp4" style="color:#284500;text-decoration:underline;display:inline-block;min-height:44px;padding:10px 0;">Short commercial</a></p>';
  $paragraphs .= '<p style="font-size:12px;line-height:1.6;"><img src="' . $localQr . '" width="160" height="160" alt="Scan for the FAMtastic Designs digital card" style="display:block;width:160px;height:160px;background:#fff;margin:12px 0;">If images are blocked, use the Digital card link above.</p>';
  $paragraphs .= '<p style="font-size:12px;line-height:1.6;">If this is not useful, reply and we will stop. <a href="https://example.invalid/unsubscribe-not-bound" style="color:#284500;text-decoration:underline;display:inline-block;min-height:44px;padding:10px 0;">Unsubscribe</a></p>';
  $html = \Drupal\famtastic_pipeline\Service\BrandedEmail::render($m['subject'], $paragraphs, \Drupal\famtastic_pipeline\Service\BrandedEmail::LOGO_URL, $m['headline'], 'Illustrative samples · draft review', 'https://example.invalid/invitation-not-bound', $m['cta_label'], $m['preview']);
  // A review-only overlay and shared exact owner bytes ensure offline legibility.
  // Sending must use the separately reviewed acquisition message kind with real
  // opaque invitation + unsubscribe bindings and a approved hosted QR asset.
  $html = str_replace(\Drupal\famtastic_pipeline\Service\BrandedEmail::LOGO_URL, $localLogo, $html);
  $html = str_replace('<body data-famtastic-email-brand=', '<body data-review-only="true" data-famtastic-email-brand=', $html);
  $banner = '<div role="note" style="padding:16px;background:#fff4c2;color:#302400;font:14px/1.5 Arial;">DRAFT v1 · fictional business fixture — unsendable review artifact. Invitation and unsubscribe links are intentionally unbound. Exact content, recipient, cohort and provider approval remain required.</div>';
  $html = preg_replace('/(<body[^>]*>)/', '$1' . $banner, $html, 1);
  file_put_contents($out . '/emails/' . $m['id'] . '.html', $html);
  $blocked = preg_replace('/<img\b[^>]*>/', '<span style="display:inline-block;padding:8px;font:13px Arial;">[Image blocked in this review]</span>', $html);
  file_put_contents($out . '/emails/' . $m['id'] . '-images-blocked.html', $blocked);
}
echo "Rendered nine drafts with existing BrandedEmail; no mailer loaded.\n";
