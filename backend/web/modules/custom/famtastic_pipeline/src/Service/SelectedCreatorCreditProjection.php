<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Pinned source projection, not the agency's presentation-only decorator. */
final class SelectedCreatorCreditProjection {
  public const SCHEMA = 'famtastic.creator-credit-projection.v1';
  public const ASSET_PATH = 'assets/brand/famtastic-designs-logo-v1.png';
  private const MAX_BYTES = 25 * 1024 * 1024;
  // Exact creatorCreditRow({page: 'index.html'}) at the commit in policy().
  private const ROW = '<div data-famtastic-creator-credit="1" style="display:flex;justify-content:center;align-items:center;width:100%;box-sizing:border-box;padding:12px 16px;clear:both"><a href="https://famtasticdesigns.com/" aria-label="Created by FAMtastic Designs" style="display:inline-flex;align-items:center;justify-content:center;min-height:44px;min-width:44px;max-width:100%;padding:8px 12px;box-sizing:border-box;background:#111111;border-radius:6px"><img src="assets/brand/famtastic-designs-logo-v1.png" alt="FAMtastic Designs" width="2172" height="724" style="display:block;width:clamp(160px,40vw,220px);max-width:100%;height:auto;object-fit:contain;filter:none;opacity:1;mix-blend-mode:normal"></a></div>';

  public static function policy(): array {
    return [
      'id' => 'famtastic.canonical-root-credit.v1',
      'foundation_commit' => '2937a3bf58c52f146734b8779375ec884c6417ae',
      'public_base_path' => '/',
      'row_sha256' => '0cffde4586bb4dbc1c9163f5154b1f94f26e82e3c4f7541b840b67fb67566bc4',
      'row_bytes' => 694,
      'system_asset' => ['path' => self::ASSET_PATH, 'sha256' => 'ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950', 'bytes' => 2020725],
      'authorization' => 'owner_creator_attribution_only',
      'customer_acceptance_changed' => FALSE,
      'publication_authorized' => FALSE,
    ];
  }

  public static function row(): string { return self::ROW; }

  /** Conservative subset of the pinned Node append rule; never repair a row. */
  public static function derive(string $html): string {
    if (strlen($html) > self::MAX_BYTES || !preg_match('//u', $html) || str_contains($html, "\0")
      || preg_match_all('~</body>~i', $html) !== 1
      || !preg_match('~</body>\s*(?:</html>\s*)?\z~i', $html)) self::fail('document_unsupported');
    $policy = self::policy();
    if (strlen(self::ROW) !== $policy['row_bytes'] || hash('sha256', self::ROW) !== $policy['row_sha256']) self::fail('policy_changed');
    $identity = substr_count($html, self::ROW) === 1;
    $bare = $identity ? str_replace(self::ROW, '', $html) : $html;
    // Deliberately reject ambiguous/noncanonical credits, including markers in
    // inert text. Do not pretend PHP presentation markup is this canonical row.
    $decoded = html_entity_decode($bare, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('~data-(?:famtastic|fd)-creator-credit|famtasticdesigns\.com~i', $decoded)) self::fail('existing_unsupported');
    $derived = $identity ? $html : preg_replace_callback('~</body>~i', static fn() => self::ROW . "\n</body>", $html, 1);
    if (!preg_match('~' . preg_quote(self::ROW, '~') . '\n</body>\s*(?:</html>\s*)?\z~i', $derived)) self::fail('existing_unsupported');
    // Parse only to reject hidden, nested, inert or malformed placement. Never
    // serialize the DOM back into the approved/derived byte stream.
    $dom = new \DOMDocument(); $prior = libxml_use_internal_errors(TRUE);
    try { $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $derived, LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    $xpath = new \DOMXPath($dom);
    $rows = $xpath->query('//*[@data-famtastic-creator-credit]');
    if (!$ok || $rows->length !== 1 || $rows->item(0)->parentNode->nodeName !== 'body'
      || $xpath->query('//base')->length) self::fail('document_unsupported');
    for ($node = $rows->item(0); $node instanceof \DOMElement; $node = $node->parentNode) {
      if ($node->hasAttribute('hidden') || strtolower($node->getAttribute('aria-hidden')) === 'true'
        || preg_match('~display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0(?:[;\s]|$)~i', $node->getAttribute('style'))) self::fail('document_unsupported');
    }
    return $derived;
  }

  /** Pure; the caller must supply authoritative original bytes, never a receipt. */
  public static function project(string $html, array $artifact): array {
    if (($artifact['sha256'] ?? NULL) !== hash('sha256', $html) || ($artifact['bytes'] ?? NULL) !== strlen($html)
      || !is_string($artifact['path'] ?? NULL)) self::fail('original_changed');
    $derived = self::derive($html); $policy = self::policy();
    return ['schema' => self::SCHEMA, 'policy' => $policy,
      'policy_sha256' => hash('sha256', json_encode($policy, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
      'mode' => $derived === $html ? 'identity' : 'append_missing_root_row',
      'original_home' => ['source_path' => $artifact['path'], 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)],
      'derived_home' => ['path' => 'index.html', 'sha256' => hash('sha256', $derived), 'bytes' => strlen($derived)]];
  }

  /** Only the agency proof store can supply originals for grant or continuation. */
  public static function fromStorage(string $storageRoot, array $artifact): array {
    $base = realpath($storageRoot); $root = realpath($storageRoot . '/web/proofs');
    $relative = $artifact['path'] ?? '';
    if (!$base || !$root || !str_starts_with($root, $base . DIRECTORY_SEPARATOR) || !is_string($relative)
      || !preg_match('~\A[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*\z~', $relative)
      || array_intersect(explode('/', $relative), ['.', '..'])
      || !is_int($artifact['bytes'] ?? NULL) || $artifact['bytes'] < 1 || $artifact['bytes'] > self::MAX_BYTES) self::fail('original_path_invalid');
    $cursor = $base;
    foreach (explode('/', $relative) as $part) { $cursor .= '/' . $part; if (is_link($cursor)) self::fail('original_path_invalid'); }
    $path = realpath($cursor);
    if (!$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) self::fail('original_path_invalid');
    $bytes = file_get_contents($path, FALSE, NULL, 0, self::MAX_BYTES + 1);
    if ($bytes === FALSE) self::fail('original_changed');
    return self::project($bytes, $artifact);
  }

  public static function assertProjection(mixed $supplied, array $expected): void {
    if ($supplied !== $expected) self::fail('projection_mismatch');
  }

  /** No broad extension/hash exception: exactly Home plus the pinned PNG. */
  public static function assertHomeAndLogo(array $files, array $projection): void {
    $byPath = [];
    foreach ($files as $file) {
      if (!is_array($file) || !is_string($file['path'] ?? NULL) || isset($byPath[$file['path']])
        || !is_int($file['bytes'] ?? NULL) || $file['bytes'] < 1
        || !preg_match('/\A[a-f0-9]{64}\z/', $file['sha256'] ?? '')) self::fail('manifest_invalid');
      $byPath[$file['path']] = ['path' => $file['path'], 'sha256' => $file['sha256'], 'bytes' => $file['bytes']];
    }
    if (($byPath['index.html'] ?? NULL) !== $projection['derived_home']) self::fail('home_changed');
    if (($byPath[self::ASSET_PATH] ?? NULL) !== self::policy()['system_asset']) self::fail('asset_changed');
  }

  private static function fail(string $code): never {
    throw new \InvalidArgumentException('source_association_credit_' . $code);
  }
}
