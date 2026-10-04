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

require_once __DIR__ . '/../src/Service/InboundEnvelope.php';
try {
  $payload = json_encode(\Drupal\famtastic_pipeline\Service\InboundEnvelope::parse($raw, time()), JSON_THROW_ON_ERROR);
} catch (\Throwable) { exit(75); }
$curl = curl_init('https://famtasticdesigns.com/web/api/pipeline/mail/inbound');
curl_setopt_array($curl, [CURLOPT_POST => TRUE, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-FAMtastic-Mail-Signature: ' . hash_hmac('sha256', $payload, $secret)], CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 30]);
$response = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
if ($response === FALSE || $status < 200 || $status >= 300) exit(75);
exit(0);
