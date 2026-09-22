<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Inlines verified resources so an opaque-origin sandbox needs no auth tokens. */
final class FullSiteReviewRenderer {

  public static function csp(string $nonce): string {
    return "default-src 'none'; base-uri 'none'; object-src 'none'; connect-src 'none'; form-action 'none'; frame-src 'none'; worker-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox; frame-ancestors 'self'";
  }

  /** The reader callback rechecks the request owner and file hash on each read. */
  public static function render(string $html, string $page, string $publicId, array $files, callable $read, string $nonce, ?callable $url = NULL): string {
    $url ??= static fn(string $path): string => FullSiteReviewPackage::url($publicId, $path);
    // libxml's HTML serializer entity-encodes non-ASCII script/style text.
    // Preserve verified raw-text bodies after serialization, without permitting
    // a closing tag to escape its own element.
    $rawBodies = [];
    $raw = static function (string $tag, string $body) use (&$rawBodies, $nonce): string {
      if (stripos($body, '</' . $tag) !== FALSE) throw new \RuntimeException('Review raw text cannot contain its HTML closing tag.');
      $token = 'FULL_SITE_RAW_' . hash('sha256', $nonce . ':' . count($rawBodies) . ':' . $body);
      $rawBodies[$token] = $body;
      return $token;
    };
    $manifest = array_column($files, NULL, 'path');
    $data = static function (string $path) use ($manifest, $read): string {
      $file = $manifest[$path] ?? NULL;
      if (!$file || $file['role'] !== 'asset' || (!str_starts_with($file['media_type'], 'image/') && !str_starts_with($file['media_type'], 'font/'))) throw new \RuntimeException('Undeclared review media.');
      return 'data:' . $file['media_type'] . ';base64,' . base64_encode($read($path));
    };
    $css = static function (string $text, string $from) use ($data): string {
      if (preg_match('/@import\b/i', $text)) throw new \RuntimeException('Review CSS must be bundled locally.');
      return preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/is', static function (array $match) use ($from, $data): string {
        $value = trim($match[2]);
        if (str_starts_with($value, '#')) return $match[0];
        return 'url("' . $data(self::resolve($from, $value)) . '")';
      }, $text) ?? throw new \RuntimeException('Invalid review CSS.');
    };
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    try {
      if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new \RuntimeException('Invalid review HTML.');
    }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $elements = iterator_to_array($document->getElementsByTagName('*'));
    $deferred = [];
    foreach ($elements as $element) {
      if (!$element instanceof \DOMElement || !$element->parentNode) continue;
      $tag = strtolower($element->tagName);
      if (in_array($tag, ['base', 'iframe', 'object', 'embed', 'foreignobject', 'audio', 'video', 'source'], TRUE)
        || ($tag === 'meta' && strtolower($element->getAttribute('http-equiv')) === 'refresh')) {
        $element->parentNode->removeChild($element); continue;
      }
      foreach (iterator_to_array($element->attributes) as $attribute) {
        if (str_starts_with(strtolower($attribute->name), 'on') || in_array(strtolower($attribute->name), ['srcdoc', 'srcset', 'ping', 'formaction'], TRUE)) $element->removeAttribute($attribute->name);
      }
      if ($element->hasAttribute('style')) $element->setAttribute('style', $css($element->getAttribute('style'), $page));
      if ($tag === 'style') $element->textContent = $raw('style', $css($element->textContent, $page));
      if ($tag === 'img') {
        if (!$element->hasAttribute('src')) continue;
        $element->setAttribute('src', $data(self::resolve($page, $element->getAttribute('src'))));
      }
      elseif ($tag === 'link') {
        $rel = strtolower($element->getAttribute('rel'));
        if ($rel === 'stylesheet') {
          $path = self::resolve($page, $element->getAttribute('href'));
          if (($manifest[$path]['media_type'] ?? '') !== 'text/css') throw new \RuntimeException('Undeclared review stylesheet.');
          $style = $document->createElement('style');
          $style->appendChild($document->createTextNode($raw('style', $css($read($path), $path))));
          $element->parentNode->replaceChild($style, $element);
        }
        elseif (in_array($rel, ['icon', 'shortcut icon'], TRUE)) $element->setAttribute('href', $data(self::resolve($page, $element->getAttribute('href'))));
        else $element->parentNode->removeChild($element);
      }
      elseif ($tag === 'script') {
        $type = strtolower($element->getAttribute('type'));
        if (!in_array($type, ['', 'text/javascript', 'application/javascript', 'application/ld+json'], TRUE)) throw new \RuntimeException('Review scripts must be bundled classic JavaScript.');
        if ($element->hasAttribute('src')) {
          if ($element->hasAttribute('async')) throw new \RuntimeException('Review scripts must use deterministic classic script order.');
          if ($element->hasAttribute('defer')) $deferred[] = $element;
          $path = self::resolve($page, $element->getAttribute('src'));
          if (($manifest[$path]['media_type'] ?? '') !== 'text/javascript') throw new \RuntimeException('Undeclared review script.');
          $script = $read($path);
          $element->removeAttribute('src');
          $element->removeAttribute('defer');
          $element->textContent = $script;
        }
        $element->textContent = $raw('script', $element->textContent);
        $element->setAttribute('nonce', $nonce);
      }
      elseif ($tag === 'a' && $element->hasAttribute('href')) {
        $href = trim($element->getAttribute('href'));
        if ($href === '' || str_starts_with($href, '#')) continue;
        if (preg_match('~^(https://|mailto:|tel:)~i', $href) && !preg_match('/[\x00-\x20]/', $href)) {
          $element->setAttribute('target', '_blank');
          $element->setAttribute('rel', 'noopener noreferrer');
        }
        else {
          $path = self::resolve($page, $href);
          if (!in_array($manifest[$path]['role'] ?? '', ['page', 'document'], TRUE)) throw new \RuntimeException('Undeclared review destination.');
          $fragment = parse_url($href, PHP_URL_FRAGMENT);
          $query = parse_url($href, PHP_URL_QUERY);
          if (is_string($query) && (strlen($query) > 2048 || preg_match('/[\x00-\x20\x7f]|%(?![0-9a-f]{2})|%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $query))) throw new \RuntimeException('Invalid review navigation query.');
          $element->setAttribute('href', $url($path) . (is_string($query) && $query !== '' ? '?' . $query : '') . ($fragment !== NULL && $fragment !== FALSE ? '#' . rawurlencode($fragment) : ''));
          $element->setAttribute('target', '_self');
        }
      }
      elseif ($element->hasAttribute('href') || $element->hasAttribute('xlink:href')) {
        foreach (['href', 'xlink:href'] as $attribute) {
          if ($element->hasAttribute($attribute) && !str_starts_with($element->getAttribute($attribute), '#')) $element->removeAttribute($attribute);
        }
      }
      if ($tag === 'form') { $element->setAttribute('action', '#'); $element->removeAttribute('target'); }
    }
    // Inline scripts ignore defer. Place verified deferred scripts after the
    // authored body, preserving their order and their DOM-ready execution.
    $body = $document->getElementsByTagName('body')->item(0);
    foreach ($deferred as $script) {
      if (!$body) throw new \RuntimeException('Deferred review scripts require an HTML body.');
      $body->appendChild($script);
    }
    foreach (iterator_to_array($document->childNodes) as $node) if ($node instanceof \DOMProcessingInstruction) $document->removeChild($node);
    $serialized = $document->saveHTML() ?: throw new \RuntimeException('Review HTML rendering failed.');
    return strtr($serialized, $rawBodies);
  }

  /** Resolves authored local references; reader request paths remain stricter. */
  private static function resolve(string $from, string $reference): string {
    $reference = trim($reference);
    if ($reference === '' || preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $reference) || str_contains($reference, '\\')) throw new \RuntimeException('Review resources must be local declared files.');
    $path = preg_split('/[?#]/', $reference, 2)[0];
    if (str_contains($path, '%')) throw new \RuntimeException('Review resource paths must not be encoded.');
    if ($path === '') return FullSiteReviewPackage::path($from);
    $parts = str_starts_with($path, '/') ? [] : explode('/', dirname($from) === '.' ? '' : dirname($from));
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    foreach (explode('/', $path) as $part) {
      if ($part === '' || $part === '.') continue;
      if ($part === '..') { if (!$parts) throw new \RuntimeException('Review reference leaves the package.'); array_pop($parts); }
      else $parts[] = $part;
    }
    $resolved = implode('/', $parts);
    if ($resolved === '' || str_ends_with($path, '/') || $path === '.' || $path === '..') $resolved .= ($resolved === '' ? '' : '/') . 'index.html';
    return FullSiteReviewPackage::path($resolved);
  }
}
