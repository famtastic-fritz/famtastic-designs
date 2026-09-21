<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Pure legacy callback validation. No identity, files, DB or delivery authority. */
final class ProofCallbackArtifacts {

  /** Trusted direction/policy inputs; preserve callback coercions and errors. */
  public static function normalize(array $variants, array $requiredDirections, bool $requiresSignedAssets = FALSE): array {
    if (count($variants) !== count($requiredDirections)) {
      throw new \InvalidArgumentException(sprintf('Exactly %d variants are required for this proof set.', count($requiredDirections)));
    }
    $validated = [];
    foreach ($variants as $variant) {
      if (!is_array($variant)) {
        throw new \InvalidArgumentException('Each variant must be an object.');
      }
      $direction = strtolower((string) ($variant['direction_id'] ?? ''));
      $html = (string) ($variant['html'] ?? '');
      if (!array_key_exists($direction, $requiredDirections) || isset($validated[$direction])) {
        throw new \InvalidArgumentException('Variants must contain the unique directions required by this proof set.');
      }
      if ($html === '' || strlen($html) > 500000) {
        throw new \InvalidArgumentException('Each proof HTML artifact is required and limited to 500 KB.');
      }
      if (preg_match('/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i', $html)) {
        throw new \InvalidArgumentException('Proof HTML contains disallowed active content.');
      }
      $assets = ProofAssetContract::normalizeCallbackAssets($variant['assets'] ?? NULL);
      $thumbnail = NULL;
      $thumbnailBase64 = (string) ($variant['thumbnail_base64'] ?? '');
      if ($thumbnailBase64 !== '') {
        $mediaType = strtolower((string) ($variant['thumbnail_media_type'] ?? ''));
        if (!in_array($mediaType, ['image/jpeg', 'image/png'], TRUE)) {
          throw new \InvalidArgumentException('Proof thumbnail must be JPEG or PNG.');
        }
        $thumbnail = base64_decode($thumbnailBase64, TRUE);
        if ($thumbnail === FALSE || strlen($thumbnail) > 1500000) {
          throw new \InvalidArgumentException('Proof thumbnail is invalid or exceeds 1.5 MB.');
        }
        $validSignature = $mediaType === 'image/png'
          ? str_starts_with($thumbnail, "\x89PNG\r\n\x1a\n")
          : str_starts_with($thumbnail, "\xff\xd8\xff");
        if (!$validSignature) {
          throw new \InvalidArgumentException('Proof thumbnail bytes do not match the declared media type.');
        }
      }
      if ($requiresSignedAssets && $assets === []) {
        throw new \InvalidArgumentException('Verified-cold proof directions require at least one signed visual asset.');
      }
      $validated[$direction] = [
        'html' => $html,
        'thumbnail' => $thumbnail,
        'thumbnail_extension' => (($variant['thumbnail_media_type'] ?? '') === 'image/png') ? 'png' : 'jpg',
        'design_dna' => is_array($variant['design_dna'] ?? NULL) ? $variant['design_dna'] : [],
        'assets' => $assets,
      ];
    }
    if (array_keys($validated) !== array_keys($requiredDirections)) {
      ksort($validated);
    }
    if (array_keys($validated) !== array_keys($requiredDirections)) {
      throw new \InvalidArgumentException('Proof directions do not match the requested proof set.');
    }
    return $validated;
  }
}
