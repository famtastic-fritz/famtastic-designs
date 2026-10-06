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

  /** Freeze only the approved NEW D0 source; no old draft/recipe fallback. */
  public static function compileGeneric(array $authorization, string $token, string $unsubscribe, string $tracking, string $postal): array {
    AcquisitionSampleGuard::tokenHash($token);
    $postal = trim((string) preg_replace('/ +/', ' ', str_replace(["\r", "\n", "\t"], ' ', $postal)), ' ');
    if (!preg_match('/^[a-f0-9]{48}$/D', $unsubscribe) || !preg_match('/^[a-f0-9]{48}$/D', $tracking) || $postal === '' || strlen($postal) > 1000 || preg_match('/[<>\x00-\x1f\x7f]/', $postal)) throw new \InvalidArgumentException('generic_native_bindings_required');
    $recordBytes = (string) file_get_contents(AcquisitionSampleArtifacts::path($authorization['creative_approval']['reference']));
    $creative = AcquisitionSampleGuard::creativeApproval($authorization['creative_approval'], $recordBytes);
    $read = static function (string $relative) use ($creative): string {
      $path = AcquisitionSampleArtifacts::path($relative);
      if (!isset($creative['artifact_sha256'][$relative]) || filesize($path) > (str_ends_with($relative, 'famtastic-designs-logo-v1.png') ? 2097152 : 1048576) || !hash_equals((string) $creative['artifact_sha256'][$relative], (string) hash_file('sha256', $path))) throw new \InvalidArgumentException('generic_approved_artifact_changed');
      return (string) file_get_contents($path);
    };
    $base = 'marketing/campaigns/acquisition-199/generic-review/';
    $html = $read($base . 'beauty-email.html');
    $plain = $read($base . 'beauty-email.txt');
    if (!preg_match('/\ASubject: ([^\r\n]+)\r?\nPreview: ([^\r\n]+)\r?\n\r?\n/', $plain, $metadata)) throw new \InvalidArgumentException('generic_approved_plain_required');
    $plain = substr($plain, strlen($metadata[0]));
    $invitation = 'https://famtasticdesigns.com/web/api/pipeline/email/click/' . $tracking;
    $stop = 'https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/confirm/' . $unsubscribe;
    if (substr_count($html, 'href="beauty-lab.html"') !== 2 || substr_count($html, 'https://example.invalid/unsubscribe-not-bound') !== 1 || substr_count($plain, 'beauty-lab.html (local review only)') !== 1) throw new \InvalidArgumentException('generic_approved_binding_slots_required');
    // Only fixed review scaffolding and declared delivery slots change. The
    // approved primary CTA, branding, terms, signature and footer remain intact.
    $html = preg_replace('#<div role="note"[^>]*>Owner review candidate · this local CTA opens the actual sample\. Sending remains held; no invitation or unsubscribe binding is issued\.</div>#', '', $html, 1, $noteCount);
    if ($noteCount !== 1) throw new \InvalidArgumentException('generic_review_scaffold_changed');
    $html = str_replace(['href="beauty-lab.html"', 'https://example.invalid/unsubscribe-not-bound', ' (unbound review placeholder)'], ['href="' . $invitation . '"', $stop, ''], $html);
    $plain = str_replace(['beauty-lab.html (local review only)', 'https://example.invalid/unsubscribe-not-bound (unbound review placeholder)', "Owner review candidate. No sending binding issued.\n"], [$invitation, $stop, ''], $plain);
    $attachments = [];
    foreach (['connect-qr.png' => ['connect-qr', 'digital-card', 'image/png'], 'hair-studio.jpg' => ['sample-beauty_soft_power_acquisition', 'illustrative-generic-sample', 'image/jpeg']] as $name => [$cid, $purpose, $type]) {
      $bytes = $read($base . 'assets/' . $name);
      $attachments[] = ['cid' => $cid, 'sha256' => hash('sha256', $bytes), 'purpose' => $purpose, 'media_type' => $type, 'bytes_base64' => base64_encode($bytes)];
      $html = str_replace('src="assets/' . $name . '"', 'src="cid:' . $cid . '"', $html);
    }
    $logo = $read($base . 'assets/famtastic-designs-logo-v1.png');
    $html = str_replace('src="assets/famtastic-designs-logo-v1.png"', 'src="' . BrandedEmail::LOGO_URL . '"', $html);
    $html = str_replace('</body>', '<p style="font-size:12px">FAMtastic Designs<br>' . htmlspecialchars($postal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><img src="https://famtasticdesigns.com/web/api/pipeline/email/open/' . $tracking . '" width="1" height="1" alt=""></body>', $html);
    $plain = rtrim($plain) . "\n\nFAMtastic Designs\n" . $postal;
    if (preg_match('#(?:example\.invalid|beauty-lab\.html|(?:src|href)="assets/|unbound review placeholder|Owner review candidate)#', $html . $plain) || !str_contains($html, 'data-famtastic-email-brand=') || !str_contains($html, 'See what your website could look like') || !str_contains($html, 'Shay-Shay')) throw new \InvalidArgumentException('generic_bound_content_invariants_required');
    return ['schema' => 'famtastic.acquisition-generic-d0.v1', 'subject' => $metadata[1], 'preview' => $metadata[2], 'body' => $plain, 'html' => $html, 'content_id' => $authorization['content_id'], 'draft_hash' => hash('sha256', $read($base . 'beauty-email.html') . $read($base . 'beauty-email.txt')), 'source_artifacts' => $creative['artifact_sha256'], 'creative_approval_record' => $recordBytes, 'authorization' => $authorization, 'authorization_hash' => hash('sha256', json_encode($authorization, JSON_THROW_ON_ERROR)), 'attachments' => $attachments, 'sample_image_count' => 1, 'branding_asset' => ['url' => BrandedEmail::LOGO_URL, 'sha256' => hash('sha256', $logo)], 'postal_address' => $postal, 'unsubscribe_key' => $unsubscribe, 'tracking_key' => $tracking, 'invitation_token_hash' => AcquisitionSampleGuard::tokenHash($token), 'greeting_source' => 'neutral', 'component_studio_registration_proved' => FALSE];
  }

}
