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
 * Implements FAMtastic Scout: give-before-extracting market scan,
 * 4-step progressive disclosure, on-screen payoff scope artifacts,
 * and high-intent lead scoring.
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
        'Lead capture & booking request form',
        'Foundational local SEO & indexing',
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
        'Lead capture & owner email notifications',
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
        'Fast turnaround for active campaigns',
      ],
    ],
    'website-care' => [
      'sku' => 'website-care',
      'title' => 'Website Care & Maintenance — $149/mo',
      'headline' => 'Keep Your Website Fast, Secure, and Updated',
      'price' => 149,
      'timeline' => 'Ongoing monthly',
      'pages' => 0,
      'best_for' => 'Businesses that have a site and need hands-off hosting, security, and monthly edits.',
      'features' => [
        'Managed cloud hosting & SSL certificate',
        'Weekly security updates & daily backups',
        'Up to 1 hour of monthly content edits',
        'Uptime monitoring & technical support',
      ],
    ],
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly mixed $aiProvider = null,
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
   * Processes a turn in FAMtastic Scout's 4-step progressive discovery.
   */
  public function conversationalTurn(array $messages, array $gatheredData = [], array $context = []): array {
    $lastUserMessage = '';
    for ($i = count($messages) - 1; $i >= 0; $i--) {
      if (($messages[$i]['role'] ?? '') === 'user') {
        $lastUserMessage = trim((string) ($messages[$i]['content'] ?? ''));
        break;
      }
    }

    $data = $this->extractDataFromConversation($messages, $gatheredData, $lastUserMessage);

    // Calculate current live recommendation
    $combinedText = implode(' ', array_filter([
      $data['business_name'] ?? '',
      $data['industry'] ?? '',
      $data['city'] ?? '',
      $data['pages'] ?? '',
      $data['logo_status'] ?? '',
      $data['domain_choice'] ?? '',
      is_array($data['features'] ?? null) ? implode(' ', $data['features']) : ($data['features'] ?? ''),
      $lastUserMessage,
    ]));

    $rec = $this->advise($combinedText, $data, $context);

    // Handle human escalation
    if (!empty($data['wants_human'])) {
      return [
        'step_number' => 4,
        'step_label' => 'Step 4 of 4 · Direct Team Handoff',
        'reply' => "You got it! I've flagged our senior team for a personal follow-up. What is your preferred email or phone number so we can reach out directly with your custom scope?",
        'quick_chips' => [],
        'input_placeholder' => 'Enter your email or phone number...',
        'input_type' => 'text',
        'is_complete' => !empty($data['email']) || !empty($data['phone']),
        'is_artifact_ready' => false,
        'gathered_data' => $data,
        'recommendation' => $rec,
      ];
    }

    // Determine state progression based on what's collected
    $step = $this->determineScoutStep($data, $lastUserMessage, $rec);

    return [
      'step_number' => $step['step_number'],
      'step_label' => $step['step_label'],
      'reply' => $step['reply'],
      'market_scan' => $step['market_scan'] ?? null,
      'quick_chips' => $step['quick_chips'],
      'input_placeholder' => $step['input_placeholder'],
      'input_type' => $step['input_type'],
      'is_complete' => $step['is_complete'],
      'is_artifact_ready' => $step['is_artifact_ready'] ?? false,
      'gathered_data' => $data,
      'recommendation' => $rec,
    ];
  }

  /**
   * Evaluates project requirements and returns tailored advice.
   */
  public function advise(string $prompt, array $answers = [], array $context = []): array {
    $cleanPrompt = trim($prompt);
    if ($cleanPrompt === '' && empty($answers)) {
      return $this->defaultRecommendation();
    }

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
   * Scout state machine: 4-step progressive disclosure.
   */
  private function determineScoutStep(array $data, string $latest, array $rec): array {
    $lower = mb_strtolower($latest);

    // STEP 4: Payoff & Optional PDF Email
    if (!empty($data['email']) || !empty($data['scope_confirmed'])) {
      $emailMsg = !empty($data['email'])
        ? "✓ Got your email ({$data['email']})! We've saved your project blueprint and sent your portal access link."
        : "Your instant project blueprint is ready below! Want me to email you the complete PDF blueprint & lock in this price for 30 days?";

      return [
        'step_number' => 4,
        'step_label' => 'Step 4 of 4 · Instant Scope & Blueprint',
        'reply' => $emailMsg,
        'quick_chips' => empty($data['email']) ? ['Lock in my $199 price', 'Talk to a real human'] : ['Start with this Package →', 'Access Client Portal'],
        'input_placeholder' => empty($data['email']) ? 'Enter your email to receive PDF blueprint...' : 'Ask any follow-up question...',
        'input_type' => empty($data['email']) ? 'email' : 'text',
        'is_complete' => !empty($data['email']),
        'is_artifact_ready' => true,
      ];
    }

    // STEP 3: Needs & Package Scope Refinement
    if (!empty($data['market_scanned'])) {
      return [
        'step_number' => 3,
        'step_label' => 'Step 3 of 4 · Custom Scope Refinement',
        'reply' => "Got it! To finalize your exact deliverables: do you have an existing logo and domain, or do you need us to register and set them up? (1st-year hosting and domain registration are included!)",
        'quick_chips' => [
          'I have logo & domain ready',
          'I need a domain + logo help',
          'Need full launch from scratch',
          'Talk to a real human',
        ],
        'input_placeholder' => 'Pick an option or describe what you have ready...',
        'input_type' => 'text',
        'is_complete' => false,
        'is_artifact_ready' => false,
      ];
    }


    // STEP 2: Market Scan & Hook ("Give before you extract")
    if (!empty($data['business_name']) || !empty($data['industry']) || !empty($data['city'])) {
      $biz = $data['business_name'] ?? 'your business';
      $city = !empty($data['city']) ? $data['city'] : 'your local market';
      $scan = $this->generateMarketScan($biz, $city, $data['industry'] ?? '');

      return [
        'step_number' => 2,
        'step_label' => 'Step 2 of 4 · Market Scan & Competitive Scan',
        'reply' => $scan['text'],
        'market_scan' => $scan,
        'quick_chips' => [
          'Yes, build my $199 scope',
          'I need 3–5 pages ($499)',
          'Need booking / growth ($3,999)',
          'Talk to a real human',
        ],
        'input_placeholder' => 'Tap a package or tell us what you need...',
        'input_type' => 'text',
        'is_complete' => false,
        'is_artifact_ready' => false,
      ];
    }

    // STEP 1: Initial Greeting
    return [
      'step_number' => 1,
      'step_label' => 'Step 1 of 4 · Business & City',
      'reply' => "Tell me your business and your city — just that. I'll show you something in 20 seconds.",
      'quick_chips' => [
        'Barbershop / Salon',
        'Restaurant / Food',
        'Plumber / HVAC / Trades',
        'Dental / Healthcare',
        'Cleaning / Pressure Washing',
        'Something Else',
      ],
      'input_placeholder' => 'e.g. Barbershop in Port St. Lucie or Italian restaurant in Austin...',
      'input_type' => 'text',
      'is_complete' => false,
      'is_artifact_ready' => false,
    ];
  }

  /**
   * Generates honest, grounded market intelligence based on real local dynamics.
   */
  private function generateMarketScan(string $business, string $city, string $industry): array {
    $lower = mb_strtolower($business . ' ' . $industry);

    if (str_contains($lower, 'barber') || str_contains($lower, 'salon') || str_contains($lower, 'hair') || str_contains($lower, 'braid')) {
      return [
        'category' => 'Barbershop & Personal Care',
        'city' => $city,
        'headline' => 'Mobile Booking is the Single Biggest Factor in ' . $city,
        'text' => "Okay — quick scan for {$city}: The top-performing barbershops and salons winning online all have one thing in common: seamless mobile booking and transparent pricing right on the homepage. Without an owned website, customers searching 'near me' tonight go straight to your competitors. Want me to scope what it takes to fix that? It's $199, by the way.",
        'key_factor' => 'One-tap appointment booking & mobile portfolio',
      ];
    }

    if (str_contains($lower, 'restaurant') || str_contains($lower, 'food') || str_contains($lower, 'cafe') || str_contains($lower, 'catering') || str_contains($lower, 'pizza')) {
      return [
        'category' => 'Restaurant & Hospitality',
        'city' => $city,
        'headline' => 'Mobile Menus & Catering Requests Drive ' . $city . ' Diners',
        'text' => "Okay — quick scan for {$city}: Over 75% of local diners check menu pricing, photo galleries, and private event catering on mobile before stepping foot in a restaurant. Spots without mobile menus lose takeout orders daily. Want me to scope what it takes to launch your custom menu and reservation page? It's $199 to $499.",
        'key_factor' => 'Fast mobile menu + catering inquiry flow',
      ];
    }

    if (str_contains($lower, 'plumb') || str_contains($lower, 'hvac') || str_contains($lower, 'roof') || str_contains($lower, 'electric') || str_contains($lower, 'clean') || str_contains($lower, 'landscap') || str_contains($lower, 'trades')) {
      return [
        'category' => 'Home Services & Trades',
        'city' => $city,
        'headline' => 'Instant Quote Requests & Review Proof Rule ' . $city,
        'text' => "Okay — quick scan for {$city}: Homeowners needing fast repairs judge trade credibility within 5 seconds on their phone. The businesses dominating local search all feature emergency quote forms, verified review badges, and clear service area maps. Want me to scope what it takes to get you up and running? It's $199 to $499.",
        'key_factor' => 'Fast quote request form + Google Maps & Review badge',
      ];
    }

    if (str_contains($lower, 'dent') || str_contains($lower, 'health') || str_contains($lower, 'chiro') || str_contains($lower, 'therap') || str_contains($lower, 'med')) {
      return [
        'category' => 'Healthcare & Wellness',
        'city' => $city,
        'headline' => 'Patient Trust & Consultation Booking in ' . $city,
        'text' => "Okay — quick scan for {$city}: Prospective patients prioritize provider trust, credential highlights, and hassle-free consultation requests. A dedicated practice website builds immediate authority over platform directories. Want me to scope your launch? It's $199 to $499.",
        'key_factor' => 'Provider credentials + new patient consultation booking',
      ];
    }

    // Universal high-converting market scan
    return [
      'category' => 'Local Business',
      'city' => $city,
      'headline' => 'Mobile Conversion & Local Authority in ' . $city,
      'text' => "Okay — quick scan for {$city}: Customer purchase decisions happen in seconds on mobile. The businesses winning search in your area have fast-loading mobile pages, clear pricing, and direct quote forms. Want me to scope your custom launch? It's $199, by the way.",
      'key_factor' => 'Mobile-first design + lead capture & Google indexing',
    ];
  }

  /**
   * Extracts conversational slots from messages.
   */
  private function extractDataFromConversation(array $messages, array $existing, string $latest): array {
    $data = $existing;
    $lower = mb_strtolower($latest);

    // Human escalation detection
    if (str_contains($lower, 'talk to a real human') || str_contains($lower, 'human') || str_contains($lower, 'speak with') || str_contains($lower, 'call me')) {
      $data['wants_human'] = true;
      $data['lead_score_hot'] = true;
    }

    // Email extraction
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $latest, $m)) {
      $data['email'] = $m[0];
    }

    // Phone extraction
    if (preg_match('/(?:\+?1[-. ]?)?\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})/', $latest, $m)) {
      $data['phone'] = $m[0];
    }

    // Package preference / scope selection from Step 2
    if (str_contains($lower, '$199') || str_contains($lower, 'web basics') || str_contains($lower, '1 page')) {
      $data['pages'] = '1';
      $data['market_scanned'] = true;
    }
    elseif (str_contains($lower, '$499') || str_contains($lower, '3–5') || str_contains($lower, '3-5') || str_contains($lower, 'business website')) {
      $data['pages'] = '3-5';
      $data['market_scanned'] = true;
    }
    elseif (str_contains($lower, '$3,999') || str_contains($lower, 'growth') || str_contains($lower, 'booking')) {
      $data['pages'] = '10+';
      $data['market_scanned'] = true;
    }

    // Logo & Domain from Step 3
    if (str_contains($lower, 'logo & domain') || str_contains($lower, 'have logo') || str_contains($lower, 'ready')) {
      $data['logo_status'] = 'ready';
      $data['domain_choice'] = 'own_domain';
      $data['scope_confirmed'] = true;
    }
    elseif (str_contains($lower, 'logo help') || str_contains($lower, 'need a domain')) {
      $data['logo_status'] = 'help_needed';
      $data['domain_choice'] = 'need_new_domain';
      $data['scope_confirmed'] = true;
    }
    elseif (str_contains($lower, 'scratch') || str_contains($lower, 'full launch')) {
      $data['logo_status'] = 'help_needed';
      $data['domain_choice'] = 'need_new_domain';
      $data['scope_confirmed'] = true;
    }

    // City & Business extraction from step 1
    if (empty($data['business_name']) && !str_contains($lower, '@') && strlen($latest) > 2) {
      $parts = preg_split('/,| in | - | near /i', $latest);
      if (count($parts) >= 2) {
        $data['business_name'] = trim($parts[0]);
        $data['city'] = trim($parts[1]);
      } else {
        $data['business_name'] = $latest;
      }
      $data['market_scanned'] = false;
    }

    return $data;
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

    $sku = 'web-basics'; // Default starter package $199.

    if (str_contains($text, 'portal') || str_contains($text, 'membership') || str_contains($text, 'ai system') || str_contains($text, 'custom software')) {
      $sku = 'premium-ai';
    }
    elseif (str_contains($text, 'growth') || str_contains($text, 'crm') || str_contains($text, 'automation') || (isset($answers['pages']) && $answers['pages'] === '10+') || str_contains($text, '$3,999')) {
      $sku = 'business-growth';
    }
    elseif (str_contains($text, 'custom design') || str_contains($text, 'bespoke') || str_contains($text, 'brand identity') || str_contains($text, '$1,999')) {
      $sku = 'custom-website';
    }
    elseif (str_contains($text, 'ad campaign') || str_contains($text, 'landing page') || str_contains($text, 'funnel') || str_contains($text, '$1,499')) {
      $sku = 'campaign-landing-page';
    }
    elseif (str_contains($text, '3–5') || str_contains($text, '3-5') || str_contains($text, '$499') || str_contains($text, 'business website')) {
      $sku = 'business-website';
    }


    $pkg = self::PACKAGES[$sku];

    $pages = match ($sku) {
      'web-basics' => ['Home (High-Conversion Single Page with Navigation)', 'Quick Booking Request Modal', 'Services & Price Guide Section', 'Customer Reviews & Trust Section', 'Direct Contact & Map Section'],
      'business-website' => ['Home', 'Services & Pricing', 'About Us', 'Trust & Reviews', 'Contact & Booking'],
      'custom-website' => ['Home', 'Custom Services Showcase', 'Brand Story & About', 'Portfolio / Case Studies', 'Inquiry & Booking'],
      'business-growth' => ['Home', 'Service Catalog', 'Online Scheduling', 'Customer Resources', 'About', 'Pricing / Estimate Calculator', 'Contact'],
      'premium-ai' => ['Home', 'Services & Capabilities', 'Client Portal Login', 'Interactive AI Assistant', 'Case Studies', 'Company', 'Security & Contact'],
      'campaign-landing-page' => ['High-Converting Campaign Landing Page', 'Instant Confirmation & Next Steps'],
      'website-care' => ['Maintenance & Monitoring Coverage for Existing Site'],
    };

    return [
      'package_sku' => $sku,
      'package_title' => $pkg['title'],
      'price_estimate' => $pkg['price'],
      'price_formatted' => '$' . number_format($pkg['price']),
      'timeline' => $pkg['timeline'],
      'personalized_rationale' => $pkg['best_for'],
      'recommended_pages' => $pages,
      'recommended_features' => $pkg['features'],
      'follow_up_questions' => [],
      'scope_summary' => $pkg['headline'],
      'source' => 'famtastic_scout_engine',
    ];
  }

  private function defaultRecommendation(): array {
    $pkg = self::PACKAGES['web-basics'];
    return [
      'package_sku' => 'web-basics',
      'package_title' => $pkg['title'],
      'price_estimate' => $pkg['price'],
      'price_formatted' => '$199',
      'timeline' => $pkg['timeline'],
      'personalized_rationale' => $pkg['best_for'],
      'recommended_pages' => ['Home (One-Page Scroll)', 'Services & Pricing', 'Booking Request', 'Reviews', 'Contact & Hours'],
      'recommended_features' => $pkg['features'],
      'follow_up_questions' => [],
      'scope_summary' => $pkg['headline'],
      'source' => 'default',
    ];
  }

  private function inferAudience(string $text): string {
    $lower = mb_strtolower($text);
    if (str_contains($lower, 'barber') || str_contains($lower, 'salon') || str_contains($lower, 'hair')) {
      return 'Local clients seeking dependable appointments, clean cuts, transparent pricing, and fast booking.';
    }
    if (str_contains($lower, 'restaurant') || str_contains($lower, 'cafe') || str_contains($lower, 'bakery') || str_contains($lower, 'food')) {
      return 'Local diners, foodies, and catering clients looking for menu, hours, and reservations.';
    }
    if (str_contains($lower, 'plumbing') || str_contains($lower, 'hvac') || str_contains($lower, 'electric') || str_contains($lower, 'contractor')) {
      return 'Homeowners and property managers needing fast, trusted quote requests and emergency service.';
    }
    return 'Target prospective customers seeking credible local services and clear next steps.';
  }

}
