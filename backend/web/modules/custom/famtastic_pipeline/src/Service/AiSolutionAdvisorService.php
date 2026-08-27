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
   * Processes a turn in a guided conversational intake interview.
   */
  public function conversationalTurn(array $messages, array $gatheredData = [], array $context = []): array {
    $lastUserMessage = '';
    for ($i = count($messages) - 1; $i >= 0; $i--) {
      if (($messages[$i]['role'] ?? '') === 'user') {
        $lastUserMessage = trim((string) ($messages[$i]['content'] ?? ''));
        break;
      }
    }

    // Merge and extract data from user input
    $data = $this->extractDataFromConversation($messages, $gatheredData, $lastUserMessage);

    // Calculate live package recommendation based on all gathered data
    $combinedText = implode(' ', array_filter([
      $data['business_name'] ?? '',
      $data['industry'] ?? '',
      $data['business_model'] ?? '',
      $data['setup'] ?? '',
      $data['pages'] ?? '',
      $data['logo_status'] ?? '',
      $data['domain_choice'] ?? '',
      is_array($data['features'] ?? null) ? implode(' ', $data['features']) : ($data['features'] ?? ''),
      $data['reference_sites'] ?? '',
      $lastUserMessage,
    ]));

    $rec = $this->advise($combinedText, $data, $context);

    // Determine the next missing discovery piece
    $step = $this->determineNextDiscoveryStep($data);

    return [
      'reply' => $step['reply'],
      'quick_chips' => $step['quick_chips'],
      'input_placeholder' => $step['input_placeholder'],
      'input_type' => $step['input_type'],
      'is_complete' => $step['is_complete'],
      'gathered_data' => $data,
      'recommendation' => $rec,
    ];
  }

  /**
   * Extracts slots from conversational text.
   */
  private function extractDataFromConversation(array $messages, array $existing, string $latest): array {
    $data = $existing;
    $lower = mb_strtolower($latest);

    // Email extraction
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $latest, $m)) {
      $data['email'] = $m[0];
    }

    // Phone extraction
    if (preg_match('/(?:\+?1[-. ]?)?\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})/', $latest, $m)) {
      $data['phone'] = $m[0];
    }

    // Logo detection
    if (str_contains($lower, 'have a logo') || str_contains($lower, 'logo ready') || str_contains($lower, 'have brand')) {
      $data['logo_status'] = 'ready';
    }
    elseif (str_contains($lower, 'help creating a logo') || str_contains($lower, 'need a logo') || str_contains($lower, 'design a logo')) {
      $data['logo_status'] = 'help_needed';
    }
    elseif (str_contains($lower, 'no logo') || str_contains($lower, 'not needed')) {
      $data['logo_status'] = 'no_logo';
    }

    // Domain detection
    if (str_contains($lower, 'already own') || str_contains($lower, 'have a domain') || str_contains($lower, 'own my domain')) {
      $data['domain_choice'] = 'own_domain';
    }
    elseif (str_contains($lower, 'need a new domain') || str_contains($lower, 'register a domain') || str_contains($lower, 'new domain')) {
      $data['domain_choice'] = 'need_new_domain';
    }

    // Pages & Setup detection
    if (str_contains($lower, '1 page') || str_contains($lower, 'one page') || str_contains($lower, '$199')) {
      $data['pages'] = '1';
      $data['setup'] = 'new';
    }
    elseif (str_contains($lower, '3–5') || str_contains($lower, '3-5') || str_contains($lower, '5 pages') || str_contains($lower, '$499')) {
      $data['pages'] = '3-5';
      $data['setup'] = 'new';
    }
    elseif (str_contains($lower, 'redesign')) {
      $data['setup'] = 'redesign';
      $data['pages'] = '3-5';
    }
    elseif (str_contains($lower, '10+') || str_contains($lower, 'growth system') || str_contains($lower, '$3,999')) {
      $data['pages'] = '10+';
      $data['setup'] = 'growth';
    }

    // Features detection
    $features = (array) ($data['features'] ?? []);
    if (str_contains($lower, 'booking') || str_contains($lower, 'scheduling') || str_contains($lower, 'appointments') || str_contains($lower, 'reservation')) {
      if (!in_array('booking', $features, true)) $features[] = 'booking';
    }
    if (str_contains($lower, 'payment') || str_contains($lower, 'store') || str_contains($lower, 'ecommerce') || str_contains($lower, 'sell online')) {
      if (!in_array('ecommerce', $features, true)) $features[] = 'ecommerce';
    }
    if (str_contains($lower, 'reviews') || str_contains($lower, 'testimonials')) {
      if (!in_array('reviews', $features, true)) $features[] = 'reviews';
    }
    if (str_contains($lower, 'chat') || str_contains($lower, 'bot') || str_contains($lower, 'ai')) {
      if (!in_array('chat', $features, true)) $features[] = 'chat';
    }
    if (!empty($features)) {
      $data['features'] = $features;
    }

    // Business Name & Description
    if (empty($data['business_name']) && !str_contains($lower, '@') && strlen($latest) > 2) {
      if (count($messages) <= 2) {
        $data['business_name'] = $latest;
      }
    }

    return $data;
  }

  /**
   * Determines the next interview question and quick reply chips.
   */
  private function determineNextDiscoveryStep(array $data): array {
    // 0. If email is provided, we can complete immediately
    if (!empty($data['email'])) {
      return [
        'reply' => "Thank you! I've synthesized your full project blueprint and locked in your package quote. You can review the complete scope below, start checkout, or access your free client portal brief!",
        'quick_chips' => [],
        'input_placeholder' => 'Ask any follow-up questions about your proposal...',
        'input_type' => 'text',
        'is_complete' => true,
      ];
    }

    // 1. Business & what they do
    if (empty($data['business_name']) && empty($data['industry'])) {
      return [
        'reply' => "Hi there! I'm FAMtastic's AI Project Advisor. Tell me a bit about your business—what is your business name and what products or services do you offer?",
        'quick_chips' => ['Local Service / Trades', 'Restaurant / Food', 'Healthcare / Wellness', 'Professional Services', 'E-Commerce Store'],
        'input_placeholder' => 'e.g. Bella Cucina — authentic Italian restaurant in Dallas...',
        'input_type' => 'text',
        'is_complete' => false,
      ];
    }


    // 2. Setup & Pages
    if (empty($data['pages']) && empty($data['setup'])) {
      $biz = $data['business_name'] ?? 'your business';
      return [
        'reply' => "Awesome! For {$biz}, are you looking to launch a brand new website or redesign an existing one? How many pages do you think you'll need?",
        'quick_chips' => [
          'Brand new site (1 page / $199)',
          'Brand new site (3–5 pages / $499)',
          'Redesign our existing site',
          'Full Growth System (10+ pages / $3,999)',
        ],
        'input_placeholder' => 'Pick an option or describe your current website setup...',
        'input_type' => 'text',
        'is_complete' => false,
      ];
    }

    // 3. Logo & Brand Assets
    if (empty($data['logo_status'])) {
      return [
        'reply' => "Great choice. What's the status of your logo and branding? Do you already have a logo and brand assets ready, or would you like help creating them?",
        'quick_chips' => [
          'I have a logo & brand ready',
          'I need help creating a logo',
          'No logo needed right now',
        ],
        'input_placeholder' => 'Tell us about your logo or pick a quick option...',
        'input_type' => 'text',
        'is_complete' => false,
      ];
    }

    // 4. Domain & Professional Email
    if (empty($data['domain_choice'])) {
      return [
        'reply' => "Got it. What about your website domain and professional email? (First-year domain registration & managed hosting is included with your package!)",
        'quick_chips' => [
          'I already own my domain',
          'I need a new domain registered',
          'I need help setting up business email',
        ],
        'input_placeholder' => 'e.g. I have mydomain.com or I need a new domain...',
        'input_type' => 'text',
        'is_complete' => false,
      ];
    }

    // 5. Key Features
    if (empty($data['features'])) {
      return [
        'reply' => "What key capabilities or features does your website need to convert visitors into customers?",
        'quick_chips' => [
          'Lead Capture & Quote Form',
          'Online Booking / Scheduling',
          'Online Store / Payments',
          'Customer Reviews & Testimonials',
          'Live AI Chatbot Assistant',
        ],
        'input_placeholder' => 'Select key features or type your custom feature needs...',
        'input_type' => 'text',
        'is_complete' => false,
      ];
    }

    // 6. Contact Email
    if (empty($data['email'])) {
      return [
        'reply' => "Perfect! I have everything needed to prepare your custom project blueprint and package recommendation. Where should I send your formal quote receipt and portal login link?",
        'quick_chips' => [],
        'input_placeholder' => 'Enter your email (e.g. you@yourbusiness.com)...',
        'input_type' => 'email',
        'is_complete' => false,
      ];
    }

    // 7. Complete!
    return [
      'reply' => "Thank you! I've synthesized your full project blueprint and locked in your package quote. You can review the complete scope below, start checkout, or access your free client portal brief!",
      'quick_chips' => [],
      'input_placeholder' => 'Ask any follow-up questions about your proposal...',
      'input_type' => 'text',
      'is_complete' => true,
    ];
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
    $flattened = [];
    foreach ($answers as $val) {
      if (is_array($val)) {
        $flattened[] = implode(' ', array_map('strval', $val));
      }
      elseif (is_scalar($val)) {
        $flattened[] = (string) $val;
      }
    }
    $text = mb_strtolower($prompt . ' ' . implode(' ', $flattened));

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
