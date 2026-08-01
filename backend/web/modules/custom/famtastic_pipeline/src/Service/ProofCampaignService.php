<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\ProofVariant;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Psr\Log\LoggerInterface;

/**
 * Creates and manages proof campaigns (3 design directions per prospect).
 *
 * On first creation the service generates three proof variants. When a Site
 * Studio URL is configured the generation request is handed off through the
 * module's Site Studio adapter interface (and local placeholder artifacts are
 * written so previews work immediately); otherwise a built-in stub generator
 * writes three distinct, presentable static proof sites under
 * backend/web/proofs/<campaign_id>/<a|b|c>/index.html.
 */
class ProofCampaignService {

  /**
   * Direction id => human-facing direction name.
   */
  public const DIRECTIONS = [
    'a' => 'Bold and Modern',
    'b' => 'Trusted and Professional',
    'c' => 'Local and Approachable',
  ];

  /**
   * Packages a prospect may choose with a variant.
   */
  public const PACKAGES = ['essential_199', 'business_499'];

  /**
   * Campaign lifetime in seconds (7 days).
   */
  protected const TTL = 7 * 24 * 60 * 60;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected FileSystemInterface $fileSystem,
    protected SiteStudioAdapterInterface $studioAdapter,
    protected PipelineRepository $repository,
    protected LoggerInterface $logger,
    protected SiteStudioProofClient $studioClient,
    protected OperationalLedger $ledger,
  ) {}

  /**
   * Creates a proof campaign with 3 variants for a prospect.
   *
   * Idempotency is enforced by the caller (controller returns any existing
   * active campaign first), so this always creates a fresh campaign.
   *
   * @return array{campaign:\Drupal\famtastic_pipeline\Entity\ProofCampaign,variants:\Drupal\famtastic_pipeline\Entity\ProofVariant[]}
   */
  public function createForProspect(Prospect $prospect): array {
    $businessName = (string) ($prospect->get('business_name')->value ?: 'Your Business');
    $campaignId = $this->buildCampaignId($businessName);
    $now = $this->time->getRequestTime();

    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
    $campaign = $storage->create([
      'campaign_id' => $campaignId,
      'prospect_id' => $prospect->id(),
      'business_name' => $businessName,
      'status' => 'active',
      'generation_status' => $this->studioClient->isRemote() ? 'dispatching' : 'ready',
      'expires_at' => $now + self::TTL,
    ]);
    $campaign->save();

    if ($this->studioClient->isRemote()) {
      $jobId = $this->studioClient->dispatch($prospect, $campaign);
      $campaign
        ->set('generation_status', 'waiting_callback')
        ->set('studio_job_id', $jobId)
        ->set('dispatched_at', $now)
        ->save();
      $this->ledger->recordEvent(
        'proof.dispatched:' . $campaignId,
        'proof.dispatched',
        ['campaign_id' => $campaignId, 'studio_job_id' => $jobId],
        (int) $prospect->id(),
      );
      return ['campaign' => $campaign, 'variants' => []];
    }

    $source = 'local';

    $variants = [];
    $variantStorage = $this->entityTypeManager->getStorage('proof_variant');
    foreach (self::DIRECTIONS as $direction => $directionName) {
      $artifact = $this->writeStubArtifact($campaignId, $direction, $directionName, $businessName, $prospect, $source);
      $dna = [
        'source' => $source,
        'direction' => $direction,
        'direction_name' => $directionName,
        'business_name' => $businessName,
        'palette' => $this->palette($direction),
        'typography' => $direction === 'b' ? 'Georgia serif headlines, system body' : 'System sans headlines, generous body',
        'layout' => $direction === 'c' ? 'Single column, warm and personal' : 'Hero-first landing with section blocks',
        'generated_at' => date(DATE_ATOM, $now),
      ];
      /** @var \Drupal\famtastic_pipeline\Entity\ProofVariant $variant */
      $variant = $variantStorage->create([
        'campaign_id' => $campaign->id(),
        'direction_id' => $direction,
        'direction_name' => $directionName,
        'artifact_path' => $artifact,
        'design_dna' => json_encode($dna, JSON_UNESCAPED_SLASHES),
        'thumbnail_path' => NULL,
        'preview_url' => $this->previewUrl($campaignId, $direction),
      ]);
      $variant->save();
      $variants[] = $variant;
    }

    $this->logger->info('Proof campaign @cid created for prospect @p (@src).', [
      '@cid' => $campaignId,
      '@p' => $prospect->id(),
      '@src' => $source,
    ]);
    $campaign->set('ready_at', $now)->save();

    return ['campaign' => $campaign, 'variants' => $variants];
  }

  /**
   * Accepts one idempotent exactly-three Site Studio callback.
   */
  public function acceptCallback(string $eventId, string $campaignId, string $studioJobId, array $variants): array {
    if ($eventId === '' || strlen($eventId) > 255) {
      throw new \InvalidArgumentException('callback event_id is required.');
    }
    $campaign = $this->loadByCampaignId($campaignId);
    if (!$campaign || !hash_equals((string) $campaign->get('studio_job_id')->value, $studioJobId)) {
      throw new \InvalidArgumentException('Unknown campaign or Site Studio job.');
    }
    $processed = json_decode((string) $campaign->get('callback_event_ids')->value ?: '[]', TRUE);
    if (in_array($eventId, (array) $processed, TRUE)) {
      return ['newly_processed' => FALSE, 'campaign' => $campaign, 'variants' => $this->loadVariants($campaign)];
    }
    if (count($variants) !== 3) {
      throw new \InvalidArgumentException('Exactly three variants are required.');
    }
    $validated = [];
    foreach ($variants as $variant) {
      if (!is_array($variant)) {
        throw new \InvalidArgumentException('Each variant must be an object.');
      }
      $direction = strtolower((string) ($variant['direction_id'] ?? ''));
      $html = (string) ($variant['html'] ?? '');
      if (!array_key_exists($direction, self::DIRECTIONS) || isset($validated[$direction])) {
        throw new \InvalidArgumentException('Variants must contain unique directions a, b, and c.');
      }
      if ($html === '' || strlen($html) > 500000) {
        throw new \InvalidArgumentException('Each proof HTML artifact is required and limited to 500 KB.');
      }
      if (preg_match('/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i', $html)) {
        throw new \InvalidArgumentException('Proof HTML contains disallowed active content.');
      }
      $thumbnail = NULL;
      $thumbnailBase64 = (string) ($variant['thumbnail_base64'] ?? '');
      if ($thumbnailBase64 !== '') {
        $mediaType = strtolower((string) ($variant['thumbnail_media_type'] ?? ''));
        if (!in_array($mediaType, ['image/jpeg', 'image/png'], TRUE)) {
          throw new \InvalidArgumentException('Proof thumbnail must be JPEG or PNG.');
        }
        $thumbnail = base64_decode($thumbnailBase64, TRUE);
        if ($thumbnail === FALSE || strlen($thumbnail) > 1500000) {
          throw new \InvalidArgumentException('Proof thumbnail is invalid or exceeds 1.5 MB.');
        }
      }
      $validated[$direction] = [
        'html' => $html,
        'thumbnail' => $thumbnail,
        'thumbnail_extension' => (($variant['thumbnail_media_type'] ?? '') === 'image/png') ? 'png' : 'jpg',
        'design_dna' => is_array($variant['design_dna'] ?? NULL) ? $variant['design_dna'] : [],
      ];
    }
    if (array_keys($validated) !== ['a', 'b', 'c']) {
      ksort($validated);
    }
    if (array_keys($validated) !== ['a', 'b', 'c']) {
      throw new \InvalidArgumentException('Variants must contain directions a, b, and c.');
    }
    if ($this->loadVariants($campaign)) {
      throw new \InvalidArgumentException('Campaign already has proof artifacts.');
    }
    $storage = $this->entityTypeManager->getStorage('proof_variant');
    $created = [];
    foreach ($validated as $direction => $variant) {
      $path = $this->writeCallbackArtifact($campaignId, $direction, $variant['html']);
      $thumbnailPath = $variant['thumbnail'] === NULL
        ? NULL
        : $this->writeCallbackThumbnail($campaignId, $direction, $variant['thumbnail'], $variant['thumbnail_extension']);
      $entity = $storage->create([
        'campaign_id' => $campaign->id(),
        'direction_id' => $direction,
        'direction_name' => self::DIRECTIONS[$direction],
        'artifact_path' => $path,
        'thumbnail_path' => $thumbnailPath,
        'design_dna' => json_encode($variant['design_dna'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'preview_url' => $this->previewUrl($campaignId, $direction),
      ]);
      $entity->save();
      $created[] = $entity;
    }
    $processed[] = $eventId;
    $campaign
      ->set('callback_event_ids', json_encode(array_values($processed), JSON_THROW_ON_ERROR))
      ->set('generation_status', 'ready')
      ->set('ready_at', $this->time->getRequestTime())
      ->save();
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $this->ledger->recordEvent(
      'proof.callback:' . $eventId,
      'proof.ready',
      ['campaign_id' => $campaignId, 'studio_job_id' => $studioJobId, 'variant_count' => 3],
      $prospectId,
    );
    $this->ledger->enqueue(
      'outreach.prepare:prospect:' . $prospectId . ':campaign:' . $campaign->id(),
      'outreach.prepare',
      ['prospect_id' => $prospectId, 'proof_campaign_id' => (int) $campaign->id()],
      $prospectId,
    );
    return ['newly_processed' => TRUE, 'campaign' => $campaign, 'variants' => $created];
  }

  /**
   * Returns the latest campaign + variants for a prospect, or NULL.
   *
   * @return array{campaign:\Drupal\famtastic_pipeline\Entity\ProofCampaign,variants:\Drupal\famtastic_pipeline\Entity\ProofVariant[]}|null
   */
  public function getForProspect(Prospect $prospect): ?array {
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('prospect_id', $prospect->id())
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
    $campaign = $storage->load(reset($ids));
    return ['campaign' => $campaign, 'variants' => $this->loadVariants($campaign)];
  }

  /**
   * Records the prospect's variant + package selection.
   *
   * @throws \InvalidArgumentException
   *   When the variant id or package is not allowed.
   */
  public function select(ProofCampaign $campaign, string $variantId, string $package): ProofCampaign {
    $variantId = strtolower(trim($variantId));
    if (!array_key_exists($variantId, self::DIRECTIONS)) {
      throw new \InvalidArgumentException('variant_id must be one of: a, b, c.');
    }
    $package = trim($package);
    if (!in_array($package, self::PACKAGES, TRUE)) {
      throw new \InvalidArgumentException('package must be one of: essential_199, business_499.');
    }
    $campaign->set('selected_variant', $variantId);
    $campaign->set('selected_package', $package);
    $campaign->set('selected_at', $this->time->getRequestTime());
    $campaign->save();
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $this->ledger->recordEvent(
      'proof.selected:' . $campaign->get('campaign_id')->value,
      'proof.selected',
      [
        'campaign_id' => $campaign->get('campaign_id')->value,
        'variant_id' => $variantId,
        'package' => $package,
      ],
      $prospectId,
    );
    return $campaign;
  }

  /**
   * Expires every active campaign past its expiry; returns the count.
   */
  public function expireActive(): int {
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'active')
      ->condition('expires_at', $this->time->getRequestTime(), '<')
      ->execute();
    $count = 0;
    foreach ($ids as $id) {
      /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
      $campaign = $storage->load($id);
      if ($campaign) {
        $campaign->set('status', 'expired');
        $campaign->save();
        $count++;
      }
    }
    return $count;
  }

  /**
   * Marks a campaign converted after a successful payment.
   *
   * Called from fulfillment when the Stripe checkout session metadata carries
   * a campaign_id. Idempotent: an already-converted campaign is left alone.
   */
  public function markConverted(string $campaignId, ?string $stripeOrderId = NULL): bool {
    $campaign = $this->loadByCampaignId($campaignId);
    if (!$campaign) {
      $this->logger->warning('Proof campaign @cid not found for conversion.', ['@cid' => $campaignId]);
      return FALSE;
    }
    if ($campaign->get('status')->value === 'converted') {
      return TRUE;
    }
    $campaign->set('status', 'converted');
    if ($stripeOrderId) {
      $campaign->set('stripe_order_id', $stripeOrderId);
    }
    $campaign->save();
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $this->ledger->recordEvent(
      'proof.converted:' . $campaignId,
      'proof.converted',
      ['campaign_id' => $campaignId, 'checkout_session_id' => $stripeOrderId],
      $prospectId,
    );
    $this->logger->info('Proof campaign @cid marked converted.', ['@cid' => $campaignId]);
    return TRUE;
  }

  /**
   * Returns the active selection for Stripe metadata, if any.
   *
   * @return array{campaign_id:string,selected_variant:string,selected_package:string}|null
   */
  public function activeSelection(Prospect $prospect): ?array {
    $found = $this->getForProspect($prospect);
    if (!$found) {
      return NULL;
    }
    $campaign = $found['campaign'];
    if ($campaign->get('status')->value !== 'active' || $campaign->isExpired()) {
      return NULL;
    }
    $variant = $campaign->get('selected_variant')->value;
    $package = $campaign->get('selected_package')->value;
    if (!$variant || !$package) {
      return NULL;
    }
    return [
      'campaign_id' => $campaign->get('campaign_id')->value,
      'selected_variant' => $variant,
      'selected_package' => $package,
    ];
  }

  /**
   * Loads a campaign by its public campaign_id.
   */
  public function loadByCampaignId(string $campaignId): ?ProofCampaign {
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', $campaignId)
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Loads all variants for a campaign, ordered a, b, c.
   *
   * @return \Drupal\famtastic_pipeline\Entity\ProofVariant[]
   */
  protected function loadVariants(ProofCampaign $campaign): array {
    $storage = $this->entityTypeManager->getStorage('proof_variant');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', $campaign->id())
      ->sort('direction_id', 'ASC')
      ->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  /**
   * Builds the public campaign id: pc-<slug>-<random4>.
   */
  protected function buildCampaignId(string $businessName): string {
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $businessName));
    $slug = trim($slug, '-');
    $slug = $slug === '' ? 'business' : substr($slug, 0, 32);
    return sprintf('pc-%s-%s', $slug, bin2hex(random_bytes(8)));
  }

  /**
   * Hands the generation request to Site Studio when a studio URL is set.
   *
   * @return bool
   *   TRUE when a handoff was made through the adapter interface.
   */
  protected function dispatchToStudio(Prospect $prospect, ProofCampaign $campaign): bool {
    $studioUrl = (string) $this->configFactory->get('famtastic_pipeline.settings')->get('studio_url');
    if ($studioUrl === '') {
      return FALSE;
    }
    $project = $this->repository->getProject($prospect);
    if (!$project) {
      $this->logger->warning('studio_url is configured but prospect @p has no project; using stub proofs.', ['@p' => $prospect->id()]);
      return FALSE;
    }
    $json = [
      'type' => 'proof_campaign',
      'campaign_id' => $campaign->get('campaign_id')->value,
      'studio_url' => $studioUrl,
      'directions' => self::DIRECTIONS,
      'output_dir' => 'web/proofs/' . $campaign->get('campaign_id')->value . '/',
    ];
    $brief = sprintf(
      "Proof campaign %s for %s\n\nGenerate three design directions (a/b/c) as static HTML under web/proofs/%s/<direction>/index.html.\n",
      $campaign->get('campaign_id')->value,
      $campaign->get('business_name')->value,
      $campaign->get('campaign_id')->value,
    );
    try {
      $result = $this->studioAdapter->submit($json, $brief, $project);
      $this->logger->info('Site Studio proof handoff for @cid: @note', [
        '@cid' => $campaign->get('campaign_id')->value,
        '@note' => $result['note'] ?? 'submitted',
      ]);
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->logger->error('Site Studio handoff failed for @cid: @m — falling back to stub proofs.', [
        '@cid' => $campaign->get('campaign_id')->value,
        '@m' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Writes one static stub proof page; returns its backend-relative path.
   */
  protected function writeStubArtifact(string $campaignId, string $direction, string $directionName, string $businessName, Prospect $prospect, string $source): string {
    $relative = 'web/proofs/' . $campaignId . '/' . $direction . '/index.html';
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($this->stubHtml($direction, $directionName, $businessName, $prospect, $source), $absolute . '/index.html', FileSystemInterface::EXISTS_REPLACE);
    return $relative;
  }

  /**
   * Writes validated callback HTML into its isolated campaign/direction path.
   */
  protected function writeCallbackArtifact(string $campaignId, string $direction, string $html): string {
    $relative = 'web/proofs/' . $campaignId . '/' . $direction . '/index.html';
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($html, $absolute . '/index.html', FileSystemInterface::EXISTS_REPLACE);
    return $relative;
  }

  /**
   * Writes a generated proof screenshot and returns its public URL path.
   */
  protected function writeCallbackThumbnail(string $campaignId, string $direction, string $binary, string $extension): string {
    $filename = 'thumbnail.' . ($extension === 'png' ? 'png' : 'jpg');
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($binary, $absolute . '/' . $filename, FileSystemInterface::EXISTS_REPLACE);
    return '/proofs/' . $campaignId . '/' . $direction . '/' . $filename;
  }

  /**
   * Builds the public preview URL for a direction.
   *
   * Default: {scheme}{host}/proofs/<campaign_id>/<direction>/. The full
   * base (including the /proofs prefix) is overridable via the
   * famtastic_pipeline.settings.proofs_base_url config for prod.
   */
  protected function previewUrl(string $campaignId, string $direction): string {
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('proofs_base_url'), '/');
    if ($base === '') {
      $base = \Drupal::request()->getSchemeAndHttpHost() . '/proofs';
    }
    return $base . '/' . $campaignId . '/' . $direction . '/';
  }

  /**
   * Accent palette per direction (dark + lime family, visually distinct).
   *
   * @return array{bg:string,accent:string,ink:string}
   */
  protected function palette(string $direction): array {
    return match ($direction) {
      'a' => ['bg' => '#0c0f0a', 'accent' => '#b8f135', 'ink' => '#f4f7ee'],
      'b' => ['bg' => '#101418', 'accent' => '#8fd14f', 'ink' => '#eef2f6'],
      default => ['bg' => '#131009', 'accent' => '#cdea44', 'ink' => '#faf6ea'],
    };
  }

  /**
   * Renders a complete, presentable stub proof page.
   */
  protected function stubHtml(string $direction, string $directionName, string $businessName, Prospect $prospect, string $source): string {
    $p = $this->palette($direction);
    $e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $tagline = $e($prospect->get('business_description')->value) ?: 'A local business ready to grow online.';
    $phone = $e($prospect->get('public_phone')->value);
    $area = $e($prospect->get('service_area')->value ?: $prospect->get('address')->value);

    $fonts = match ($direction) {
      'b' => "font-family: Georgia, 'Times New Roman', serif;",
      default => "font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif;",
    };
    $heroAlign = $direction === 'c' ? 'text-align:center;' : 'text-align:left;';
    $radius = $direction === 'a' ? '0' : ($direction === 'b' ? '4px' : '18px');
    $letter = $direction === 'a' ? 'letter-spacing:-0.03em;text-transform:uppercase;' : '';
    $services = match ($direction) {
      'a' => ['Web Presence', 'Brand Identity', 'Growth Campaigns'],
      'b' => ['Reliable Service', 'Proven Results', 'Free Estimates'],
      default => ['Friendly Local Team', 'Fast Response', 'Fair Pricing'],
    };
    $items = '';
    foreach ($services as $s) {
      $items .= '<div class="card"><h3>' . $e($s) . '</h3><p>Everything ' . $e($businessName) . ' needs, handled with care from first call to final sign-off.</p></div>';
    }
    $contactBits = trim($phone . ($phone && $area ? ' &middot; ' : '') . $area);
    $note = $source === 'site_studio' ? 'Concept preview — final design in production.' : 'Design concept preview.';

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $e($businessName) . ' — ' . $e($directionName) . '</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:' . $p['bg'] . '; color:' . $p['ink'] . '; ' . $fonts . ' line-height:1.6; }
  header { padding:48px 24px 24px; max-width:960px; margin:0 auto; ' . $heroAlign . ' }
  .kicker { color:' . $p['accent'] . '; font-size:14px; letter-spacing:0.2em; text-transform:uppercase; margin-bottom:16px; }
  h1 { font-size:clamp(34px,6vw,64px); line-height:1.05; ' . $letter . ' margin-bottom:16px; }
  h1 span { color:' . $p['accent'] . '; }
  .tag { font-size:18px; opacity:0.85; max-width:640px; ' . ($direction === 'c' ? 'margin:0 auto;' : '') . ' }
  .cta { display:inline-block; margin-top:28px; background:' . $p['accent'] . '; color:' . $p['bg'] . '; font-weight:700; padding:14px 30px; border-radius:' . $radius . '; text-decoration:none; }
  section { max-width:960px; margin:0 auto; padding:32px 24px 64px; display:grid; gap:20px; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); }
  .card { border:1px solid ' . $p['accent'] . '33; border-radius:' . $radius . '; padding:24px; background:#ffffff08; }
  .card h3 { color:' . $p['accent'] . '; margin-bottom:8px; }
  .card p { font-size:15px; opacity:0.8; }
  footer { border-top:1px solid #ffffff1a; padding:24px; text-align:center; font-size:14px; opacity:0.7; }
</style>
</head>
<body>
<header>
  <div class="kicker">' . $e($directionName) . '</div>
  <h1>' . $e($businessName) . '<span>.</span></h1>
  <p class="tag">' . $tagline . '</p>
  <a class="cta" href="#contact">Get in touch</a>
</header>
<section>' . $items . '</section>
<footer id="contact">
  ' . ($contactBits !== '' ? $contactBits . '<br>' : '') . $note . '
</footer>
</body>
</html>
';
  }

}
