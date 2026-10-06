<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/** Registry for the separately approved, immutable acquisition industry set. */
final class AcquisitionIndustryTemplate {

  public const APPROVAL_REFERENCE = 'docs/research/acquisition-199/TEMPLATE-APPROVAL-20261006.json';
  public const APPROVAL_SCHEMA = 'famtastic.acquisition-industry-creative-approval.v1';

  /** Exact workbook labels only; this is routing, not verified niche evidence. */
  private const SEGMENTS = [
    'mobile detailing, auto care & tinting' => 'mobile-detailing',
    'fitness, personal training & meal prep' => 'fitness-meal-prep',
    'photography, videography & media' => 'photography-media',
    'custom baking, catering & private chefs' => 'baking-catering',
    'home services, cleaning & maintenance' => 'home-services',
    'events, party rentals & entertainment' => 'events-rentals',
    'pet services, grooming & training' => 'pet-services',
    'consulting, tutoring & digital creators' => 'consulting-tutoring',
    'handcrafted products, fashion & boutiques' => 'handcrafted-boutiques',
  ];

  private const FILES = ['email.html', 'email.txt', 'email-images-blocked.html', 'lab.html', 'lab.css', 'lab.js'];
  private const MAX_FILE_BYTES = 2_097_152;

  public static function fromSegment(string $segment): ?string {
    $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $segment) ?? $segment));
    return self::SEGMENTS[$key] ?? NULL;
  }

  public static function bySlug(string $slug): array {
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new \InvalidArgumentException('industry_template_unknown');
    $approvalBytes = AcquisitionSampleArtifacts::industryPath(self::APPROVAL_REFERENCE);
    $approval = json_decode((string) file_get_contents($approvalBytes), TRUE, 64, JSON_THROW_ON_ERROR);
    if (($approval['schema'] ?? '') !== self::APPROVAL_SCHEMA || ($approval['owner'] ?? '') !== 'Fritz Medine' || ($approval['source'] ?? '') !== 'Direct human message in this chat' || ($approval['scope'] ?? '') !== 'All nine remaining industry email and Lab templates; existing approved beauty unchanged' || ($approval['physical_uncoached_owner_task'] ?? '') !== 'pending' || ($approval['new_industry_provider_sends_activated'] ?? NULL) !== FALSE || !preg_match('/^[a-f0-9]{40}$/D', (string) ($approval['creative_source_commit'] ?? ''))) {
      throw new \RuntimeException('industry_template_owner_approval_invalid');
    }
    $matches = array_values(array_filter($approval['templates'] ?? [], static fn(array $entry): bool => ($entry['industry'] ?? '') === $slug && ($entry['approved'] ?? FALSE) === TRUE));
    if (count($matches) !== 1 || array_keys($matches[0]['source_hashes'] ?? []) !== self::FILES) throw new \InvalidArgumentException('industry_template_not_approved');
    $row = $matches[0];
    $base = 'marketing/campaigns/acquisition-199/industry-previews/' . $slug . '/';
    $sourceHashes = [];
    $sourcePaths = [];
    foreach (self::FILES as $name) {
      $expected = (string) $row['source_hashes'][$name];
      if (!preg_match('/^[a-f0-9]{64}$/D', $expected)) throw new \RuntimeException('industry_template_hash_invalid');
      $relative = $base . $name;
      $path = AcquisitionSampleArtifacts::industryPath($relative, $expected);
      if (filesize($path) > self::MAX_FILE_BYTES) throw new \RuntimeException('industry_template_file_too_large');
      $sourceHashes[$name] = $expected;
      $sourcePaths[$name] = $relative;
    }
    $receiptPath = AcquisitionSampleArtifacts::industryPath($base . 'receipt.json');
    $receipt = json_decode((string) file_get_contents($receiptPath), TRUE, 64, JSON_THROW_ON_ERROR);
    $status = $receipt['status'] ?? '';
    $candidateReceipt = in_array($status, ['local_review_candidate', 'candidate'], TRUE) || (is_array($status) && ($status['session'] ?? '') === 'candidate');
    $ownerApproval = $receipt['owner_approval'] ?? $receipt['review_state']['owner_approval'] ?? NULL;
    if (!$candidateReceipt || $ownerApproval === TRUE || in_array($ownerApproval, ['approved', 'accepted'], TRUE)) throw new \RuntimeException('industry_template_receipt_invalid');
    $assetHashes = [];
    self::collectAssetHashes($receipt, $base, $slug, $assetHashes);
    if (!$assetHashes) throw new \RuntimeException('industry_template_original_artwork_required');
    $id = 'industry_' . str_replace('-', '_', $slug);
    return [
      'slug' => $slug,
      'id' => $id,
      'version' => 1,
      'niche' => 'generic',
      'title' => (string) ($receipt['display_name'] ?? $slug),
      'summary' => (string) ($receipt['selected_subcategory'] ?? 'Fictional illustrative example'),
      'industry_family' => $slug,
      'artifact_path' => $sourcePaths['lab.html'],
      'sha256' => $sourceHashes['lab.html'],
      'content_id' => self::contentId($slug),
      'approval_ref' => self::APPROVAL_REFERENCE,
      'approval_sha256' => hash_file('sha256', $approvalBytes),
      'source_commit' => $approval['creative_source_commit'],
      'source_hashes' => $sourceHashes,
      'source_paths' => $sourcePaths,
      'asset_hashes' => $assetHashes,
      'approval' => $approval,
      'approval_bytes' => (string) file_get_contents($approvalBytes),
      'receipt' => $receipt,
    ];
  }

  /** Exact native-preparation recipe consumed by the existing sample service. */
  public static function nativeRecipe(string $slug): array {
    $entry = self::bySlug($slug);
    $recipe = [
      'id' => $entry['id'],
      'version' => 1,
      'niche' => 'generic',
      'title' => $entry['title'],
      'summary' => $entry['summary'],
      'artifact_path' => $entry['artifact_path'],
      'sha256' => $entry['sha256'],
      'industry_family' => $slug,
      'content_id' => $entry['content_id'],
      'review' => [
        'status' => 'approved_campaign_artifact',
        'approval_record' => [
          'reference' => self::APPROVAL_REFERENCE,
          'sha256' => $entry['approval_sha256'],
          'template_slug' => $slug,
        ],
      ],
      'recipe_ref' => ['owner' => 'component-studio', 'id' => $entry['id'], 'version' => 1, 'status' => 'import_request_pending'],
    ];
    return $recipe;
  }

  /** Freeze Lab bytes as a self-contained document for the invitation row. */
  public static function freezeLab(string $slug, string $token): array {
    $entry = self::bySlug($slug);
    AcquisitionSampleGuard::tokenHash($token);
    $read = static function (string $name) use ($entry): string {
      $path = AcquisitionSampleArtifacts::industryPath($entry['source_paths'][$name], $entry['source_hashes'][$name]);
      return (string) file_get_contents($path);
    };
    $html = $read('lab.html');
    $css = $read('lab.css');
    $js = $read('lab.js');
    $jsCode = preg_replace('~//[^\r\n]*|/\*.*?\*/~s', '', $js) ?? $js;
    if (preg_match('/\bfetch\s*\(|\bXMLHttpRequest\b|\bsendBeacon\s*\(|\bWebSocket\b|\b(?:window\.)?(?:localStorage|sessionStorage|indexedDB)\b|\bdocument\.cookie\b/i', $jsCode)) throw new \RuntimeException('industry_lab_network_or_storage_capability_forbidden');
    if (preg_match('/<script\b[^>]*\bon[a-z]+\s*=/i', $html) || stripos($html, 'javascript:') !== FALSE || stripos($js, '</script') !== FALSE || stripos($css, '</style') !== FALSE) throw new \RuntimeException('industry_lab_active_markup_invalid');
    $html = preg_replace('#<link\s+rel="stylesheet"\s+href="lab\.css"\s*/?>#i', '__FAMTASTIC_INDUSTRY_CSS_SLOT__', $html, 1, $cssCount);
    $html = preg_replace('#<script\s+src="lab\.js"(?:\s+[^>]*)?>\s*</script>#i', '__FAMTASTIC_INDUSTRY_JS_SLOT__', $html, 1, $jsCount);
    if ($cssCount !== 1 || $jsCount !== 1) throw new \RuntimeException('industry_lab_bundle_slots_invalid');
    $html = str_replace('__FAMTASTIC_INDUSTRY_CSS_SLOT__', '<style data-famtastic-industry-css-sha256="' . $entry['source_hashes']['lab.css'] . '">' . $css . '</style>', $html);
    $html = str_replace('__FAMTASTIC_INDUSTRY_JS_SLOT__', '<script data-famtastic-industry-js-sha256="' . $entry['source_hashes']['lab.js'] . '">' . $js . '</script>', $html);
    $byBasename = [];
    foreach ($entry['asset_hashes'] as $path => $hash) $byBasename[basename($path)] = [$path, $hash];
    $html = preg_replace_callback('/(?:src|poster)="([^"#]+)"/i', static function (array $match) use ($byBasename): string {
      $url = $match[1];
      if (preg_match('#^(?:https?:|data:|//)#i', $url)) throw new \RuntimeException('industry_lab_remote_media_forbidden');
      $basename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
      if (!isset($byBasename[$basename])) throw new \RuntimeException('industry_lab_unapproved_media_reference');
      [$path, $hash] = $byBasename[$basename];
      if ($basename === 'famtastic-designs-logo-v1.png') return 'src="' . BrandedEmail::LOGO_URL . '"';
      $bytes = (string) file_get_contents(AcquisitionSampleArtifacts::industryPath($path, $hash));
      $type = str_ends_with(strtolower($basename), '.svg') ? 'image/svg+xml' : (str_ends_with(strtolower($basename), '.png') ? 'image/png' : 'image/jpeg');
      return str_replace($url, 'data:' . $type . ';base64,' . base64_encode($bytes), $match[0]);
    }, $html);
    $css = preg_replace_callback('/url\(["\']?([^"\')]+)["\']?\)/i', static function (array $match) use ($byBasename): string {
      $url = trim($match[1]);
      if (preg_match('#^(?:https?:|data:|//)#i', $url)) throw new \RuntimeException('industry_lab_remote_media_forbidden');
      $basename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
      if (!isset($byBasename[$basename])) throw new \RuntimeException('industry_lab_unapproved_media_reference');
      [$path, $hash] = $byBasename[$basename];
      $bytes = (string) file_get_contents(AcquisitionSampleArtifacts::industryPath($path, $hash));
      $type = str_ends_with(strtolower($basename), '.woff2') ? 'font/woff2' : 'image/svg+xml';
      return 'url("data:' . $type . ';base64,' . base64_encode($bytes) . '")';
    }, $css);
    if (preg_match('/\b(?:https?:)?\/\//i', $css) && preg_match('/url\(["\']?(?:https?:)?\/\//i', $css)) throw new \RuntimeException('industry_lab_external_css_asset_forbidden');
    $html = preg_replace_callback('/<style\b([^>]*)>(.*?)<\/style>/is', static fn(array $m): string => '<style' . $m[1] . '>' . $css . '</style>', $html, 1, $styleCount);
    if ($styleCount !== 1) throw new \RuntimeException('industry_lab_inline_css_missing');
    $returnUrl = '/samples/' . rawurlencode($token);
    $html = str_replace('href="email.html"', 'href="' . htmlspecialchars($returnUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $html);
    $sandboxCsp = "default-src 'none'; img-src data: https://famtasticdesigns.com; style-src 'unsafe-inline' data:; script-src 'unsafe-inline'; font-src data:; connect-src 'none'; form-action 'none'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'";
    $html = preg_replace('#<meta\s+http-equiv="Content-Security-Policy"\s+content="[^"]*"\s*/?>#i', '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($sandboxCsp, ENT_QUOTES, 'UTF-8') . '">', $html, 1, $cspCount);
    if (!str_contains($html, 'data-famtastic-industry-lab=')) {
      $html = preg_replace('/<body\b/i', '<body data-famtastic-industry-lab="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"', $html, 1, $bodyCount);
      if ($bodyCount !== 1) throw new \RuntimeException('industry_lab_marker_required');
    }
    elseif (!str_contains($html, 'data-famtastic-industry-lab="' . $slug . '"')) throw new \RuntimeException('industry_lab_marker_mismatch');
    return [
      'html' => $html,
      'sha256' => hash('sha256', $html),
      'css_sha256' => $entry['source_hashes']['lab.css'],
      'js_sha256' => $entry['source_hashes']['lab.js'],
      'lab_source_sha256' => $entry['source_hashes']['lab.html'],
      'asset_hashes' => $entry['asset_hashes'],
      'source_commit' => $entry['source_commit'],
      'content_id' => $entry['content_id'],
      'approval_ref' => $entry['approval_ref'],
      'approval_sha256' => $entry['approval_sha256'],
      'interactive' => TRUE,
    ];
  }

  public static function isNativeRecipe(array $recipe): bool {
    $slug = (string) ($recipe['industry_family'] ?? '');
    if (!in_array($slug, array_values(self::SEGMENTS), TRUE)) return FALSE;
    try {
      return $recipe === self::nativeRecipe($slug);
    }
    catch (\Throwable) {
      return FALSE;
    }
  }

  public static function contentId(string $slug): string {
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new \InvalidArgumentException('industry_template_unknown');
    return 'acquisition-199:industry-' . str_replace('-', '_', $slug) . '_d0:v1';
  }

  private static function allowedAssetPath(string $path, string $slug): bool {
    return str_starts_with($path, 'marketing/campaigns/acquisition-199/industry-previews/' . $slug . '/')
      || in_array($path, [
        'marketing/campaigns/acquisition-199/industry-previews/assets/famtastic-designs-logo-v1.png',
        'marketing/campaigns/acquisition-199/industry-previews/assets/connect-qr.png',
        'marketing/campaigns/acquisition-199/industry-previews/generic-review/assets/famtastic-designs-logo-v1.png',
        'marketing/campaigns/acquisition-199/industry-previews/generic-review/assets/connect-qr.png',
        'marketing/campaigns/acquisition-199/industry-previews/generic-review/assets/metropolis-regular.woff2',
        'marketing/campaigns/acquisition-199/industry-previews/generic-review/assets/metropolis-bold.woff2',
        'marketing/campaigns/acquisition-199/industry-previews/generic-review/assets/lora-bold.woff2',
        'marketing/campaigns/acquisition-199/industry-previews/generic-review/assets/lora-italic.woff2',
      ], TRUE)
      || in_array($path, [
        'marketing/campaigns/acquisition-199/assets/famtastic-designs-logo-v1.png',
        'marketing/campaigns/acquisition-199/assets/connect-qr.png',
        'marketing/campaigns/acquisition-199/generic-review/assets/famtastic-designs-logo-v1.png',
        'marketing/campaigns/acquisition-199/generic-review/assets/connect-qr.png',
        'marketing/campaigns/acquisition-199/generic-review/assets/metropolis-regular.woff2',
        'marketing/campaigns/acquisition-199/generic-review/assets/metropolis-bold.woff2',
        'marketing/campaigns/acquisition-199/generic-review/assets/lora-bold.woff2',
        'marketing/campaigns/acquisition-199/generic-review/assets/lora-italic.woff2',
      ], TRUE);
  }

  /** Collect only receipt-hashed image/font files; the copy/layout source hashes stay authoritative. */
  private static function collectAssetHashes(array $value, string $base, string $slug, array &$output): void {
    if (isset($value['path']) && is_string($value['path'])) {
      $path = $value['path'];
      $hash = (string) ($value['sha256'] ?? '');
      $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
      if ($hash !== '' && preg_match('/^[a-f0-9]{64}$/D', $hash) && in_array($extension, ['svg', 'png', 'jpg', 'jpeg', 'woff2'], TRUE)) {
        if (!str_starts_with($path, 'marketing/')) $path = self::normalizeAssetPath($path, $base);
        if (!self::allowedAssetPath($path, $slug)) throw new \RuntimeException('industry_template_asset_path_invalid:' . $path);
        AcquisitionSampleArtifacts::industryPath($path, $hash);
        $output[$path] = $hash;
      }
    }
    foreach ($value as $key => $child) {
      if (is_string($key) && preg_match('#(?:^|/)([^/]+\.(?:svg|png|jpe?g|woff2))$#i', $key)) {
        $hash = is_array($child) ? (string) ($child['sha256'] ?? '') : (is_string($child) ? $child : '');
        if (preg_match('/^[a-f0-9]{64}$/D', $hash)) {
          $path = str_starts_with($key, 'marketing/') ? $key : self::normalizeAssetPath($key, $base);
          if (!self::allowedAssetPath($path, $slug)) throw new \RuntimeException('industry_template_asset_path_invalid:' . $path);
          AcquisitionSampleArtifacts::industryPath($path, $hash);
          $output[$path] = $hash;
        }
      }
      if (is_array($child)) self::collectAssetHashes($child, $base, $slug, $output);
    }
  }

  private static function normalizeAssetPath(string $path, string $base): string {
    if (str_starts_with($path, 'industry-previews/')) return 'marketing/campaigns/acquisition-199/' . $path;
    if (str_starts_with($path, 'generic-review/assets/')) {
      if (in_array(basename($path), ['famtastic-designs-logo-v1.png', 'connect-qr.png'], TRUE)) return 'marketing/campaigns/acquisition-199/industry-previews/assets/' . basename($path);
      return 'marketing/campaigns/acquisition-199/industry-previews/' . $path;
    }
    if (str_starts_with($path, 'assets/')) return $base . $path;
    return $base . basename($path);
  }
}
