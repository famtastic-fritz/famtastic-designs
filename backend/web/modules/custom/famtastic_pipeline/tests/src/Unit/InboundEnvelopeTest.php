<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use Drupal\famtastic_pipeline\Service\InboundEnvelope;
use PHPUnit\Framework\TestCase;
final class InboundEnvelopeTest extends TestCase {
  public function testNestedMimePreservesCaseSensitiveBoundariesAndReplyReferences(): void {
    $raw = "Message-ID: <reply@example.test>\r\nFrom: Client <client@example.test>\r\nTo: hello@famtasticdesigns.com\r\nEnvelope-To: support+aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa@famtasticdesigns.com\r\nReferences: <first@example.test>\r\n <second@example.test>\r\nContent-Type: multipart/mixed; boundary=\"OuterCASE\"\r\n\r\n--OuterCASE\r\nContent-Type: multipart/alternative; boundary=InnerCASE\r\n\r\n--InnerCASE\r\nContent-Type: text/plain\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nPlease=20revise.\r\n--InnerCASE--\r\n--OuterCASE\r\nContent-Type: text/plain; name=\"notes.txt\"\r\nContent-Disposition: attachment; filename=\"notes.txt\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . base64_encode('notes') . "\r\n--OuterCASE--\r\n";
    $message = InboundEnvelope::parse($raw, 123);
    self::assertSame('Please revise.', trim($message['body']));
    self::assertSame(['<first@example.test>', '<second@example.test>'], $message['references']);
    self::assertCount(2, $message['recipients']);
    self::assertSame(hash('sha256', 'notes'), $message['attachments'][0]['sha256']);
  }
  public function testRejectsDuplicateIdentityHeaders(): void {
    $this->expectException(\InvalidArgumentException::class);
    InboundEnvelope::parse("Message-ID: <one@example.test>\nMessage-ID: <two@example.test>\n\nbody", 1);
  }
}
