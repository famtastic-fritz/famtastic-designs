<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Customer-safe text boundary for public preview rooms and invitations.
 *
 * Public-source research can still contain contact data copied from a listing
 * or model output. This guard is deliberately shared at every public-preview
 * projection so storage/audit fidelity never becomes accidental disclosure.
 */
final class PublicPreviewContentGuard {

  public function redact(mixed $value, int $maximum = 1200): string {
    $text = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value))) ?? '';
    $text = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '[redacted email]', $text) ?? '';
    $text = preg_replace('/(?<!\w)(?:\+?1[ .-]?)?(?:\(?\d{3}\)?[ .-]?)\d{3}[ .-]?\d{4}(?!\w)/', '[redacted phone]', $text) ?? '';
    // Common explicit credentials and bearer values. This is not a claim
    // that a public preview can safely display arbitrary secret-shaped text;
    // it removes the formats a seed, researcher, or copied listing is most
    // likely to carry before that data reaches a public/customer surface.
    $text = preg_replace('/\b(?:api[_ -]?key|secret|token|password|authorization)\s*(?:[:=]|is)\s*(?:Bearer\s+)?[^\s,;]+/i', '[redacted secret]', $text) ?? '';
    $text = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]{12,}\b/i', '[redacted secret]', $text) ?? '';
    $text = preg_replace('/\b(?:AIza[\w-]{20,}|sk-[A-Za-z0-9_-]{16,}|ghp_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{12,})\b/', '[redacted secret]', $text) ?? '';
    return mb_substr($text, 0, max(0, $maximum));
  }

}
