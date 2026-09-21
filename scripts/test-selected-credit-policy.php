<?php
declare(strict_types=1);

// Dependency-free, synthetic-only policy/association/resolver checks. No Drupal,
// provider, network, database or customer artifact is booted by this harness.
$services = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
foreach (['SelectedCreatorCreditProjection', 'SelectedSourceIntent', 'SelectedFinalizedSource', 'SelectedSourceAssociation', 'ProofAssetContract', 'SelectedAssetRights', 'SelectedRecordResolver'] as $class) require_once $services . $class . '.php';
use Drupal\famtastic_pipeline\Service\SelectedCreatorCreditProjection as Credit;
use Drupal\famtastic_pipeline\Service\SelectedSourceAssociation as Association;
use Drupal\famtastic_pipeline\Service\SelectedSourceIntent as Intent;
use Drupal\famtastic_pipeline\Service\SelectedRecordResolver as Resolver;

if (($argv[1] ?? '') === '--policy') { echo json_encode(Credit::policy(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n"; exit; }
if (($argv[1] ?? '') === '--project') {
  $input = json_decode(stream_get_contents(STDIN), TRUE, 32, JSON_THROW_ON_ERROR);
  $html = base64_decode($input['html_base64'], TRUE);
  if ($html === FALSE) throw new InvalidArgumentException('invalid fixture base64');
  $artifact = ['path' => $input['source_path'] ?? 'web/proofs/synthetic.html', 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)];
  echo json_encode(Credit::project($html, $artifact), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
  exit;
}

final class Drupal { public static string $dir; public static function root(): string { return self::$dir . '/web'; } }
$checks = 0;
$check = static function(bool $condition, string $label) use (&$checks): void { if (!$condition) throw new RuntimeException($label); $checks++; };
$reject = static function(callable $run, string $message) use ($check): void {
  try { $run(); } catch (InvalidArgumentException $e) { $check(str_contains($e->getMessage(), $message), 'wrong rejection: ' . $e->getMessage()); return; }
  throw new RuntimeException('accepted: ' . $message);
};
$home = '<!doctype html><html lang="en"><head><title>Home</title></head><body><main><h1>Original café 雪 😀</h1></main></body></html>';
$artifact = static fn(string $html): array => ['role' => 'selected_preview', 'path' => 'web/proofs/index.html', 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)];
$file = static fn(string $path, string $html): array => ['path' => $path, 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)];
$wire = static function(array $record): array {
  $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  return ['schema' => 'famtastic.finalized-source-wire.v2', 'payload_json' => $json, 'sha256' => hash('sha256', "famtastic.finalized-source-wire.v2\n" . $json)];
};
$check(strlen(Credit::row()) === 694 && hash('sha256', Credit::row()) === Credit::policy()['row_sha256'], 'pinned root row');
$check(hash('sha256', json_encode(Credit::policy(), JSON_UNESCAPED_SLASHES)) === 'bb45adcade4cb87faac70ca84b14f8b1cb347395fbc8f3e14f50215d3704e914', 'frozen ordered policy');
foreach ([$home, str_replace('</body>', '</BODY>', $home), "\xEF\xBB\xBF" . str_replace('><', ">\r\n<", $home) . "\r\n"] as $html) {
  $expected = preg_replace_callback('~</body>~i', static fn() => Credit::row() . "\n</body>", $html, 1);
  $check(Credit::derive($html) === $expected, 'exact byte transform');
  $check(Credit::derive($expected) === $expected && Credit::project($expected, $artifact($expected))['mode'] === 'identity', 'canonical identity');
}
foreach ([str_replace('</body>', '</body >', $home), str_replace('</body>', '', $home), $home . '</body>', str_replace('</body>', '</body><!-- trailing -->', $home), str_replace('</head>', '<base href="https://example.invalid/"></head>', $home), str_replace('<body>', '<body hidden>', $home), str_replace('<body>', '<body style="opacity:0">', $home), str_replace('</body>', '<div data-fd-creator-credit="v1">Invalid</div></body>', $home), str_replace('</body>', '<!-- data-famtastic-creator-credit="1" --></body>', $home), str_replace('</body>', '<a href="https://famtasticdesigns.com/"><img alt="Created by FAMtastic Designs"></a></body>', $home), str_replace('</body>', '<script>' . Credit::row() . '</script></body>', $home), "\xFF" . $home] as $invalid) $reject(static fn() => Credit::derive($invalid), 'source_association_credit_');
$reject(static fn() => Credit::project($home, array_replace($artifact($home), ['sha256' => 'invalid'])), 'original_changed');

$tmp = sys_get_temp_dir() . '/selected-credit-policy-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700); mkdir($tmp . '/web', 0700); mkdir($tmp . '/web/proofs', 0700);
Drupal::$dir = $tmp;
register_shutdown_function(static function() use ($tmp): void {
  // Only paths created by this synthetic fixture; no recursive cleanup.
  foreach (['index.html', 'link.html'] as $name) { $path = $tmp . '/web/proofs/' . $name; if (is_file($path) || is_link($path)) unlink($path); }
  if (is_file($tmp . '/outside.html')) unlink($tmp . '/outside.html');
  rmdir($tmp . '/web/proofs'); rmdir($tmp . '/web'); rmdir($tmp);
});
file_put_contents($tmp . '/web/proofs/index.html', $home);
$page = ['record_id' => 'authored-about', 'customer_id' => '903', 'text' => ['page_name' => 'About', 'title' => 'About', 'description' => 'Our story', 'heading' => 'About us', 'body' => 'Actual authored copy.']];
$intake = ['page_count' => 2, 'page_list' => 'Home, About', 'authored_content' => ['pages' => [$page]]];
$row = ['id' => 901, 'public_id' => 'synthetic-request', 'project_id' => 902, 'customer_id' => 903, 'proof_campaign_id' => 904, 'proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'commerce_order_id' => NULL, 'intake_data' => json_encode($intake)];
$intent = Intent::create($row, '902', 905, 'a', 1, '2026-09-21T00:00:00Z', [$artifact($home)], [], [], NULL);
$intent['execution_binding'] = ['status' => 'synthetic_fixture'];
$studio = ['selected_source_intent' => $intent]; $now = 1789948800; $secret = 'synthetic-credit-policy-only';
$grant = Association::issue($row, $studio, $secret, $now);
$payload = json_decode($grant['payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
$check($payload['schema'] === 'famtastic.source-association.v2' && hash_equals($grant['signature'], hash_hmac('sha256', "famtastic.source-association.v2\n" . $grant['payload_json'], $secret)), 'v2 signature domain');
$check($payload['intent'] === $intent && $payload['creator_credit_projection'] === Credit::project($home, $artifact($home)), 'immutable intent independent projection');
$studio['source_association_grants'][$payload['association_id']] = $grant;
$about = Credit::derive('<!doctype html><html lang="en"><head><title>About</title><meta name="description" content="Our story"></head><body><main><h1 data-field-type="text">About us</h1><p data-field-type="text">Actual authored copy.</p></main></body></html>');
$make = static function(array $payload, array $grant, string $homeBytes, bool $logo = TRUE) use ($about, $file, $wire, $page): array {
  $files = [$file('index.html', $homeBytes), $file('about.html', $about)];
  if ($logo) $files[] = Credit::policy()['system_asset'];
  usort($files, static fn($a, $b) => strcmp($a['path'], $b['path']));
  $manifest = hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES));
  $record = ['schema' => 'famtastic.finalized-source.v1', 'site_id' => 'synthetic-site', 'run_id' => 'synthetic-run', 'files' => $files,
    'repository' => ['repository_path' => '/synthetic/customer-site'], 'manifest_sha256' => $manifest,
    'scope' => ['required_pages' => ['index.html', 'about.html'], 'request_scope_sha256' => $payload['scope_sha256'], 'evidence_ref' => 'association:' . $payload['association_id']],
    'scope_complete' => TRUE, 'issues' => [], 'use_restrictions' => NULL, 'provenance' => ['packet_id' => 'synthetic-packet', 'brief_hash' => 'synthetic-brief'],
    'review_qa' => ['passed' => TRUE, 'problems' => [], 'source_binding' => ['site_id' => 'synthetic-site', 'run_id' => 'synthetic-run', 'manifest_sha256' => $manifest]]];
  $export = $wire($record);
  $mapping = ['project_id' => '902', 'customer_id' => '903', 'request_id' => 'synthetic-request', 'site_id' => 'synthetic-site', 'run_id' => 'synthetic-run', 'association_id' => $payload['association_id'], 'association_scope_sha256' => $payload['scope_sha256'], 'originating_system' => 'studio', 'handoff_initiator' => 'studio', 'repository_path' => '/synthetic/customer-site', 'source_export_sha256' => $export['sha256'], 'source_export' => $export, 'evidence_ref' => 'synthetic-association', 'content_records' => ['about.html' => $page['record_id']]];
  if (isset($payload['creator_credit_projection'])) $mapping['creator_credit_projection'] = $payload['creator_credit_projection'];
  return ['project_id' => '902', 'customer_id' => '903', 'request_id' => 'synthetic-request', 'association' => $grant, 'source_export' => $export, 'source_completion' => $mapping, 'content_evidence' => ['about.html' => base64_encode($about)]];
};
$good = $make($payload, $grant, Credit::derive($home));
$check(Association::accept($row, $studio, $good, $now) === $good['source_completion'], 'v2 association');
$check(Association::accept($row, $studio, $good, $now) === Association::accept($row, $studio, $good, $now), 'exact duplicate stable');
$changeRecord = static function(array $envelope, callable $change) use ($wire): array {
  $record = json_decode($envelope['source_export']['payload_json'], TRUE); $change($record);
  // Re-sign the synthetic wire and refresh the claimed QA digest too: a fresh
  // worker claim cannot make an unauthorized Home/logo/content delta valid.
  $record['manifest_sha256'] = hash('sha256', json_encode($record['files'], JSON_UNESCAPED_SLASHES));
  $record['review_qa']['source_binding']['manifest_sha256'] = $record['manifest_sha256'];
  $export = $wire($record);
  $envelope['source_export'] = $envelope['source_completion']['source_export'] = $export;
  $envelope['source_completion']['source_export_sha256'] = $export['sha256']; return $envelope;
};
foreach ([
  static function(&$e) { unset($e['source_completion']['content_records']['about.html'], $e['content_evidence']['about.html']); },
  static function(&$e) { unset($e['content_evidence']['about.html']); },
  static function(&$e) { $e['content_evidence']['extra.html'] = 'Zg=='; },
  static function(&$e) { $e['source_completion']['content_records']['about.html'] = 'other'; },
  static function(&$e) { $e['content_evidence']['about.html'] = base64_encode('Different'); },
] as $mutate) { $e = $good; $mutate($e); $reject(static fn() => Association::accept($row, $studio, $e, $now), 'content_evidence_required'); }
foreach (['schema', 'policy_sha256', 'mode'] as $key) { $e = $good; $e['source_completion']['creator_credit_projection'][$key] = 'tampered'; $reject(static fn() => Association::accept($row, $studio, $e, $now), 'projection_mismatch'); }
foreach (['customer_id', 'project_id', 'request_id'] as $key) { $e = $good; $e['source_completion'][$key] = 'other'; $reject(static fn() => Association::accept($row, $studio, $e, $now), 'identity_changed'); }
foreach (['customer_id', 'project_id', 'request_id'] as $key) { $e = $good; $e[$key] = 'other'; $reject(static fn() => Association::accept($row, $studio, $e, $now), 'identity_changed'); }
$reject(static fn() => Association::accept($row, $studio, $good, $now + 3601), 'stale');
$reject(static fn() => Association::accept($row, $studio, $good, $now - 1), 'stale');
foreach ([['customer_id' => 999], ['commerce_order_id' => 1], ['selected_proof_direction' => 'b']] as $delta) $reject(static fn() => Association::accept(array_replace($row, $delta), $studio, $good, $now), 'current_selection_required');
$changed = $row; $changed['intake_data'] = json_encode(array_replace($intake, ['page_list' => 'Home, Team']));
$reject(static fn() => Association::accept($changed, $studio, $good, $now), 'current_input_changed');
$staleStudio = $studio; $staleStudio['selected_source_intent']['selection']['revision']++;
$reject(static fn() => Association::accept($row, $staleStudio, $good, $now), 'stale');
$staleStudio['selected_source_mapping'] = $good['source_completion'];
$check(Association::accept($row, $staleStudio, $good, $now) === $good['source_completion'], 'exact first refresh retry');
$staleStudio['selected_source_intent']['selection']['revision']++;
$reject(static fn() => Association::accept($row, $staleStudio, $good, $now), 'stale');
foreach ([static function(&$r) { $r['files'][0]['sha256'] = str_repeat('0', 64); }, static function(&$r) { $r['files'][] = $r['files'][0]; }, static function(&$r) { $r['files'][] = ['path' => 'extra.css', 'sha256' => str_repeat('a', 64), 'bytes' => 1]; }, static function(&$r) { $r['use_restrictions'] = ['scope' => 'protected_review_only']; }] as $mutate) {
  $e = $changeRecord($good, $mutate); $reject(static fn() => Association::accept($row, $studio, $e, $now), 'source_association_');
}
foreach (['sha256' => str_repeat('c', 64), 'bytes' => 2020724, 'path' => 'assets/brand/other.png'] as $key => $value) {
  $e = $changeRecord($good, static function(&$r) use ($key, $value) { foreach ($r['files'] as &$f) if ($f['path'] === Credit::ASSET_PATH) $f[$key] = $value; });
  $reject(static fn() => Association::accept($row, $studio, $e, $now), 'asset_changed');
}
$reject(static fn() => Association::accept($row, $studio, $make($payload, $grant, Credit::derive($home), FALSE), $now), 'asset_changed');
$reject(static fn() => Association::accept($row, $studio, $make($payload, $grant, $home), $now), 'home_changed');
foreach (['sha256' => str_repeat('d', 64), 'bytes' => strlen(Credit::derive($home)) + 1] as $key => $value) {
  $e = $changeRecord($good, static function(&$r) use ($key, $value) { foreach ($r['files'] as &$f) if ($f['path'] === 'index.html') $f[$key] = $value; });
  $reject(static fn() => Association::accept($row, $studio, $e, $now), 'home_changed');
}
$editedAbout = str_replace('Actual authored copy.', 'Unauthorized authored copy.', $about);
$e = $changeRecord($good, static function(&$r) use ($editedAbout, $file) { foreach ($r['files'] as &$f) if ($f['path'] === 'about.html') $f = $file('about.html', $editedAbout); });
$e['content_evidence']['about.html'] = base64_encode($editedAbout);
$reject(static fn() => Association::accept($row, $studio, $e, $now), 'completed_content_changed');
// Missing pages may remain explicitly incomplete, never silently complete.
$partial = $changeRecord($good, static function(&$r) { $r['files'] = array_values(array_filter($r['files'], static fn($f) => $f['path'] !== 'about.html')); $r['scope_complete'] = FALSE; $r['issues'] = ['required_pages_incomplete']; });
$partial['source_completion']['content_records'] = []; $partial['content_evidence'] = [];
$check(Association::accept($row, $studio, $partial, $now) === $partial['source_completion'], 'explicit incomplete scope');
$e = $changeRecord($partial, static function(&$r) { $r['scope_complete'] = TRUE; $r['issues'] = []; });
$reject(static fn() => Association::accept($row, $studio, $e, $now), 'scope_changed');
$e = $changeRecord($good, static function(&$r) { $r['issues'] = ['required_pages_incomplete']; });
$reject(static fn() => Association::accept($row, $studio, $e, $now), 'scope_changed');
foreach (['duplicate', 'wrong_customer', 'wrong_page'] as $kind) {
  $pages = [$page];
  if ($kind === 'duplicate') $pages[] = array_replace($page, ['record_id' => 'second-about']);
  elseif ($kind === 'wrong_customer') $pages[0]['customer_id'] = '999';
  else $pages[0]['text']['page_name'] = 'Team';
  $r = $row; $r['intake_data'] = json_encode(array_replace($intake, ['authored_content' => ['pages' => $pages]]));
  $s = ['selected_source_intent' => Intent::create($r, '902', 905, 'a', 1, '2026-09-21T00:00:00Z', [$artifact($home)], [], [], NULL)];
  $g = Association::issue($r, $s, $secret, $now); $p = json_decode($g['payload_json'], TRUE); $s['source_association_grants'][$p['association_id']] = $g;
  $e = $make($p, $g, Credit::derive($home));
  $reject(static fn() => Association::accept($r, $s, $e, $now), 'content_evidence_required');
}
$bad = $payload; $bad['creator_credit_projection']['policy']['row_bytes']++;
$badGrant = ['payload_json' => json_encode($bad, JSON_UNESCAPED_SLASHES), 'signature' => 'synthetic-invalid'];
$e = $good; $e['association'] = $badGrant;
$reject(static fn() => Association::accept($row, $studio, $e, $now), 'grant_unknown');
// Even an independently signed/stored unsupported policy cannot redefine v1.
$badGrant['signature'] = hash_hmac('sha256', "famtastic.source-association.v2\n" . $badGrant['payload_json'], $secret);
$badStudio = $studio; $badStudio['source_association_grants'][$payload['association_id']] = $badGrant; $e['association'] = $badGrant;
$reject(static fn() => Association::accept($row, $badStudio, $e, $now), 'projection_mismatch');

$legacy = Association::issue($row, $studio, $secret, $now, 'famtastic.source-association.v1'); $v1 = json_decode($legacy['payload_json'], TRUE);
$legacyStudio = $studio; $legacyStudio['source_association_grants'][$v1['association_id']] = $legacy;
$legacyEnvelope = $make($v1, $legacy, $home, FALSE);
$check(!isset($v1['creator_credit_projection']) && $legacy['signature'] === hash_hmac('sha256', "famtastic.source-association.v1\n" . $legacy['payload_json'], $secret), 'v1 original signature');
$check(Association::accept($row, $legacyStudio, $legacyEnvelope, $now) === $legacyEnvelope['source_completion'], 'v1 exact assetless behavior');
$legacyMissing = $legacyEnvelope; $legacyMissing['source_completion']['content_records'] = []; $legacyMissing['content_evidence'] = [];
$check(Association::accept($row, $legacyStudio, $legacyMissing, $now) === $legacyMissing['source_completion'], 'v1 content-loop semantics unchanged');
$reject(static fn() => Association::accept($row, $legacyStudio, $make($v1, $legacy, $home), $now), 'asset_authority_required');
$reject(static fn() => Association::accept($row, $legacyStudio, $make($v1, $legacy, Credit::derive($home), FALSE), $now), 'selected_source_changed');

// The older explicit-file-authority path deliberately gets no grant exception.
$oneRow = $row; $oneRow['intake_data'] = json_encode(['page_count' => 1, 'page_list' => 'Home']);
$oneIntent = Intent::create($oneRow, '902', 905, 'a', 1, '2026-09-21T00:00:00Z', [$artifact($home)], [], [], NULL);
$alternate = $changeRecord($good, static function(&$r) use ($file, $home) {
  $r['files'] = [$file('index.html', Credit::derive($home)), Credit::policy()['system_asset']];
  $r['scope']['required_pages'] = ['index.html'];
});
$authority = $alternate['source_completion'] + ['scope_evidence_ref' => 'association:' . $payload['association_id'], 'request_scope_sha256' => $oneIntent['scope']['snapshot_sha256'],
  'files' => ['index.html' => ['source_path' => $artifact($home)['path'], 'url' => 'https://agency.example.invalid/original', 'rights' => ['status' => 'approved', 'evidence_ref' => 'synthetic-source']]]];
$reject(static fn() => \Drupal\famtastic_pipeline\Service\SelectedFinalizedSource::continuation($alternate['source_export'], $oneIntent, $authority), 'file_authority_binding_required');
$brandedIntent = $oneIntent; $brandedIntent['source']['artifacts'] = [$artifact(Credit::derive($home))];
$reject(static fn() => \Drupal\famtastic_pipeline\Service\SelectedFinalizedSource::continuation($alternate['source_export'], $brandedIntent, $authority), 'file_authority_binding_required');

$installation = ['artifact_base_url' => 'https://agency.example.invalid', 'targets' => ['902' => ['customer_id' => '903']], 'authored_shell_policy' => ['status' => 'approved', 'evidence_ref' => 'synthetic-owned-code']];
$resolve = static fn(array $mapping) => Resolver::resolve($row, [], $intent, [$artifact($home)], $installation, $tmp, $mapping);
$result = $resolve($good['source_completion']);
$check($result['continuation']['operation'] === 'package_existing' && count($result['continuation']['files']) === 3, 'mapped exact derivative and PNG');
$check($result['artifacts'][0] === $artifact($home), 'mapped selection remains original');
$normal = $good['source_completion']; unset($normal['association_id'], $normal['association_scope_sha256'], $normal['creator_credit_projection']);
$check(count($resolve($normal)['continuation']['files']) === 3, 'ordinary verified mapping independently projects');
$v1Mapped = $good['source_completion']; unset($v1Mapped['creator_credit_projection']);
$reject(static fn() => $resolve($v1Mapped), 'source_changed_requires_edit_recipe');
$cross = $good['source_completion']; $cross['customer_id'] = 'other'; $reject(static fn() => $resolve($cross), 'identity_mismatch');
$tampered = $changeRecord($good, static function(&$r) { foreach ($r['files'] as &$f) if ($f['path'] === 'index.html') $f['sha256'] = str_repeat('b', 64); });
$reject(static fn() => $resolve($tampered['source_completion']), 'home_changed');
$tampered = $good['source_completion']; $tampered['creator_credit_projection']['policy']['publication_authorized'] = TRUE;
$reject(static fn() => $resolve($tampered), 'projection_mismatch');
$tampered = $changeRecord($good, static function(&$r) { $r['files'][] = ['path' => 'extra.png', 'sha256' => str_repeat('a', 64), 'bytes' => 10]; });
$reject(static fn() => $resolve($tampered['source_completion']), 'existing_page_removal_requires_edit_recipe');
file_put_contents($tmp . '/web/proofs/index.html', $home . 'changed');
$reject(static fn() => Association::accept($row, $studio, $good, $now), 'current_source_changed');
// Identity grants still require the exact system asset; unsupported existing
// presentation credit never turns into a signed repair authorization.
$identityHome = Credit::derive($home); file_put_contents($tmp . '/web/proofs/index.html', $identityHome);
$identityStudio = ['selected_source_intent' => Intent::create($row, '902', 905, 'a', 1, '2026-09-21T00:00:00Z', [$artifact($identityHome)], [], [], NULL)];
$g = Association::issue($row, $identityStudio, $secret, $now); $p = json_decode($g['payload_json'], TRUE); $identityStudio['source_association_grants'][$p['association_id']] = $g;
$e = $make($p, $g, $identityHome);
$check($p['creator_credit_projection']['mode'] === 'identity' && Association::accept($row, $identityStudio, $e, $now) === $e['source_completion'], 'signed canonical identity');
$invalidHome = str_replace('</body>', '<div data-fd-creator-credit="v1">Presentation credit</div></body>', $home);
file_put_contents($tmp . '/web/proofs/index.html', $invalidHome);
$invalidStudio = ['selected_source_intent' => Intent::create($row, '902', 905, 'a', 1, '2026-09-21T00:00:00Z', [$artifact($invalidHome)], [], [], NULL)];
$reject(static fn() => Association::issue($row, $invalidStudio, $secret, $now), 'existing_unsupported');
file_put_contents($tmp . '/web/proofs/index.html', $home);
file_put_contents($tmp . '/outside.html', $home); symlink($tmp . '/outside.html', $tmp . '/web/proofs/link.html');
$escaped = $artifact($home); $escaped['path'] = 'web/proofs/link.html'; $reject(static fn() => Credit::fromStorage($tmp, $escaped), 'original_path_invalid');
$escaped['path'] = 'web/proofs/../../outside.html'; $reject(static fn() => Credit::fromStorage($tmp, $escaped), 'original_path_invalid');
$escaped['path'] = 'outside.html'; $reject(static fn() => Credit::fromStorage($tmp, $escaped), 'original_path_invalid');
echo "PASS: $checks focused creator-credit policy assertions; synthetic/local only.\n";
