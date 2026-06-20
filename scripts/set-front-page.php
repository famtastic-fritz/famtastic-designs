<?php

/**
 * @file
 * Set the site front page to the first published node so the front-page
 * theme template (page--front.html.twig) is used after a fresh install.
 *
 * Run with: ./vendor/bin/drush php:script scripts/set-front-page.php
 */

use Drupal\node\NodeInterface;

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $storage->getQuery()
  ->condition('status', NodeInterface::PUBLISHED)
  ->sort('nid', 'ASC')
  ->range(0, 1)
  ->accessCheck(FALSE)
  ->execute();

if (!$nids) {
  print "No published nodes found.\n";
  return;
}

$nid = reset($nids);
\Drupal::configFactory()->getEditable('system.site')
  ->set('page.front', '/node/' . $nid)
  ->save();

print "Front page set to /node/{$nid}\n";
