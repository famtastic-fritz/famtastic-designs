<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/** Reusable HVAC Lab registry. Implementation approval grants no email authority. */
final class AcquisitionHvacTemplate {

  public const ID = 'coastal_current_hvac_v1';
  public const FAMILY = 'hvac';
  public const SLUG = 'hvac-coastal-current';
  public const BASE = 'marketing/campaigns/acquisition-199/industry-previews/' . self::SLUG;
  public const MANIFEST_SCHEMA = 'famtastic.hvac-lab-manifest.v1';
  public const CONTENT_ID = 'acquisition-199:coastal_current_hvac_lab:v1';
  private const SOURCES = ['lab.html', 'lab.css', 'lab.js'];

  /** Only exact supplied categories; broad home services retains its old recipe. */
  public static function fromSegment(string $segment): ?string {
    $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $segment) ?? $segment));
    return in_array($key, ['hvac', 'ac/hvac', 'air conditioning', 'heating & air conditioning'], TRUE) ? self::FAMILY : NULL;
  }

  public static function bySlug(string $slug = self::SLUG): array {
    if ($slug !== self::SLUG) throw new \InvalidArgumentException('hvac_template_unknown');
    [$root, $bytes] = self::bundle();
    $manifest = json_decode($bytes, TRUE, 32, JSON_THROW_ON_ERROR);
    if (($manifest['schema'] ?? '') !== self::MANIFEST_SCHEMA || ($manifest['id'] ?? '') !== self::ID || ($manifest['family'] ?? '') !== self::FAMILY || ($manifest['approval']['status'] ?? '') !== 'owner_authorized_implementation' || ($manifest['approval']['email_send_authorized'] ?? NULL) !== FALSE || !preg_match('/^3dfc722(?:[a-f0-9]{33})?$/D', (string) ($manifest['source_design_commit'] ?? '')) || array_keys($manifest['source_hashes'] ?? []) !== self::SOURCES) throw new \RuntimeException('hvac_template_manifest_invalid');
    if (empty($manifest['asset_hashes']) || !is_array($manifest['asset_hashes'])) throw new \RuntimeException('hvac_template_assets_required');
    foreach ($manifest['source_hashes'] + $manifest['asset_hashes'] as $name => $hash) {
      if (!is_string($name) || (!in_array($name, self::SOURCES, TRUE) && !preg_match('/^[a-z0-9][a-z0-9_.-]*\.(?:webp|png|woff2)$/D', $name)) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new \RuntimeException('hvac_template_file_invalid');
      self::verifiedPath($root, $name, $hash);
    }
    return $manifest + ['slug' => self::SLUG, 'root' => $root, 'manifest_sha256' => hash('sha256', $bytes)];
  }

  /** A separate provenance boundary, deliberately distinct from approved email creative. */
  public static function nativeRecipe(): array {
    $entry = self::bySlug();
    return [
      'id' => self::ID, 'version' => 1, 'niche' => 'generic',
      'title' => 'Coastal Current HVAC Lab',
      'summary' => 'Illustrative HVAC service and inquiry workflow; company facts require confirmation.',
      'industry_family' => self::FAMILY, 'content_id' => self::CONTENT_ID,
      'artifact_path' => self::BASE . '/lab.html', 'sha256' => $entry['source_hashes']['lab.html'],
      'review' => ['status' => 'owner_authorized_lab_artifact', 'approval_record' => ['reference' => self::BASE . '/manifest.json', 'sha256' => $entry['manifest_sha256'], 'email_send_authorized' => FALSE]],
      'recipe_ref' => ['owner' => 'component-studio', 'id' => self::ID, 'version' => 1, 'status' => 'import_request_pending'],
    ];
  }

  public static function isNativeRecipe(array $recipe): bool {
    try { return $recipe === self::nativeRecipe(); }
    catch (\Throwable) { return FALSE; }
  }

  /** Hash-checked public allowlist; manifest and other source records stay private. */
  public static function assetPath(string $name): string {
    $entry = self::bySlug();
    $hash = $entry['source_hashes'][$name] ?? $entry['asset_hashes'][$name] ?? NULL;
    if (!is_string($hash)) throw new \InvalidArgumentException('hvac_asset_not_allowlisted');
    return self::verifiedPath($entry['root'], $name, $hash);
  }

  /** Freeze fonts, images, CSS and ephemeral workflow JS into one immutable snapshot. */
  public static function freezeLab(string $token): array {
    AcquisitionSampleGuard::tokenHash($token);
    $entry = self::bySlug();
    $read = static fn(string $name): string => (string) file_get_contents(self::verifiedPath($entry['root'], $name, $entry['source_hashes'][$name]));
    $html = $read('lab.html');
    $css = $read('lab.css');
    $js = $read('lab.js');
    self::validateSource($html, $css, $js);
    $embed = static function (string $name) use ($entry): string {
      if (!isset($entry['asset_hashes'][$name])) throw new \RuntimeException('hvac_unapproved_asset_reference');
      $types = ['webp' => 'image/webp', 'png' => 'image/png', 'woff2' => 'font/woff2'];
      $path = self::verifiedPath($entry['root'], $name, $entry['asset_hashes'][$name]);
      return 'data:' . $types[pathinfo($name, PATHINFO_EXTENSION)] . ';base64,' . base64_encode((string) file_get_contents($path));
    };
    $css = preg_replace_callback('/url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', static fn(array $m): string => 'url("' . $embed($m[1]) . '")', $css);
    $html = preg_replace('#<link\s+rel="stylesheet"\s+href="lab\.css"\s*/?>#i', '__HVAC_CSS_SLOT__', $html, 1, $cssCount);
    $html = preg_replace('#<script\s+src="lab\.js"(?:\s+[^>]*)?>\s*</script>#i', '__HVAC_JS_SLOT__', $html, 1, $jsCount);
    if ($cssCount !== 1 || $jsCount !== 1) throw new \RuntimeException('hvac_bundle_slots_invalid');
    $html = preg_replace_callback('/(?:src|poster)="([^"#]+)"/i', static fn(array $m): string => str_replace($m[1], $embed($m[1]), $m[0]), $html);
    $html = str_replace(['__HVAC_CSS_SLOT__', '__HVAC_JS_SLOT__'], ['<style data-famtastic-hvac-css-sha256="' . $entry['source_hashes']['lab.css'] . '">' . $css . '</style>', '<script data-famtastic-hvac-js-sha256="' . $entry['source_hashes']['lab.js'] . '">' . $js . '</script>'], $html);
    $html = str_replace('__HVAC_CONTINUATION__', '/login?mode=register&amp;sample_continuation=' . $token, $html);
    $csp = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; font-src data:; connect-src 'none'; form-action 'none'; base-uri 'none'; object-src 'none'";
    $html = preg_replace('#<meta\s+http-equiv="Content-Security-Policy"\s+content="[^"]*"\s*/?>#i', '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($csp, ENT_QUOTES, 'UTF-8') . '">', $html, 1, $cspCount);
    if ($cspCount !== 1 || !str_contains($html, 'data-famtastic-industry-lab="' . self::SLUG . '"')) throw new \RuntimeException('hvac_snapshot_markers_required');
    if (strlen($html) > 33_554_432) throw new \RuntimeException('hvac_snapshot_too_large');
    return [
      'html' => $html, 'sha256' => hash('sha256', $html), 'interactive' => TRUE,
      'css_sha256' => $entry['source_hashes']['lab.css'], 'js_sha256' => $entry['source_hashes']['lab.js'],
      'lab_source_sha256' => $entry['source_hashes']['lab.html'], 'asset_hashes' => $entry['asset_hashes'],
      'source_design_commit' => $entry['source_design_commit'], 'content_id' => self::CONTENT_ID,
      'approval_ref' => self::BASE . '/manifest.json', 'approval_sha256' => $entry['manifest_sha256'],
      'email_send_authorized' => FALSE,
    ];
  }

  /** Reject active HTML, external assets, persistent state and network-capable scripts. */
  public static function validateSource(string $html, string $css, string $js): void {
    if (strlen($html) > 262144 || strlen($css) > 262144 || strlen($js) > 131072) throw new \RuntimeException('hvac_source_too_large');
    if (preg_match('/\b(?:fetch|XMLHttpRequest|sendBeacon|WebSocket|EventSource|Worker|SharedWorker|localStorage|sessionStorage|indexedDB|caches|eval|importScripts)\b|\bnew\s+Function\b|\bdocument\s*\.\s*cookie\b|\blocation\s*\.\s*(?:href|assign|replace)\b|\bwindow\s*\.\s*open\b|\bimport\s*(?:\(|["\'])/i', $js) || preg_match('/\bFunction\s*\(/', $js)) throw new \RuntimeException('hvac_network_or_storage_capability_forbidden');
    if (stripos($js, '</script') !== FALSE || stripos($css, '</style') !== FALSE || preg_match('/@import\b/i', $css)) throw new \RuntimeException('hvac_active_source_invalid');
    if (preg_match('/<[^>]*\son[a-z]+\s*=|<\s*(?:iframe|object|embed|base)\b|(?:javascript|vbscript)\s*:|\bsrcset\s*=|http-equiv\s*=\s*["\']?refresh/i', $html)) throw new \RuntimeException('hvac_active_markup_invalid');
    if (preg_match('/<[^>]*\{\{business_name\}\}|<(?:script|style)\b[^>]*>[^<]*\{\{business_name\}\}/is', $html) || str_contains($js . $css, '{{')) throw new \RuntimeException('hvac_text_binding_required');
    if (preg_match('/\{\{(?!business_name\}\})[^}]+\}\}/', $html)) throw new \RuntimeException('hvac_unknown_placeholder');
    // Exactly one local script. Inline/external additions cannot enter the snapshot.
    if (preg_match_all('/<script\b/i', $html) !== 1 || !preg_match('#<script\s+src="lab\.js"(?:\s+[^>]*)?>\s*</script>#i', $html)) throw new \RuntimeException('hvac_script_slot_required');
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $document = new \DOMDocument();
      $document->loadHTML($html, LIBXML_NONET);
      foreach ($document->getElementsByTagName('*') as $element) {
        foreach ($element->attributes as $attribute) {
          $name = strtolower($attribute->name);
          $value = $attribute->value;
          if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'srcset', 'action', 'formaction', 'ping'], TRUE)) throw new \RuntimeException('hvac_active_markup_invalid');
          if ($name === 'src' && !preg_match('/^(?:lab\.js|[a-z0-9][a-z0-9_.-]*\.(?:webp|png))$/D', $value)) throw new \RuntimeException('hvac_unapproved_asset_reference');
          if ($name === 'href' && $value !== 'lab.css' && $value !== '__HVAC_CONTINUATION__' && !preg_match('/^#[a-z0-9_-]*$/D', $value) && $value !== 'https://famtasticdesigns.com/?utm_source=hvac_lab&utm_medium=creator_credit&utm_campaign=created_by_famtastic') throw new \RuntimeException('hvac_navigation_not_allowlisted');
        }
      }
    }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
  }

  private static function verifiedPath(string $root, string $name, string $hash): string {
    $path = realpath($root . '/' . $name);
    if (!$path || !str_starts_with($path, $root . '/') || is_link($root . '/' . $name) || !is_file($path) || filesize($path) > 8_388_608 || !hash_equals($hash, (string) hash_file('sha256', $path))) throw new \RuntimeException('hvac_artifact_hash_mismatch');
    return $path;
  }

  /** Optional private immutable release bundle never rewrites older industry settings. */
  private static function bundle(): array {
    $bundle = class_exists(Settings::class) ? (string) Settings::get('famtastic_acquisition_hvac_bundle_root', '') : '';
    $root = realpath($bundle === '' ? dirname(__DIR__, 7) . '/' . self::BASE : $bundle);
    if (!$root || ($bundle !== '' && is_link($bundle))) throw new \RuntimeException('hvac_bundle_unavailable');
    $manifest = $root . '/manifest.json';
    if (!is_file($manifest) || is_link($manifest) || filesize($manifest) > 65536) throw new \RuntimeException('hvac_manifest_unavailable');
    if ($bundle !== '') {
      $private = realpath((string) Settings::get('file_private_path', ''));
      $hash = (string) Settings::get('famtastic_acquisition_hvac_bundle_sha256', '');
      if (!$private || !str_starts_with($root . '/', $private . '/') || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !hash_equals($hash, (string) hash_file('sha256', $manifest))) throw new \RuntimeException('hvac_private_bundle_required');
    }
    return [$root, (string) file_get_contents($manifest)];
  }

}
