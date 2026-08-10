#!/usr/bin/env php
<?php

declare(strict_types=1);

// cPanel pipe adapter: raw RFC822 on stdin -> signed, bounded Drupal envelope.
$raw = stream_get_contents(STDIN, 16777217);
if ($raw === FALSE || strlen($raw) < 1 || strlen($raw) > 16777216) exit(75);
$home = (string) getenv('HOME');
$secretPath = $home . '/.famtastic/inbound-mail-secret';
$secret = is_file($secretPath) ? trim((string) file_get_contents($secretPath)) : '';
if (strlen($secret) < 32) exit(78);

$split = preg_split("/\r?\n\r?\n/", $raw, 2);
$headerText = preg_replace("/\r?\n[ \t]+/", ' ', $split[0] ?? '');
$bodyRaw = $split[1] ?? '';
$headers = [];
foreach (preg_split('/\r?\n/', (string) $headerText) as $line) {
  if (!str_contains($line, ':')) continue;
  [$name, $value] = explode(':', $line, 2);
  $headers[strtolower(trim($name))] = trim($value);
}
$address = static function (string $value): string {
  if (preg_match('/<([^>]+)>/', $value, $m)) return strtolower(trim($m[1]));
  return strtolower(trim(explode(',', $value)[0]));
};
$decode = static function (string $value): string {
  return function_exists('iconv_mime_decode') ? (string) iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : $value;
};
$text = '';
$attachments = [];
$contentType = strtolower($headers['content-type'] ?? 'text/plain');
if (preg_match('/boundary="?([^";]+)"?/', $contentType, $boundary)) {
  foreach (explode('--' . $boundary[1], $bodyRaw) as $part) {
    $pieces = preg_split("/\r?\n\r?\n/", ltrim($part, "\r\n"), 2);
    if (count($pieces) !== 2) continue;
    $partHeaders = [];
    foreach (preg_split('/\r?\n/', preg_replace("/\r?\n[ \t]+/", ' ', $pieces[0])) as $line) {
      if (!str_contains($line, ':')) continue;
      [$name, $value] = explode(':', $line, 2); $partHeaders[strtolower(trim($name))] = trim($value);
    }
    $partBody = preg_replace('/\r?\n--$/', '', $pieces[1]);
    $encoding = strtolower($partHeaders['content-transfer-encoding'] ?? '');
    $bytes = $encoding === 'base64' ? base64_decode(preg_replace('/\s+/', '', $partBody), TRUE) : ($encoding === 'quoted-printable' ? quoted_printable_decode($partBody) : $partBody);
    if ($bytes === FALSE) continue;
    $mime = strtolower(trim(explode(';', $partHeaders['content-type'] ?? 'text/plain')[0]));
    $disposition = $partHeaders['content-disposition'] ?? '';
    if (preg_match('/filename="?([^";]+)"?/i', $disposition, $filename)) {
      $attachments[] = ['name' => $decode($filename[1]), 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'content_base64' => base64_encode($bytes)];
    }
    elseif ($text === '' && $mime === 'text/plain') $text = $bytes;
  }
}
else {
  $encoding = strtolower($headers['content-transfer-encoding'] ?? '');
  $text = $encoding === 'base64' ? (string) base64_decode(preg_replace('/\s+/', '', $bodyRaw), TRUE) : ($encoding === 'quoted-printable' ? quoted_printable_decode($bodyRaw) : $bodyRaw);
}
$payload = json_encode([
  'message_id' => $headers['message-id'] ?? '', 'from' => $address($headers['from'] ?? ''), 'to' => $address($headers['to'] ?? ''),
  'subject' => $decode($headers['subject'] ?? ''), 'body' => $text, 'attachments' => $attachments, 'received_at' => time(),
], JSON_THROW_ON_ERROR);
$curl = curl_init('https://famtasticdesigns.com/web/api/pipeline/mail/inbound');
curl_setopt_array($curl, [CURLOPT_POST => TRUE, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-FAMtastic-Mail-Signature: ' . hash_hmac('sha256', $payload, $secret)], CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 30]);
$response = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
if ($response === FALSE || $status < 200 || $status >= 300) exit(75);
exit(0);
