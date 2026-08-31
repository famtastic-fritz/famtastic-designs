<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Uuid\UuidInterface;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Owns private, answer-at-a-time discovery invitations before account claim.
 *
 * A raw invitation secret is never persisted. The public page keeps it in the
 * URL fragment and sends it in a request body/header, preventing referrer and
 * server access-log disclosure.
 */
final class DeepDiveInvitationService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Creates a private draft invitation. Sending remains a separate action.
   *
   * @return array{record:array,secret:string}
   */
  public function create(string $email, string $businessName, string $contactName = ''): array {
    $email = $this->email($email);
    if ($email === '') {
      throw new \InvalidArgumentException('A valid recipient email is required.');
    }
    $businessName = trim($businessName) ?: 'Website project';
    $now = $this->time->getRequestTime();
    $prospect = $this->prospectFor($email, $businessName, $contactName, $now);
    $secret = bin2hex(random_bytes(32));
    $id = (int) $this->database->insert('famtastic_deep_dive_invitation')->fields([
      'public_id' => $this->uuid->generate(),
      'prospect_id' => (int) $prospect->id(),
      'customer_id' => NULL,
      'website_request_id' => NULL,
      'email' => $email,
      'business_name' => $businessName,
      'contact_name' => trim($contactName),
      'secret_hash' => hash('sha256', $secret),
      'status' => 'draft',
      'answers' => json_encode(['business_name' => $businessName], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $record = $this->recordById($id);
    if (!$record) {
      throw new \RuntimeException('The deep-dive invitation could not be created.');
    }
    return ['record' => $record, 'secret' => $secret];
  }

  /** Marks a named invitation available to its bearer. */
  public function activate(string $publicId): array {
    $record = $this->record($publicId);
    if (!$record) {
      throw new \InvalidArgumentException('Invitation not found.');
    }
    if (in_array($record['status'], ['revoked', 'claimed'], TRUE)) {
      throw new \InvalidArgumentException('This invitation cannot be activated.');
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_deep_dive_invitation')->fields([
      'status' => 'active',
      'activated_at' => (int) $record['activated_at'] ?: $now,
      'changed' => $now,
    ])->condition('id', (int) $record['id'])->execute();
    return $this->record($publicId) ?? $record;
  }

  /** Records an SMTP/provider acceptance receipt after an explicit send. */
  public function markSent(string $publicId, string $providerMessageId): void {
    $record = $this->record($publicId);
    if (!$record || $record['status'] !== 'active') {
      throw new \InvalidArgumentException('Only an active invitation can be sent.');
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_deep_dive_invitation')->fields([
      'sent_at' => $now,
      'provider_message_id' => mb_substr($providerMessageId, 0, 255),
      'changed' => $now,
    ])->condition('id', (int) $record['id'])->execute();
  }

  /**
   * Returns only the active question and safe progress for a bearer.
   */
  public function view(string $publicId, string $secret): array {
    $record = $this->authorize($publicId, $secret);
    $answers = $this->answers($record);
    $question = $this->nextQuestion($answers);
    return [
      'invitation' => [
        'public_id' => (string) $record['public_id'],
        'business_name' => (string) $record['business_name'],
        'status' => (string) $record['status'],
        'completed' => $question === NULL,
        'progress' => $this->progress($answers),
      ],
      'question' => $question,
    ];
  }

  /** Saves exactly the next expected answer, making progress resumable. */
  public function answer(string $publicId, string $secret, string $key, mixed $answer): array {
    $record = $this->authorize($publicId, $secret);
    $answers = $this->answers($record);
    $question = $this->nextQuestion($answers);
    if ($question === NULL) {
      throw new \InvalidArgumentException('This deep dive is already complete.');
    }
    if (!hash_equals((string) $question['key'], $key)) {
      throw new \InvalidArgumentException('Please answer the current question before moving on.');
    }
    $answers[$key] = $this->validateAnswer($question, $answer);
    $next = $this->nextQuestion($answers);
    $now = $this->time->getRequestTime();
    $fields = [
      'answers' => json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'status' => $next === NULL ? 'completed' : 'active',
      'completed_at' => $next === NULL ? $now : NULL,
      'changed' => $now,
    ];
    $this->database->update('famtastic_deep_dive_invitation')->fields($fields)
      ->condition('id', (int) $record['id'])->execute();
    return $this->view($publicId, $secret);
  }

  /**
   * Validates an exact-email completed invitation before registration begins.
   */
  public function beginRegistration(string $email, string $continuation): ?array {
    if ($continuation === '') {
      return NULL;
    }
    [$publicId, $secret] = array_pad(explode('.', $continuation, 2), 2, '');
    $record = $this->authorize($publicId, $secret, TRUE);
    if (!$record || !hash_equals($this->email($email), (string) $record['email'])) {
      throw new \InvalidArgumentException('This interview link belongs to a different email address.');
    }
    if (!in_array((string) $record['status'], ['completed', 'registration_pending'], TRUE)) {
      throw new \InvalidArgumentException('Finish the interview before creating the connected account.');
    }
    return $record;
  }

  /** Holds a completed interview for the account verification event. */
  public function attachPendingCustomer(string $publicId, int $customerId): void {
    $record = $this->record($publicId);
    if (!$record || !in_array((string) $record['status'], ['completed', 'registration_pending'], TRUE)) {
      throw new \InvalidArgumentException('The completed interview is unavailable for account connection.');
    }
    $this->database->update('famtastic_deep_dive_invitation')->fields([
      'customer_id' => $customerId,
      'status' => 'registration_pending',
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', (int) $record['id'])->execute();
  }

  /** Claims all pending interviews after the matching email is verified. */
  public function claimForVerifiedCustomer(int $customerId, string $email): array {
    $email = $this->email($email);
    if ($email === '') {
      return [];
    }
    $rows = $this->database->select('famtastic_deep_dive_invitation', 'i')->fields('i')
      ->condition('email', $email)
      // Claimed records with no attached request are deliberately retried. This
      // keeps a transient failure between account verification and draft creation
      // from silently losing the client's completed interview.
      ->condition('status', ['completed', 'registration_pending', 'claimed'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!$rows) {
      return [];
    }
    $now = $this->time->getRequestTime();
    $claimed = [];
    foreach ($rows as $row) {
      if (!empty($row['customer_id']) && (int) $row['customer_id'] !== $customerId) {
        continue;
      }
      if ((string) $row['status'] !== 'claimed') {
        $this->database->update('famtastic_deep_dive_invitation')->fields([
          'customer_id' => $customerId,
          'status' => 'claimed',
          'claimed_at' => $now,
          'changed' => $now,
        ])->condition('id', (int) $row['id'])
          ->condition('status', ['completed', 'registration_pending'], 'IN')->execute();
      }
      $claimed[] = $this->recordById((int) $row['id']) ?? $row;
    }
    return $claimed;
  }

  /** Links the durable account-owned request after it has been created. */
  public function attachWebsiteRequest(int $invitationId, int $websiteRequestId): void {
    $this->database->update('famtastic_deep_dive_invitation')->fields([
      'website_request_id' => $websiteRequestId,
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', $invitationId)->execute();
  }

  /** Generates a shareable link with the bearer secret intentionally in hash. */
  public function publicUrl(array $record, string $secret, string $origin): string {
    return rtrim($origin, '/') . '/deep-dive/' . rawurlencode((string) $record['public_id']) . '#' . $secret;
  }

  /** Draft-only transactional copy; no commercial promise is made. */
  public function emailDraft(array $record, string $url): array {
    $name = trim((string) $record['contact_name']) ?: 'there';
    $business = trim((string) $record['business_name']) ?: 'your business';
    return [
      'subject' => 'A few questions to shape ' . $business . "'s website",
      'body' => "Hi {$name},\n\nWe want to understand {$business} before we design anything. This private interview asks one question at a time about your services, booking, brand, location, photos, payments, and growth goals. It takes about 10 minutes, saves your progress, and does not ask for payment details.\n\nStart your private planning interview:\n{$url}\n\nAfter you finish and verify your free FAMtastic account with this same email address, your answers will be saved in your private workspace. We will then prepare a six-direction creative plan for owner review; no site, payment flow, or booking change goes live without your approval.\n\n— FAMtastic Designs",
    ];
  }

  /** @return array<int,array<string,mixed>> */
  public static function questions(): array {
    return [
      ['key' => 'business_name', 'title' => 'What name should appear on your website?', 'help' => 'Use the exact business name clients recognize.', 'type' => 'text', 'required' => TRUE],
      ['key' => 'service_specialties', 'title' => 'Which loc services do you most want to be booked for?', 'help' => 'Include specialties, add-ons, and any services you do not offer.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'ideal_clients', 'title' => 'Who is the best-fit client for you right now?', 'help' => 'Think about their hair stage, needs, location, and what they value.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'primary_sales_goal', 'title' => 'What would make this website a win first?', 'type' => 'choice', 'options' => ['more_new_clients' => 'More new clients', 'more_retightening_rebooks' => 'More retightening rebooks', 'higher_value_services' => 'More higher-value services', 'fewer_booking_questions' => 'Fewer booking questions and better-prepared clients', 'brand_confidence' => 'A stronger, more professional brand'], 'required' => TRUE],
      ['key' => 'sales_baseline', 'title' => 'What do you want to improve about the way clients find and choose you?', 'help' => 'Examples: fewer empty slots, more rebooks, clearer service choices, or better local visibility. Estimates are welcome; exact sales numbers are optional.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'service_area', 'title' => 'Where do you serve clients?', 'help' => 'Share your city, neighborhood, travel radius, or salon name. Do not include a home address unless you intend to publish it.', 'type' => 'text', 'required' => TRUE],
      ['key' => 'hours_and_availability', 'title' => 'What are your normal hours and booking boundaries?', 'help' => 'Include days off, lead time, late policies, or dates you do not accept clients.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'booking_path', 'title' => 'How should clients book while we build?', 'type' => 'choice', 'options' => ['booksy_bridge' => 'Keep Booksy as the booking link', 'request_to_book' => 'Let clients request a time for you to confirm', 'compare_first' => 'Help me compare both options first'], 'required' => TRUE],
      ['key' => 'booksy_url', 'title' => 'What is your current Booksy or booking link?', 'help' => 'Paste the public link only. We will not attempt to sign in, scrape clients, or change your Booksy account.', 'type' => 'url', 'required' => TRUE],
      ['key' => 'booking_friction', 'title' => 'Where do booking questions or missed opportunities happen today?', 'help' => 'Tell us about service selection, availability, preparation, cancellations, deposits, or double-booking concerns.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'payment_display', 'title' => 'What payment path should the site explain?', 'type' => 'choice', 'options' => ['booksy_only' => 'Keep payment inside Booksy', 'existing_payment_qr' => 'Display my existing payment-provider QR', 'deposits_need_review' => 'I need a deposit/payment recommendation first', 'no_public_payment' => 'Do not show a payment path yet'], 'required' => TRUE],
      ['key' => 'payment_notes', 'title' => 'What should clients know before they pay or book?', 'help' => 'Share public policy wording or a future QR destination. Never enter card, bank, Booksy, or merchant-login details.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'brand_start', 'title' => 'What brand material do you already have?', 'type' => 'choice', 'options' => ['logo_and_brand' => 'Logo and brand colors are ready', 'logo_only' => 'I have a logo but need color/style help', 'photos_only' => 'I have photos but no finished brand system', 'start_fresh' => 'I want to build it from scratch'], 'required' => TRUE],
      ['key' => 'brand_colors', 'title' => 'Which colors, materials, or moods feel like your business?', 'help' => 'Describe colors, texture, lighting, clothing, spaces, or references. “Not sure” is a valid answer.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'colors_to_avoid', 'title' => 'What visual directions should we avoid?', 'help' => 'Colors, styles, stereotypes, symbols, or anything that does not feel like you.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'creative_intensity', 'title' => 'How FAMtastic should the visual direction feel?', 'type' => 'choice', 'options' => ['3' => 'Polished and familiar', '5' => 'Balanced and distinct', '7' => 'Bold and editorial', '10' => 'Maximum FAMtastic showpiece'], 'required' => TRUE],
      ['key' => 'reference_links', 'title' => 'Which websites, Instagram pages, or images inspire you?', 'help' => 'Paste public links or describe what you like. You will be able to upload owned photos in your portal after verification.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'portfolio_story', 'title' => 'Which transformations or client stories best show your work?', 'help' => 'Tell us what you are proud of. Do not share client information you do not have permission to publish.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'service_menu_status', 'title' => 'Is your service menu ready to mirror accurately?', 'type' => 'choice', 'options' => ['ready' => 'Yes, services, durations, and prices are current', 'needs_cleanup' => 'Mostly, but I need help simplifying it', 'not_ready' => 'No, I need help deciding what to show'], 'required' => TRUE],
      ['key' => 'policies', 'title' => 'What preparation, late, cancellation, or no-show policies must clients understand?', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'google_business_status', 'title' => 'What is the status of your Google Business Profile?', 'type' => 'choice', 'options' => ['verified_current' => 'Verified and current', 'needs_update' => 'It exists but needs updates', 'not_sure' => 'I am not sure', 'not_created' => 'I do not have one yet'], 'required' => TRUE],
      ['key' => 'local_competitors', 'title' => 'Who do clients compare you with locally?', 'help' => 'Share names or links so we can research positioning—not copy them.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'reviews_and_proof', 'title' => 'What approved reviews, testimonials, or proof can we use?', 'help' => 'Fresh, genuine customer feedback only. We will never scrape or republish Booksy reviews without authorization.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'content_growth', 'title' => 'Which questions do clients ask before booking?', 'help' => 'Your answers become ideas for helpful pages, FAQs, blogs, and social content—not automatic posts.', 'type' => 'textarea', 'required' => TRUE],
      ['key' => 'research_consent', 'title' => 'May FAMtastic research your public business presence and local search opportunity?', 'type' => 'choice', 'options' => ['yes' => 'Yes, research public information only', 'no' => 'No, use only what I provide'], 'required' => TRUE],
      ['key' => 'asset_and_ai_consent', 'title' => 'When you upload materials later, can we use only the files you confirm you own or may use for the project?', 'type' => 'choice', 'options' => ['yes' => 'Yes, with my per-file confirmation', 'no' => 'No AI-assisted visual work from my uploaded files'], 'required' => TRUE],
    ];
  }

  private function authorize(string $publicId, string $secret, bool $allowInactive = FALSE): ?array {
    if (!preg_match('/^[0-9a-f-]{36}$/', $publicId) || !preg_match('/^[a-f0-9]{64}$/', $secret)) {
      if ($allowInactive) {
        throw new \InvalidArgumentException('This interview link is invalid.');
      }
      throw new \InvalidArgumentException('This interview link is invalid.');
    }
    $record = $this->record($publicId);
    if (!$record || !hash_equals((string) $record['secret_hash'], hash('sha256', $secret))) {
      throw new \InvalidArgumentException('This interview link is invalid.');
    }
    if (!$allowInactive && !in_array((string) $record['status'], ['active', 'completed', 'registration_pending'], TRUE)) {
      throw new \InvalidArgumentException('This interview link is not available.');
    }
    return $record;
  }

  private function validateAnswer(array $question, mixed $answer): string {
    $value = trim(is_scalar($answer) ? (string) $answer : '');
    if ($value === '') {
      throw new \InvalidArgumentException('Please provide an answer before continuing.');
    }
    if (($question['type'] ?? '') === 'choice') {
      $options = (array) ($question['options'] ?? []);
      if (!array_key_exists($value, $options)) {
        throw new \InvalidArgumentException('Choose one of the available answers.');
      }
    }
    if (($question['type'] ?? '') === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException('Enter a complete public web address, including https://.');
    }
    if (mb_strlen($value) > 3000) {
      throw new \InvalidArgumentException('Please keep this answer under 3,000 characters.');
    }
    return $value;
  }

  private function nextQuestion(array $answers): ?array {
    foreach (self::questions() as $question) {
      if (empty($answers[$question['key']])) {
        return $question;
      }
    }
    return NULL;
  }

  private function progress(array $answers): array {
    $questions = self::questions();
    $complete = 0;
    foreach ($questions as $question) {
      if (!empty($answers[$question['key']])) {
        $complete++;
      }
    }
    return ['complete' => $complete, 'total' => count($questions)];
  }

  private function answers(array $record): array {
    $answers = json_decode((string) ($record['answers'] ?? ''), TRUE);
    return is_array($answers) ? $answers : [];
  }

  private function record(string $publicId): ?array {
    return $this->database->select('famtastic_deep_dive_invitation', 'i')->fields('i')
      ->condition('public_id', $publicId)->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  private function recordById(int $id): ?array {
    return $this->database->select('famtastic_deep_dive_invitation', 'i')->fields('i')
      ->condition('id', $id)->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  private function prospectFor(string $email, string $businessName, string $contactName, int $now): Prospect {
    $storage = $this->entities->getStorage('famtastic_prospect');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('public_email', $email)->range(0, 1)->execute();
    if ($ids) {
      /** @var \Drupal\famtastic_pipeline\Entity\Prospect $prospect */
      $prospect = $storage->load((int) reset($ids));
      return $prospect;
    }
    /** @var \Drupal\famtastic_pipeline\Entity\Prospect $prospect */
    $prospect = $storage->create([
      'business_name' => $businessName,
      'public_email' => $email,
      'contact_name' => $contactName,
      'contact_method' => 'email',
      'contact_value' => $email,
      'campaign' => 'owner_invited_deep_dive',
      'source' => 'owner_invited_deep_dive',
      'authorized' => TRUE,
      'confirmed_at' => $now,
      'status' => 'new',
      'owner_uid' => 1,
    ]);
    $prospect->save();
    return $prospect;
  }

  private function email(string $email): string {
    $email = mb_strtolower(trim($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
  }

}
