<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Controller\ClientMessagesController;
use Drupal\famtastic_pipeline\Controller\CustomerPortalController;
use Drupal\famtastic_pipeline\Service\ClientMessagingService;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\OutreachMailer;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once dirname(__DIR__, 3) . '/src/Service/ClientMessagingService.php';
require_once dirname(__DIR__, 3) . '/src/Service/OutreachMailer.php';
require_once dirname(__DIR__, 3) . '/src/Controller/ClientMessagesController.php';
require_once dirname(__DIR__, 3) . '/src/Controller/CustomerPortalController.php';
require_once dirname(__DIR__, 3) . '/src/Service/CustomerPortalService.php';
require_once dirname(__DIR__, 3) . '/src/Portal/StaffCommandCenterBridge.php';

/** Durable database tests of inbox ownership, retries, and delivery truth. */
#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class ClientMessagingServiceTest extends UnitTestCase {

  private Connection $db;
  private ClientMessagingService $messages;
  private AccountInterface $staff;
  private AccountInterface $customer;
  private AccountInterface $other;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'driver' => 'sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $definitions = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema();
    foreach (['famtastic_portal_thread', 'famtastic_portal_message', 'famtastic_portal_read', 'famtastic_customer', 'famtastic_membership', 'famtastic_notification_outbox', 'famtastic_support_case', 'famtastic_project_request'] as $table) {
      $this->db->schema()->createTable($table, $definitions[$table]);
    }
    $this->db->query('CREATE TABLE famtastic_prospect (id INTEGER PRIMARY KEY, public_email TEXT, business_name TEXT)');
    $this->db->query('CREATE TABLE famtastic_intake (id INTEGER PRIMARY KEY, prospect_ref INTEGER, primary_goal TEXT, about TEXT, submitted_at INTEGER)');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1800000000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $config = $this->getConfigFactoryStub(['famtastic_pipeline.settings' => ['frontend_base_url' => 'https://famtasticdesigns.com', 'notification_to_email' => 'owner@example.test']]);
    $this->messages = new ClientMessagingService($this->db, $time, new Uuid(), $config, $lock);
    $this->staff = $this->account(1, TRUE);
    $this->customer = $this->account(11);
    $this->other = $this->account(22);
    foreach ([[11, 'client@example.test', 101], [22, 'other@example.test', 202]] as [$uid, $email, $organization]) {
      $this->db->insert('famtastic_customer')->fields(['id' => $uid, 'public_id' => (new Uuid())->generate(), 'uid' => $uid, 'display_name' => 'Client ' . $uid, 'email' => $email, 'verified_at' => 1799999999, 'created' => 1799999999, 'changed' => 1799999999])->execute();
      $this->db->insert('famtastic_membership')->fields(['organization_id' => $organization, 'customer_id' => $uid, 'role' => 'owner', 'status' => 'active', 'created' => 1799999999, 'changed' => 1799999999])->execute();
    }
    $this->contact(18, 'client@example.test', 'Where are the proofs?');
    $this->contact(19, 'other@example.test', 'An unrelated business message.');
  }

  private function account(int $uid, bool $staff = FALSE): AccountProxyInterface {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('isAuthenticated')->willReturn($uid > 0);
    $account->method('getDisplayName')->willReturn($staff ? 'FAMtastic owner' : 'Client');
    $account->method('getEmail')->willReturn($staff ? 'owner@example.test' : 'client@example.test');
    $account->method('hasPermission')->willReturnCallback(static fn(string $permission): bool => $staff && $permission === 'administer famtastic pipeline');
    return $account;
  }

  private function contact(int $id, string $email, string $message): void {
    $this->db->insert('famtastic_prospect')->fields(['id' => $id, 'public_email' => $email, 'business_name' => 'Business ' . $id])->execute();
    $this->db->insert('famtastic_intake')->fields(['id' => $id, 'prospect_ref' => $id, 'primary_goal' => 'Public contact request', 'about' => 'CONTACT request' . "\n" . json_encode(['name' => 'Contact ' . $id, 'email' => $email, 'subject' => 'Website help ' . $id, 'message' => $message]), 'submitted_at' => 1799999000 + $id])->execute();
  }

  private function imported(): string { return $this->messages->importIntake(18); }

  private function rowCount(string $table): int { return (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField(); }

  public function testOriginalContactAppearsOnceAndImportDoesNotSendAnything(): void {
    $id = $this->imported();
    self::assertSame($id, $this->imported());
    self::assertSame(1, $this->rowCount('famtastic_portal_thread'));
    self::assertSame(1, $this->rowCount('famtastic_portal_message'));
    self::assertSame(0, $this->rowCount('famtastic_notification_outbox'));
    $inbox = $this->messages->inbox($this->staff);
    self::assertTrue($inbox['is_staff']);
    self::assertSame(1, $inbox['unread_count']);
    self::assertSame(1, $this->messages->unreadCount($this->staff));
    self::assertSame(1, $inbox['needs_reply_count']);
    self::assertSame('Where are the proofs?', $inbox['threads'][0]['last_message_preview']);
    self::assertSame('contact_form', $inbox['threads'][0]['source']);
    self::assertSame('/web/admin/famtastic/intake/18/edit', $inbox['threads'][0]['context']['admin_intake_url']);
  }

  public function testExactVerifiedEmailClaimsContactButOtherAccountCannotReadReplyOrMarkIt(): void {
    $id = $this->imported();
    $this->messages->importIntake(19);
    self::assertCount(1, $this->messages->inbox($this->customer)['threads']);
    self::assertSame(101, (int) $this->db->select('famtastic_portal_thread', 't')->fields('t', ['organization_id'])->condition('public_id', $id)->execute()->fetchField());
    self::assertCount(1, $this->messages->inbox($this->other)['threads']);
    foreach (['detail', 'reply', 'read'] as $action) {
      try {
        match ($action) {
          'detail' => $this->messages->detail($this->other, $id),
          'reply' => $this->messages->reply($this->other, $id, 'No access', 'client-message-attempt-01'),
          'read' => $this->messages->markRead($this->other, $id, 1),
        };
        self::fail('Another customer accessed this conversation.');
      }
      catch (\RuntimeException $error) { self::assertSame('Conversation not found.', $error->getMessage()); }
    }
    self::assertSame(0, $this->rowCount('famtastic_notification_outbox'));
  }

  public function testUnverifiedAndAnonymousAccountsCannotClaimOrReadMessages(): void {
    $this->imported();
    $this->db->update('famtastic_customer')->fields(['verified_at' => NULL])->condition('uid', 11)->execute();
    foreach ([$this->customer, $this->account(0)] as $account) {
      try { $this->messages->inbox($account); self::fail('Unauthorized inbox access.'); }
      catch (\UnexpectedValueException) {}
    }
    self::assertSame(0, (int) $this->db->select('famtastic_portal_thread', 't')->fields('t', ['organization_id'])->execute()->fetchField());
  }

  public function testReadIsSeparateFromNeedsReplyAndCannotSwallowNewerMessage(): void {
    $id = $this->imported();
    $loaded = $this->messages->detail($this->staff, $id)['thread']['last_message_id'];
    $this->messages->reply($this->customer, $id, 'Here is another detail.', 'customer-message-0001');
    $this->messages->markRead($this->staff, $id, $loaded);
    $inbox = $this->messages->inbox($this->staff);
    self::assertSame(1, $inbox['unread_count']);
    self::assertSame(1, $inbox['needs_reply_count']);
    $latest = $inbox['threads'][0]['last_message_id'];
    $this->messages->markRead($this->staff, $id, $latest);
    $this->messages->markRead($this->staff, $id, $loaded);
    self::assertSame(0, $this->messages->inbox($this->staff)['unread_count']);
    self::assertSame(0, $this->messages->unreadCount($this->staff));
    self::assertSame(1, $this->messages->inbox($this->staff)['needs_reply_count']);
  }

  public function testReadMarkerFromAnotherConversationIsRejected(): void {
    $id = $this->imported();
    $other = $this->messages->importIntake(19);
    $foreign = $this->messages->detail($this->staff, $other)['thread']['last_message_id'];
    $this->expectException(\InvalidArgumentException::class);
    $this->messages->markRead($this->staff, $id, $foreign);
  }

  public function testStaffReplyIsAtomicIdempotentAndMailReceiptStaysHonest(): void {
    $id = $this->imported();
    $reply = $this->messages->reply($this->staff, $id, 'Your proof review is being prepared.', 'staff-message-0001');
    self::assertSame('queued', $reply['delivery_status']);
    self::assertTrue($this->messages->reply($this->staff, $id, 'Your proof review is being prepared.', 'staff-message-0001')['duplicate']);
    self::assertSame(2, $this->rowCount('famtastic_portal_message'));
    self::assertSame(1, $this->rowCount('famtastic_notification_outbox'));
    $outbox = $this->db->select('famtastic_notification_outbox', 'n')->fields('n')->execute()->fetchAssoc();
    self::assertSame('client@example.test', $outbox['recipient']);
    self::assertSame('customer_message_reply', $outbox['template_id']);
    self::assertSame(0, $this->messages->inbox($this->staff)['needs_reply_count']);
    self::assertSame(1, $this->messages->inbox($this->customer)['unread_count']);
    self::assertSame('queued', $this->messages->detail($this->customer, $id)['messages'][1]['delivery_status']);
    $this->db->update('famtastic_notification_outbox')->fields(['status' => 'sent', 'provider_message_id' => 'smtp-receipt-1'])->execute();
    self::assertSame('sent', $this->messages->detail($this->staff, $id)['messages'][1]['delivery_status']);
    self::assertSame('smtp-receipt-1', $this->messages->detail($this->staff, $id)['messages'][1]['provider_message_id']);
    self::assertArrayNotHasKey('provider_message_id', $this->messages->detail($this->customer, $id)['messages'][1]);
    $this->db->update('famtastic_notification_outbox')->fields(['status' => 'dead_letter'])->execute();
    self::assertSame('failed', $this->messages->detail($this->staff, $id)['messages'][1]['delivery_status']);
  }

  public function testDuplicateRequestCannotChangeReplyText(): void {
    $id = $this->imported();
    $this->messages->reply($this->staff, $id, 'Original reply', 'staff-message-0001');
    try { $this->messages->reply($this->staff, $id, 'Different reply', 'staff-message-0001'); self::fail('Idempotency collision was accepted.'); }
    catch (\InvalidArgumentException) {}
    self::assertSame(2, $this->rowCount('famtastic_portal_message'));
  }

  public function testOutboxFailureRollsBackReplyAndStatus(): void {
    $id = $this->imported();
    $this->db->query("CREATE TRIGGER reject_outbox BEFORE INSERT ON famtastic_notification_outbox BEGIN SELECT RAISE(FAIL, 'outbox unavailable'); END", [], ['allow_delimiter_in_query' => TRUE]);
    try { $this->messages->reply($this->staff, $id, 'Never partially save this reply', 'staff-message-0001'); self::fail('Expected failure.'); }
    catch (\Throwable $error) { self::assertStringContainsString('outbox unavailable', $error->getMessage()); }
    self::assertSame(1, $this->rowCount('famtastic_portal_message'));
    self::assertSame(0, $this->rowCount('famtastic_notification_outbox'));
    self::assertSame(1, $this->messages->inbox($this->staff)['needs_reply_count']);
  }

  public function testFiltersAndPrivateApiErrors(): void {
    $this->imported();
    $this->messages->importIntake(19);
    self::assertCount(1, $this->messages->inbox($this->staff, ['q' => 'proofs'])['threads']);
    $controller = new ClientMessagesController($this->messages, $this->account(0));
    $response = $controller->inbox(new Request());
    self::assertSame(401, $response->getStatusCode());
    self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $controller = new ClientMessagesController($this->messages, $this->account(1, TRUE));
    self::assertSame(422, $controller->reply(new Request(content: '{"body":[]}'), $this->imported())->getStatusCode());
    self::assertSame(200, $controller->inbox(new Request(['q' => ['unexpected'], 'status' => ['unexpected']]))->getStatusCode());
  }

  public function testArchivedDuplicateCannotReplaceLiveProjectContext(): void {
    $id = $this->imported();
    foreach ([[12, 'submitted', NULL, 100], [13, 'archived', NULL, 200], [14, 'submitted', 210, 300]] as [$requestId, $status, $archived, $changed]) {
      $this->db->insert('famtastic_project_request')->fields([
        'id' => $requestId, 'public_id' => (new Uuid())->generate(), 'organization_id' => 101, 'customer_id' => 11,
        'prospect_id' => 18, 'project_name' => 'Website ' . $requestId, 'status' => $status, 'customer_archived_at' => $archived,
        'proof_review_status' => $requestId === 12 ? 'approved' : 'not_started', 'intake_data' => '{}', 'created' => 10, 'changed' => $changed,
      ])->execute();
    }
    $context = $this->messages->detail($this->staff, $id)['thread']['context'];
    self::assertSame(12, $context['request_id']);
    self::assertSame('approved', $context['proof_status']);
    self::assertSame('/web/admin/famtastic/website-request/12/proof-review', $context['admin_request_url']);
  }

  public function testClosedConversationsAreExcludedFromWaitingFilter(): void {
    $id = $this->imported();
    $this->messages->reply($this->staff, $id, 'Closing after the answer', 'staff-message-closed-01');
    self::assertCount(1, $this->messages->inbox($this->staff, ['status' => 'waiting'])['threads']);
    $this->db->update('famtastic_portal_thread')->fields(['status' => 'closed'])->condition('public_id', $id)->execute();
    self::assertCount(0, $this->messages->inbox($this->staff, ['status' => 'waiting'])['threads']);
  }

  public function testStaffOnlySessionNeedsNoInventedCustomerRecord(): void {
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $this->db);
    $controller = (new \ReflectionClass(CustomerPortalController::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($controller, 'portal'))->setValue($controller, $portal);
    (new \ReflectionProperty($controller, 'account'))->setValue($controller, $this->staff);
    $response = $controller->session(new Request());
    $payload = json_decode($response->getContent(), TRUE);
    self::assertSame(200, $response->getStatusCode());
    self::assertTrue($payload['can_manage_messages']);
    self::assertSame('FAMtastic owner', $payload['staff']['display_name']);
    self::assertSame('owner@example.test', $payload['staff']['email']);
    self::assertNull($payload['customer']);
    self::assertSame([], $payload['organizations']);
    self::assertSame(2, $this->rowCount('famtastic_customer'));
    self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
  }

  public function testAdminFilterControlsSurviveTwigRenderingAndEscapeInput(): void {
    $controller = new ClientMessagesController($this->messages, $this->account(1, TRUE));
    $build = $controller->admin(new Request(['q' => '\"><script>alert(1)</script>']));
    self::assertSame('inline_template', $build['filters']['#type']);
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), ['autoescape' => 'html']);
    $html = $twig->createTemplate($build['filters']['#template'])->render($build['filters']['#context']);
    self::assertStringContainsString('<input type="search"', $html);
    self::assertStringContainsString('<select name="status">', $html);
    self::assertStringContainsString('<button type="submit">', $html);
    self::assertStringNotContainsString('<script>', $html);
  }

  public function testAllMessageWritesRequireCsrfAndStaffPagesRequirePermission(): void {
    $routes = \Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    foreach (['famtastic_pipeline.client_message_reply', 'famtastic_pipeline.client_message_read'] as $name) {
      self::assertSame(['POST'], $routes[$name]['methods']);
      self::assertSame('TRUE', $routes[$name]['requirements']['_csrf_request_header_token']);
    }
    foreach (['famtastic_pipeline.client_messages_admin', 'famtastic_pipeline.client_messages_admin_thread'] as $name) self::assertSame('administer famtastic pipeline', $routes[$name]['requirements']['_permission']);
  }

  public function testReplyEmailKeepsItsConversationCtaEvenWhenReplyContainsAnotherUrl(): void {
    $mailer = (new \ReflectionClass(OutreachMailer::class))->newInstanceWithoutConstructor();
    $render = new \ReflectionMethod($mailer, 'renderHtmlMessage');
    $url = 'https://famtasticdesigns.com/portal?section=messages&thread=00000000-0000-4000-8000-000000000018';
    $body = "See https://example.test/reference for context.\n\nOpen your workspace:\n{$url}\n\nSign in with the email address that received this message to continue the conversation.";
    $html = $render->invoke($mailer, 'Reply about your website', $body, 'customer_message_reply');
    self::assertStringContainsString('href="' . htmlspecialchars($url, ENT_QUOTES) . '"', $html);
    self::assertStringNotContainsString('href="https://example.test/reference"', $html);
    self::assertStringContainsString('See https://example.test/reference for context.', $html);
    self::assertStringContainsString('FAMtastic Concierge', $html);
    self::assertTrue(OutreachMailer::supportsTemplate('customer_message_reply', 1));
  }

}
