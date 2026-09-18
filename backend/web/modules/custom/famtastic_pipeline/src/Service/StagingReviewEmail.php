<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Pure presentation for customer_staging_review_ready/v1; never sends mail. */
final class StagingReviewEmail {

  public static function render(string $subject, string $body, string $logoUrl, bool $localPreview = FALSE): string {
    // The terminal, system-authored destination is the only actionable link.
    if (!preg_match('~\n\nReview your staging site:\n(https://[^\s]+)\s*$~D', $body, $match)) {
      throw new \InvalidArgumentException('staging_review_destination_missing');
    }
    $url = $match[1];
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
      || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.famtasticinc\.com$/D', $parts['host'] ?? '')
      || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
      || isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '/') !== '/') {
      throw new \InvalidArgumentException('staging_review_destination_invalid');
    }
    // Local assets are permitted only by the explicit non-sending preview API.
    if (!($localPreview && $logoUrl === './assets/famtastic-designs-logo-v1.png')
      && !preg_match('~^https://(?:www\.)?famtasticdesigns\.com/[a-zA-Z0-9/_-]+\.png$~D', $logoUrl)) {
      throw new \InvalidArgumentException('staging_review_hosted_logo_required');
    }
    $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = $escape($subject);
    $message = substr($body, 0, -strlen($match[0]));
    $paragraphs = '';
    foreach (preg_split('/\R{2,}/', trim($message)) ?: [] as $paragraph) {
      $safe = $escape($paragraph);
      $safe = str_replace('The Signal Room', '<strong>The Signal Room</strong>', $safe);
      $paragraphs .= '<p style="margin:0 0 20px;">' . nl2br($safe, FALSE) . '</p>';
    }
    return BrandedEmail::render(
      html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $paragraphs, $logoUrl,
      'Your selected direction is taking shape.', 'YOUR WEBSITE / STAGING REVIEW',
      $url, 'REVIEW YOUR STAGING SITE →',
      html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '. No payment is due at this review stage.', $localPreview,
    );
  }
}
