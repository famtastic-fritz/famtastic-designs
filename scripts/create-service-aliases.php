<?php

/**
 * @file
 * Create URL aliases for existing service offerings.
 */

use Drupal\path_alias\Entity\PathAlias;

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = $storage->loadByProperties(['type' => 'service_offering']);

foreach ($nodes as $node) {
  $slug = $node->get('field_slug')->value;
  if (!$slug) {
    continue;
  }
  $alias = '/services/' . $slug;
  $existing = \Drupal::entityTypeManager()
    ->getStorage('path_alias')
    ->loadByProperties(['alias' => $alias]);
  if ($existing) {
    print "Exists: {$alias}\n";
    continue;
  }
  PathAlias::create([
    'path' => '/node/' . $node->id(),
    'alias' => $alias,
    'langcode' => 'en',
  ])->save();
  print "Created: {$alias}\n";
}
