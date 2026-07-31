<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\famtastic_pipeline\Entity\Intake;
use Drupal\famtastic_pipeline\Entity\Order;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Turns a confirmed prospect + submitted intake into a Site Studio request in
 * two forms: a versioned machine JSON structure and a human-readable brief.
 *
 * This is the FAMtastic Designs → Site Studio handoff. It intentionally makes
 * no assumption about Site Studio's transport (see SiteStudioAdapterInterface).
 */
class SiteStudioRequestBuilder {

  public const SCHEMA_VERSION = '1.0';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Builds the machine-readable request array.
   */
  public function buildJson(Prospect $prospect, ?Intake $intake, ?Order $order): array {
    $pkg = $this->configFactory->get('famtastic_pipeline.settings')->get('package');

    $lines = static function (?string $v): array {
      if (!$v) {
        return [];
      }
      return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $v))));
    };
    $iv = static function (?Intake $i, string $field): ?string {
      return $i && !$i->get($field)->isEmpty() ? (string) $i->get($field)->value : NULL;
    };

    $social = [];
    if (!$prospect->get('social_links')->isEmpty()) {
      $decoded = json_decode((string) $prospect->get('social_links')->value, TRUE);
      $social = is_array($decoded) ? $decoded : $lines($prospect->get('social_links')->value);
    }

    $assets = [];
    if ($intake) {
      foreach ($intake->get('asset_refs')->referencedEntities() as $file) {
        /** @var \Drupal\file\FileInterface $file */
        $assets[] = [
          'type' => 'business_asset',
          'filename' => $file->getFilename(),
          'uri' => $file->getFileUri(),
          'owner_confirmed' => (bool) $intake->get('asset_ownership_confirmed')->value,
        ];
      }
    }

    return [
      'schema_version' => self::SCHEMA_VERSION,
      'project_id' => NULL,
      'customer_id' => (int) $prospect->id(),
      'package' => $order
        ? (string) $order->get('package')->value
        : (is_array($pkg) ? ($pkg['id'] ?? 'essential_199') : 'essential_199'),
      'order' => $order ? [
        'id' => (int) $order->id(),
        'amount' => (int) $order->get('amount')->value,
        'currency' => $order->get('currency')->value,
        'payment_status' => $order->get('payment_status')->value,
      ] : NULL,
      'business' => [
        'name' => $prospect->get('business_name')->value,
        'category' => $prospect->get('business_category')->value,
        'description' => $prospect->get('business_description')->value,
        'address' => $prospect->get('address')->value,
        'service_area' => $prospect->get('service_area')->value,
        'public_contact' => [
          'phone' => $prospect->get('public_phone')->value,
          'email' => $prospect->get('public_email')->value,
          'website' => $prospect->get('website_url')->value,
        ],
        'hours' => $prospect->get('hours')->value,
        'social_links' => $social,
      ],
      'positioning' => [
        'ideal_customer' => $iv($intake, 'ideal_customer'),
        'customer_problem' => $iv($intake, 'customer_problem'),
        'desired_outcome' => $iv($intake, 'desired_outcome'),
        'primary_goal' => $iv($intake, 'primary_goal'),
        'primary_cta' => $iv($intake, 'primary_cta'),
        'secondary_cta' => $iv($intake, 'secondary_cta'),
      ],
      'content' => [
        'services' => $lines($iv($intake, 'services')),
        'about' => $iv($intake, 'about'),
        'differentiators' => $lines($iv($intake, 'differentiators')),
        'credentials' => $lines($iv($intake, 'credentials')),
        'testimonials' => $lines($iv($intake, 'testimonials')),
        'faqs' => $lines($iv($intake, 'faqs')),
        'required_sections' => $lines($iv($intake, 'required_sections')),
        'avoid' => $iv($intake, 'info_to_avoid'),
      ],
      'brand' => [
        'colors' => $iv($intake, 'brand_colors'),
        'style_preferences' => $iv($intake, 'style_preferences'),
        'reference_sites' => $lines($iv($intake, 'reference_sites')),
        'logo_asset' => $assets[0]['filename'] ?? NULL,
        'photos' => array_map(static fn ($a) => $a['filename'], $assets),
      ],
      'assets' => $assets,
      'domain' => [
        'existing_domain' => $iv($intake, 'existing_domain'),
        'registrar' => $iv($intake, 'domain_registrar'),
        'existing_website' => $iv($intake, 'existing_website'),
      ],
      'constraints' => [
        'info_to_avoid' => $iv($intake, 'info_to_avoid'),
        'asset_ownership_confirmed' => $intake ? (bool) $intake->get('asset_ownership_confirmed')->value : FALSE,
      ],
      'approvals' => [
        'customer_approval_status' => 'pending',
      ],
    ];
  }

  /**
   * Renders the human-readable Markdown brief from the JSON structure.
   */
  public function buildBrief(array $json): string {
    $b = $json['business'] ?? [];
    $p = $json['positioning'] ?? [];
    $c = $json['content'] ?? [];
    $br = $json['brand'] ?? [];
    $d = $json['domain'] ?? [];

    $list = static function (array $items): string {
      $items = array_filter($items);
      return $items ? implode("\n", array_map(static fn ($i) => "- $i", $items)) : '_none provided_';
    };
    $val = static fn ($v) => ($v === NULL || $v === '') ? '_none provided_' : $v;

    $out = [];
    $out[] = '# Site Studio Build Request — ' . ($b['name'] ?? 'Business');
    $out[] = '';
    $out[] = 'Package: **' . ($json['package'] ?? 'basic_199') . '** · Schema v' . ($json['schema_version'] ?? self::SCHEMA_VERSION);
    $out[] = '';
    $out[] = '## Business';
    $out[] = '- **Name:** ' . $val($b['name'] ?? NULL);
    $out[] = '- **Category:** ' . $val($b['category'] ?? NULL);
    $out[] = '- **Description:** ' . $val($b['description'] ?? NULL);
    $out[] = '- **Address:** ' . $val($b['address'] ?? NULL);
    $out[] = '- **Service area:** ' . $val($b['service_area'] ?? NULL);
    $out[] = '- **Phone:** ' . $val($b['public_contact']['phone'] ?? NULL);
    $out[] = '- **Email:** ' . $val($b['public_contact']['email'] ?? NULL);
    $out[] = '- **Hours:** ' . $val($b['hours'] ?? NULL);
    $out[] = '';
    $out[] = '## Positioning';
    $out[] = '- **Ideal customer:** ' . $val($p['ideal_customer'] ?? NULL);
    $out[] = '- **Customer problem:** ' . $val($p['customer_problem'] ?? NULL);
    $out[] = '- **Desired outcome:** ' . $val($p['desired_outcome'] ?? NULL);
    $out[] = '- **Primary goal:** ' . $val($p['primary_goal'] ?? NULL);
    $out[] = '- **Primary CTA:** ' . $val($p['primary_cta'] ?? NULL);
    $out[] = '- **Secondary CTA:** ' . $val($p['secondary_cta'] ?? NULL);
    $out[] = '';
    $out[] = '## Services';
    $out[] = $list($c['services'] ?? []);
    $out[] = '';
    $out[] = '## About';
    $out[] = $val($c['about'] ?? NULL);
    $out[] = '';
    $out[] = '## Differentiators';
    $out[] = $list($c['differentiators'] ?? []);
    $out[] = '';
    $out[] = '## Credentials';
    $out[] = $list($c['credentials'] ?? []);
    $out[] = '';
    $out[] = '## Testimonials';
    $out[] = $list($c['testimonials'] ?? []);
    $out[] = '';
    $out[] = '## FAQs';
    $out[] = $list($c['faqs'] ?? []);
    $out[] = '';
    $out[] = '## Required sections';
    $out[] = $list($c['required_sections'] ?? []);
    $out[] = '';
    $out[] = '## Brand & style';
    $out[] = '- **Colors:** ' . $val($br['colors'] ?? NULL);
    $out[] = '- **Style preferences:** ' . $val($br['style_preferences'] ?? NULL);
    $out[] = '- **Reference sites:** ' . (!empty($br['reference_sites']) ? implode(', ', $br['reference_sites']) : '_none provided_');
    $out[] = '';
    $out[] = '## Domain';
    $out[] = '- **Existing domain:** ' . $val($d['existing_domain'] ?? NULL);
    $out[] = '- **Registrar:** ' . $val($d['registrar'] ?? NULL);
    $out[] = '- **Existing website:** ' . $val($d['existing_website'] ?? NULL);
    $out[] = '';
    $out[] = '## Assets';
    $assetLines = array_map(static fn ($a) => $a['filename'] . ($a['owner_confirmed'] ? ' (ownership confirmed)' : ''), $json['assets'] ?? []);
    $out[] = $list($assetLines);
    $out[] = '';
    $out[] = '## Do NOT include';
    $out[] = $val($c['avoid'] ?? NULL);
    $out[] = '';
    return implode("\n", $out);
  }

}
