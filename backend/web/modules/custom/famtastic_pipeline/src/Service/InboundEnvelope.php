<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Bounded RFC822 decoder shared by Drupal discovery and the signed pipe. */
final class InboundEnvelope {
  public static function parse(string $raw, int $receivedAt): array {
    if ($raw === '' || strlen($raw) > 16777216) throw new \InvalidArgumentException('mail_size_invalid');
    [$headers, $body] = self::parts($raw);
    $text = ''; $html = ''; $attachments = [];
    self::decode($headers, $body, $text, $html, $attachments, 0);
    $recipients = [];
    foreach (['envelope-to', 'delivered-to', 'to', 'cc'] as $key) {
      preg_match_all('/[a-z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-z0-9.-]+/i', $headers[$key] ?? '', $matches);
      $recipients = array_merge($recipients, array_map('strtolower', $matches[0]));
    }
    preg_match_all('/<[^<>\s]{1,998}>/', ($headers['in-reply-to'] ?? '') . ' ' . ($headers['references'] ?? ''), $refs);
    $senderHeader = $headers['from'] ?? '';
    if (preg_match('/<([^<>]+)>/', $senderHeader, $senderAddress)) $senderHeader = $senderAddress[1];
    preg_match('/[a-z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-z0-9.-]+/i', $senderHeader, $from);
    return [
      'message_id' => trim($headers['message-id'] ?? ''), 'from' => strtolower($from[0] ?? ''),
      'to' => $recipients[0] ?? '', 'recipients' => array_values(array_unique($recipients)),
      'references' => array_slice(array_values(array_unique($refs[0])), -50),
      'subject' => self::words($headers['subject'] ?? ''), 'body' => $text ?: trim(strip_tags($html)),
      'attachments' => $attachments, 'received_at' => $receivedAt,
    ];
  }
  private static function parts(string $raw): array {
    $parts = preg_split('/\r?\n\r?\n/', ltrim($raw, "\r\n"), 2);
    $headers = [];
    foreach (preg_split('/\r?\n/', preg_replace('/\r?\n[ \t]+/', ' ', $parts[0])) as $line) {
      if (!str_contains($line, ':')) continue;
      [$key, $value] = explode(':', $line, 2);
      $key = strtolower(trim($key));
      // Repeated addressing/reference headers are retained; never override a
      // Message-ID with an attacker-controlled duplicate.
      if (isset($headers[$key]) && in_array($key, ['message-id', 'from'], TRUE)) throw new \InvalidArgumentException('mail_duplicate_identity_header');
      $headers[$key] = isset($headers[$key]) ? $headers[$key] . ' ' . trim($value) : trim($value);
    }
    return [$headers, $parts[1] ?? ''];
  }
  private static function words(string $value): string {
    return function_exists('iconv_mime_decode') ? (iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $value) : $value;
  }
  private static function decode(array $headers, string $body, string &$text, string &$html, array &$attachments, int $depth): void {
    if ($depth > 8) throw new \InvalidArgumentException('mail_mime_depth');
    $type = $headers['content-type'] ?? 'text/plain';
    // MIME boundaries are case-sensitive even though media types are not.
    if (str_starts_with(strtolower($type), 'multipart/')) {
      if (!preg_match('/boundary=(?:"([^"\r\n]+)"|([^;\s]+))/i', $type, $boundary)) throw new \InvalidArgumentException('mail_boundary_missing');
      foreach (array_slice(explode('--' . ($boundary[1] ?: $boundary[2]), $body), 1, 100) as $part) {
        if (str_starts_with($part, '--')) break;
        [$sub, $bytes] = self::parts($part);
        self::decode($sub, $bytes, $text, $html, $attachments, $depth + 1);
      }
      return;
    }
    $encoding = strtolower($headers['content-transfer-encoding'] ?? '');
    $bytes = match ($encoding) {
      'base64' => base64_decode(preg_replace('/\s+/', '', $body), TRUE),
      'quoted-printable' => quoted_printable_decode($body), default => $body,
    };
    if ($bytes === FALSE) throw new \InvalidArgumentException('mail_encoding_invalid');
    $mime = strtolower(trim(explode(';', $type)[0]));
    if (preg_match('/(?:filename|name)=(?:"([^"\r\n]+)"|([^;\s]+))/i', ($headers['content-disposition'] ?? '') . ';' . $type, $filename)) {
      $attachments[] = ['name' => self::words($filename[1] ?: $filename[2]), 'mime' => $mime,
        'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'content_base64' => base64_encode($bytes)];
      if (count($attachments) > 25) throw new \InvalidArgumentException('mail_attachments_limit');
    }
    elseif ($mime === 'text/plain' && $text === '') $text = $bytes;
    elseif ($mime === 'text/html' && $html === '') $html = $bytes;
  }
}
