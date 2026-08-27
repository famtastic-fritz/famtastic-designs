<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Intelligent solution advisor and brief synthesizer powered by Drupal AI.
 *
 * Evaluates natural-language customer requirements against FAMtastic's exact
 * 16-SKU package ladder and outputs structured recommendations with zero-downtime
 * deterministic fallback.
 */
class AiSolutionAdvisorService {

  public const PACKAGES = [
    'web-basics' => [
      'sku' => 'web-basics',
      'title' => 'Web Basics Bundle — $199',
      'headline' => 'Get a Professional Website for $199',
      'price' => 199,
      'timeline' => '3–5 business days after intake',
      'pages' => 1,
      'best_for' => 'Businesses that need a clean, credible first website on one focused page with hosting and domain included.',
      'features' => [
        'One focused high-conversion business page',
        'Mobile-responsive design',
        'Lead capture form with owner email alerts',
        'Foundational search & indexing setup',
        'First-year managed hosting included',
        'Domain registration or connection',
      ],
    ],
    'business-website' => [
      'sku' => 'business-website',
      'title' => 'Business Website Bundle — $499',
      'headline' => 'Launch or Upgrade a Business Website for $499',
      'price' => 499,
      'timeline' => '5–10 business days after intake',
      'pages' => 5,
      'best_for' => 'Local businesses, services, and storefronts that need 3–5 dedicated pages for services, trust, FAQ, and lead capture.',
      'features' => [
        'Up to 5 standard business pages',
        'Mobile-first responsive layout',
        'Lead capture & owner notifications',
        'On-page local SEO foundations',
        'Google Analytics (GA4) connection',
        'Two consolidated revision rounds',
        'First-year managed hosting & domain included',
      ],
    ],
    'custom-website' => [
      'sku' => 'custom-website',
      'title' => 'Custom Website — $1,999',
      'headline' => 'A Fully Custom Website Foundation',
      'price' => 1999,
      'timeline' => '2–3 weeks',
      'pages' => 5,
      'best_for' => 'Brands requiring distinctive visual styling, bespoke information architecture, and deeper design discovery.',
      'features' => [
        'Up to 5 fully custom page designs',
        'Discovery & content architecture',
        'Original visual design system',
        'Lead capture & conversion tracking',
        'Foundational SEO implementation',
        'Two dedicated revision rounds',
        'Complete training and handoff',
      ],
    ],
    'business-growth' => [
      'sku' => 'business-growth',
      'title' => 'Business Growth System — $3,999',
      'headline' => 'Business Website + Connected Growth Systems',
      'price' => 3999,
      'timeline' => '3–4 weeks',
      'pages' => 10,
      'best_for' => 'Growing companies needing website lead capture connected to scheduling, CRM, marketing automation, and business reporting.',
      'features' => [
        'Comprehensive multi-page custom website',
        'Online booking / scheduling integration',
        'Automated lead routing & CRM sync',
        'Dual-grain UTM attribution & analytics',
        'Custom customer intake workflows',
        'Site Studio export bundle ready',
      ],
    ],
    'premium-ai' => [
      'sku' => 'premium-ai',
      'title' => 'Premium Website + AI System — $6,999',
      'headline' => 'Premium Website, Portal, Automation, and AI',
      'price' => 6999,
      'timeline' => '4–6 weeks',
      'pages' => 15,
      'best_for' => 'Established businesses wanting a high-end web presence with client portal, custom workflows, and governed AI assistance.',
      'features' => [
        'Enterprise-grade custom design & architecture',
        'Custom client portal / members dashboard',
        'Governed AI chatbot & automation workflow',
        'Stripe payment & subscription workflows',
        'Advanced analytics & SLA monitoring',
        'Dedicated onboarding & priority support',
      ],
    ],
    'campaign-landing-page' => [
      'sku' => 'campaign-landing-page',
      'title' => 'Campaign Landing Page System — $1,499',
      'headline' => 'A Complete Paid-Campaign Landing System',
      'price' => 1499,
      'timeline' => '5–7 business days',
      'pages' => 1,
      'best_for' => 'Businesses running Google/Meta ads needing a high-converting landing destination with A/B testing and attribution tracking.',
      'features' => [
        'High-converting single-offer campaign page',
        'Dedicated thank-you & conversion experience',
        'UTM & conversion-event attribution',
        'Lead routing to owner phone & email',
        'A/B-test-ready architecture',
        '14 days post-launch optimization support',
      ],
    ],
    'website-care' => [
      'sku' => 'website-care',
      'title' => 'Website Care & Maintenance — $149/month',
      'headline' => 'Ongoing Website Care & Maintenance',
      'price' => 149,
      'timeline' => 'Immediate activation',
      'pages' => 0,
      'best_for' => 'Businesses with an existing site wanting hands-off security, backups, monitoring, and monthly content updates.',
      'features' => [
        'Fast managed cloud hosting',
        'Automated daily backups & security scans',
        'SSL certificate management',
        'Monthly content update allowance (1 hr)',
        'Uptime monitoring & priority support',
      ],
    ],
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly ?object $aiProvider = null,
  ) {}

