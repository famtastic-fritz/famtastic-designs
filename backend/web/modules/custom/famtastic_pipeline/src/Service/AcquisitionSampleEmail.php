<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Pure content compiler over the existing approved BrandedEmail shell. */
final class AcquisitionSampleEmail {

  public static function compile(array $draft, array $bindings, string $token, string $unsubscribe, string $qrHash, string $postal = '', string $tracking = ''): array {
    $bindings = AcquisitionSampleGuard::bindings($bindings);
    AcquisitionSampleGuard::tokenHash($token);
    $day = (int) ($draft['day'] ?? -1);
    $niche = (string) ($draft['niche'] ?? '');
    $id = $niche . '_d' . $day;
    $version = (int) ($draft['version'] ?? 0);
    $images = (array) ($draft['sample_previews'] ?? []);
    if (preg_match('/^[a-f0-9]{48}$/D', $unsubscribe) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $qrHash) !== 1 || !in_array($day, AcquisitionSampleSequenceService::DAYS, TRUE) || !in_array($niche, AcquisitionSampleGuard::NICHES, TRUE) || ($draft['id'] ?? '') !== $id || ($draft['campaign_id'] ?? '') !== 'acquisition-199' || $version < 1 || ($draft['content_id'] ?? '') !== 'acquisition-199:' . $id . ':v' . $version || ($draft['signature'] ?? '') !== 'Shay-Shay' || empty($draft['preview']) || empty($draft['subject']) || !is_array($draft['paragraphs'] ?? NULL) || !$draft['paragraphs'] || (int) ($draft['sample_image_count'] ?? -1) !== count($images) || count($images) !== ($day === 0 ? 2 : 0)) throw new \InvalidArgumentException('sample_email_draft_invalid');
    $canonical = json_decode((string) file_get_contents(AcquisitionSampleArtifacts::path('marketing/campaigns/acquisition-199/messages.json')), TRUE, 32, JSON_THROW_ON_ERROR);
    $candidate = $draft; unset($candidate['qr_sha256']);
    $matches = array_values(array_filter($canonical['messages'] ?? [], static fn(array $row): bool => ($row['id'] ?? '') === $id));
    if (count($matches) !== 1 || $matches[0] !== $candidate) throw new \InvalidArgumentException('canonical_sample_draft_required');
    $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $invitation = 'https://famtasticdesigns.com/samples/' . $token;
    if ($tracking !== '') {
      if (preg_match('/^[a-f0-9]{48}$/D', $tracking) !== 1) throw new \InvalidArgumentException('sample_tracking_invalid');
      $invitation = 'https://famtasticdesigns.com/web/api/pipeline/email/click/' . $tracking;
    }
    $stop = 'https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/confirm/' . $unsubscribe;
    $greeting = $bindings['recipient_name'] ?: $bindings['business_name'];
    $paragraphs = '<p>Hello, ' . $esc($greeting) . ',</p>';
    $root = dirname(__DIR__, 7);
    $qr = AcquisitionSampleArtifacts::path('marketing/campaigns/acquisition-199/assets/connect-qr.png');
    if (!is_file($qr) || !hash_equals($qrHash, (string) hash_file('sha256', $qr))) throw new \InvalidArgumentException('sample_email_qr_integrity_required');
    $attachments = [['cid' => 'connect-qr', 'sha256' => $qrHash, 'purpose' => 'digital-card', 'media_type' => 'image/png', 'bytes_base64' => base64_encode((string) file_get_contents($qr))]];
    $observation = $bindings['research_observation'] ?? NULL;
    if ($observation) $paragraphs .= '<p>' . $esc((string) $observation['text']) . '</p>';
    foreach ($draft['paragraphs'] as $paragraph) $paragraphs .= '<p>' . $esc((string) $paragraph) . '</p>';
    $recipeIds = match ($niche) { 'beauty_hair' => ['beauty_editorial', 'beauty_service_first'], 'mobile_detailing' => ['detailing_precision', 'detailing_route_ready'], 'baking_catering' => ['baking_signature', 'catering_table_story'] };
    if ($day === 0 && array_values(array_unique(array_column($images, 'recipe_id'))) !== $recipeIds) throw new \InvalidArgumentException('two_distinct_niche_previews_required');
    foreach ($images as $image) {
      $recipe = (string) ($image['recipe_id'] ?? '');
      $relative = 'assets/' . $recipe . '-preview.jpg';
      if (!in_array($recipe, $recipeIds, TRUE) || ($image['path'] ?? '') !== $relative || empty($image['rights']) || empty($image['alt']) || ($image['link_binding'] ?? '') !== 'invitation_url') throw new \InvalidArgumentException('sample_email_shared_media_required');
      $path = AcquisitionSampleArtifacts::path('marketing/campaigns/acquisition-199/' . $relative);
      if (!is_file($path) || filesize($path) > 1048576) throw new \InvalidArgumentException('sample_email_shared_media_unavailable');
      $cid = 'sample-' . $recipe;
      $attachments[] = ['cid' => $cid, 'sha256' => hash_file('sha256', $path), 'source_path' => 'marketing/campaigns/acquisition-199/' . $relative, 'purpose' => 'illustrative-generic-sample', 'recipe_id' => $recipe, 'rights' => $image['rights'], 'media_type' => 'image/jpeg', 'bytes_base64' => base64_encode((string) file_get_contents($path))];
      $paragraphs .= '<p><a href="' . $esc($invitation) . '"><img src="cid:' . $cid . '" width="560" style="display:block;width:100%;height:auto" alt="' . $esc((string) $image['alt']) . '"></a></p>';
    }
    $paragraphs .= '<p>' . $esc((string) ($draft['journey'] ?? '')) . '</p><p style="font-size:13px">' . $esc((string) ($draft['terms'] ?? '')) . '</p>';
    $postal = trim(strip_tags($postal));
    if (strlen($postal) > 1000) throw new \InvalidArgumentException('sample_email_postal_invalid');
    $paragraphs .= '<p><strong>Shay-Shay</strong><br>FAMtastic Designs assistant</p><p><a href="https://famtasticdesigns.com/connect">Digital card</a> · <a href="https://famtasticdesigns.com/connect/commercial.mp4">Short commercial</a></p><p><img src="cid:connect-qr" width="160" height="160" alt="Scan for the FAMtastic Designs digital card">If images are blocked, use the Digital card link above.</p><p><a href="' . $esc($stop) . '">Unsubscribe</a></p><p style="font-size:12px">FAMtastic Designs<br>' . nl2br($esc($postal ?: 'Physical sender address awaiting review; dispatch held.')) . '</p>';
    $html = BrandedEmail::render((string) $draft['subject'], $paragraphs, BrandedEmail::LOGO_URL, (string) ($draft['headline'] ?? ''), 'Illustrative samples', $invitation, (string) ($draft['cta_label'] ?? 'See your two directions'), (string) $draft['preview']);
    if ($tracking !== '') $html .= '<img src="https://famtasticdesigns.com/web/api/pipeline/email/open/' . $tracking . '" width="1" height="1" alt="">';
    $plain = "Hello, {$greeting},\n\n" . ($observation ? $observation['text'] . "\n\n" : '') . implode("\n\n", $draft['paragraphs']) . "\n\n" . ($draft['journey'] ?? '') . "\n\n" . ($draft['terms'] ?? '') . "\n\n" . ($draft['cta_label'] ?? 'See your two directions') . ":\n{$invitation}\n\nShay-Shay\nFAMtastic Designs assistant\n\nDigital card: https://famtasticdesigns.com/connect\nShort commercial: https://famtasticdesigns.com/connect/commercial.mp4\nUnsubscribe: {$stop}";
    $plain .= "\n\nFAMtastic Designs\n" . ($postal ?: 'Physical sender address awaiting review; dispatch held.');
    return ['subject' => (string) $draft['subject'], 'body' => $plain, 'html' => $html, 'preview' => (string) $draft['preview'], 'content_id' => (string) $draft['content_id'], 'draft_hash' => hash('sha256', json_encode($draft, JSON_THROW_ON_ERROR)), 'attachments' => $attachments, 'sample_image_count' => count($images), 'postal_address' => $postal, 'unsubscribe_key' => $unsubscribe, 'invitation_token_hash' => AcquisitionSampleGuard::tokenHash($token), 'tracking_key' => $tracking, 'research_observation' => $observation, 'greeting_source' => $bindings['recipient_name'] === '' ? 'verified_business_name' : 'verified_recipient_name'];
  }

}