  public static function create(ContainerInterface $container): static {
    $aiProvider = $container->has('ai.provider') ? $container->get('ai.provider') : null;
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $aiProvider,
    );
  }

  /**
   * Evaluates project requirements and returns tailored advice.
   */
  public function advise(string $prompt, array $answers = [], array $context = []): array {
    $cleanPrompt = trim($prompt);
    if ($cleanPrompt === '' && empty($answers)) {
      return $this->defaultRecommendation();
    }

    // Attempt Drupal AI LLM generation if available.
    if ($this->aiProvider !== null) {
      try {
        $aiResult = $this->queryDrupalAi($cleanPrompt, $answers, $context);
        if ($aiResult !== null) {
          return $aiResult;
        }
      }
      catch (\Throwable $e) {
        $this->loggerFactory->get('famtastic_ai')->warning('Drupal AI query failed; using deterministic engine: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // Fail-safe high-accuracy deterministic engine.
    return $this->deterministicAdvise($cleanPrompt, $answers);
  }

  /**
   * Synthesizes a structured project brief from raw intake notes.
   */
  public function synthesizeBrief(array $intakeData): array {
    $name = $intakeData['project_name'] ?? $intakeData['business_name'] ?? 'New Project';
    $model = $intakeData['business_model'] ?? '';
    $features = $intakeData['required_features'] ?? '';
    $notes = $intakeData['notes'] ?? '';

    $combined = trim("Business: {$name}\nModel: {$model}\nFeatures: {$features}\nNotes: {$notes}");
    $advice = $this->advise($combined, $intakeData);

    return [
      'executive_summary' => sprintf('%s is seeking a modern web solution focused on %s.', $name, $advice['personalized_rationale'] ?: 'expanding their digital reach and customer acquisition'),
      'recommended_package' => $advice['package_title'],
      'package_sku' => $advice['package_sku'],
      'price_estimate' => $advice['price_formatted'],
      'target_audience' => $this->inferAudience($combined),
      'site_architecture' => $advice['recommended_pages'],
      'key_features' => $advice['recommended_features'],
      'scope_summary' => $advice['scope_summary'],
      'estimated_timeline' => $advice['timeline'],
    ];
  }

  /**
   * Queries Drupal AI provider.
   */
  private function queryDrupalAi(string $prompt, array $answers, array $context): ?array {
    if (!method_exists($this->aiProvider, 'hasDefaultProvider') || !$this->aiProvider->hasDefaultProvider('chat')) {
      return null;
    }

    $provider = $this->aiProvider->getDefaultProviderForOperationType('chat');
    $modelId = $this->aiProvider->getDefaultModelForOperationType('chat');
    if (!$provider || !$modelId) {
      return null;
    }

    $systemPrompt = <<<PROMPT
You are FAMtastic Designs' AI Project Advisor.
FAMtastic builds high-converting, professional websites and digital systems for small-to-midsize businesses.

OUR EXACT PACKAGE LADDER (Prices are strictly fixed):
1. web-basics ($199): 1 focused page, 1st-yr hosting, domain connection. Best for new businesses, solo operators, or simple 1-page presence.
2. business-website ($499): Up to 5 pages (Home, Services, About, Trust/FAQ, Contact), SEO, GA4, lead capture, 1st-yr hosting. Best for local businesses, service pros, restaurants, trades.
3. custom-website ($1,999): Up to 5 custom-designed pages, deep brand discovery, custom visual design, conversion tracking. Best for distinctive brands needing bespoke visual identity.
4. business-growth ($3,999): Broader site (up to 10 pages) + online booking, lead automation, CRM integration, analytics.
5. premium-ai ($6,999): Full digital system (up to 15 pages), client portal, custom workflows, AI assistant.
6. campaign-landing-page ($1,499): High-converting single-offer landing page for paid ads with UTM tracking and A/B test setup.
7. website-care ($149/mo): Monthly hosting, backups, maintenance, 1 hr updates.

INSTRUCTIONS:
Evaluate the user's business description and answers.
Select the BEST MATCHING package SKU from the list above.
Respond ONLY with valid JSON matching this exact schema:
{
  "package_sku": "business-website",
  "personalized_rationale": "2-3 sentences directly addressing their industry, why this specific tier solves their needs without overspending.",
  "recommended_pages": ["Home", "Services", "About Us", "FAQ", "Contact & Booking"],
  "recommended_features": ["Lead capture form", "Foundational local SEO", "Mobile-first responsive design", "Google Analytics setup"],
  "follow_up_questions": [
    {
      "id": "clarify_1",
      "question": "A concise clarifying question that could impact scope (e.g. online booking vs contact form)?",
      "options": ["Option A", "Option B"]
    }
  ],
  "scope_summary": "1 sentence summarizing the core deliverable."
}
PROMPT;

    $userContent = "Customer Business Description: " . ($prompt ?: "Guided questionnaire") . "\nAnswers: " . Json::encode($answers);
    $messages = [
      ['role' => 'system', 'content' => $systemPrompt],
      ['role' => 'user', 'content' => $userContent],
    ];

    $response = $provider->chat($messages, $modelId);
    $text = $response->getNormalized()->getText();

    $jsonStart = strpos($text, '{');
    $jsonEnd = strrpos($text, '}');
    if ($jsonStart !== false && $jsonEnd !== false) {
      $parsed = Json::decode(substr($text, $jsonStart, $jsonEnd - $jsonStart + 1));
      if (is_array($parsed) && isset($parsed['package_sku']) && isset(self::PACKAGES[$parsed['package_sku']])) {
        $sku = $parsed['package_sku'];
        $pkg = self::PACKAGES[$sku];
        return [
          'package_sku' => $sku,
          'package_title' => $pkg['title'],
          'price_estimate' => $pkg['price'],
          'price_formatted' => '$' . number_format($pkg['price']),
          'timeline' => $pkg['timeline'],
          'personalized_rationale' => $parsed['personalized_rationale'] ?? $pkg['best_for'],
          'recommended_pages' => $parsed['recommended_pages'] ?? ['Home', 'Services', 'About', 'Contact'],
          'recommended_features' => $parsed['recommended_features'] ?? $pkg['features'],
          'follow_up_questions' => $parsed['follow_up_questions'] ?? [],
          'scope_summary' => $parsed['scope_summary'] ?? $pkg['headline'],
          'source' => 'drupal_ai',
        ];
      }
    }

    return null;
  }

  /**
   * Deterministic rule engine fallback.
   */
  private function deterministicAdvise(string $prompt, array $answers): array {
    $text = mb_strtolower($prompt . ' ' . implode(' ', array_map('strval', $answers)));

    $sku = 'business-website'; // Default recommendation.

    // Keywords matching
    if (str_contains($text, 'portal') || str_contains($text, 'membership') || str_contains($text, 'ai system') || str_contains($text, 'custom software')) {
      $sku = 'premium-ai';
    }
    elseif (str_contains($text, 'booking') || str_contains($text, 'crm') || str_contains($text, 'automation') || str_contains($text, 'growth') || (isset($answers['pages']) && $answers['pages'] === '10+')) {
      $sku = 'business-growth';
    }
    elseif (str_contains($text, 'custom design') || str_contains($text, 'bespoke') || str_contains($text, 'brand identity') || str_contains($text, 'high end')) {
      $sku = 'custom-website';
    }
    elseif (str_contains($text, 'ad campaign') || str_contains($text, 'landing page') || str_contains($text, 'funnel') || str_contains($text, 'google ads')) {
      $sku = 'campaign-landing-page';
    }
    elseif (str_contains($text, 'maintenance') || str_contains($text, 'care plan') || str_contains($text, 'hosting only') || str_contains($text, 'update my existing site')) {
      $sku = 'website-care';
    }
    elseif (str_contains($text, '1 page') || (isset($answers['pages']) && $answers['pages'] === '1') || str_contains($text, 'simple') || str_contains($text, 'starter') || str_contains($text, 'basic')) {
      $sku = 'web-basics';
    }

    $pkg = self::PACKAGES[$sku];

    $pages = match ($sku) {
      'web-basics' => ['Home (One-Page Scroll with Navigation)'],
      'business-website' => ['Home', 'Services', 'About Us', 'Trust & Reviews', 'Contact & Quote'],
      'custom-website' => ['Home', 'Custom Services Showcase', 'Brand Story & About', 'Portfolio / Case Studies', 'Inquiry & Booking'],
      'business-growth' => ['Home', 'Service Catalog', 'Online Scheduling', 'Customer Resources', 'About', 'Pricing / Estimate Calculator', 'Contact'],
      'premium-ai' => ['Home', 'Services & Capabilities', 'Client Portal Login', 'Interactive AI Assistant', 'Case Studies', 'Company', 'Security & Contact'],
      'campaign-landing-page' => ['High-Converting Campaign Landing Page', 'Instant Confirmation & Next Steps'],
      'website-care' => ['Maintenance & Monitoring Coverage for Existing Site'],
    };

    $followUps = [];
    if ($sku === 'business-website') {
      $followUps[] = [
        'id' => 'booking_needed',
        'question' => 'Will you need direct online booking / scheduling, or is a quote inquiry form sufficient?',
        'options' => ['Quote inquiry form (Included)', 'Direct online booking (+Growth System)'],
      ];
    }
    elseif ($sku === 'web-basics') {
      $followUps[] = [
        'id' => 'expand_pages',
        'question' => 'Can your core offer be explained on one focused page, or do you have multiple distinct services?',
        'options' => ['One page is perfect ($199)', 'Need multiple service pages ($499)'],
      ];
    }

    return [
      'package_sku' => $sku,
      'package_title' => $pkg['title'],
      'price_estimate' => $pkg['price'],
      'price_formatted' => '$' . number_format($pkg['price']),
      'timeline' => $pkg['timeline'],
      'personalized_rationale' => $pkg['best_for'],
      'recommended_pages' => $pages,
      'recommended_features' => $pkg['features'],
      'follow_up_questions' => $followUps,
      'scope_summary' => $pkg['headline'],
      'source' => 'deterministic_engine',
    ];
  }

  private function defaultRecommendation(): array {
    $pkg = self::PACKAGES['business-website'];
    return [
      'package_sku' => 'business-website',
      'package_title' => $pkg['title'],
      'price_estimate' => $pkg['price'],
      'price_formatted' => '$499',
      'timeline' => $pkg['timeline'],
      'personalized_rationale' => $pkg['best_for'],
      'recommended_pages' => ['Home', 'Services', 'About Us', 'Reviews', 'Contact'],
      'recommended_features' => $pkg['features'],
      'follow_up_questions' => [
        [
          'id' => 'project_type',
          'question' => 'What type of business are you launching or upgrading?',
          'options' => ['Local Service / Trades', 'Restaurant / Hospitality', 'Professional Services', 'E-Commerce / Store'],
        ],
      ],
      'scope_summary' => 'Our most popular all-in-one business launch package.',
      'source' => 'default',
    ];
  }

  private function inferAudience(string $text): string {
    $lower = mb_strtolower($text);
    if (str_contains($lower, 'restaurant') || str_contains($lower, 'cafe') || str_contains($lower, 'bakery')) {
      return 'Local diners, foodies, and catering clients looking for menu, hours, and reservations.';
    }
    if (str_contains($lower, 'plumbing') || str_contains($lower, 'hvac') || str_contains($lower, 'electric') || str_contains($lower, 'contractor')) {
      return 'Homeowners and property managers needing fast, trusted quote requests and emergency service.';
    }
    if (str_contains($lower, 'dental') || str_contains($lower, 'doctor') || str_contains($lower, 'therapy') || str_contains($lower, 'wellness')) {
      return 'Patients seeking compassionate care, provider trust, credentials, and easy consultation booking.';
    }
    if (str_contains($lower, 'accounting') || str_contains($lower, 'legal') || str_contains($lower, 'consulting')) {
      return 'Business owners and executives seeking authoritative advice, security, and proven expertise.';
    }
    return 'Target prospective customers seeking credible services and clear next steps.';
  }

}
