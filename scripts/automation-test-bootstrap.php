<?php
/** Reuse an existing dependency runtime read-only; never boot its Drupal site. */
declare(strict_types=1);
$vendor = getenv('FAMTASTIC_BACKEND_VENDOR');
if (!$vendor || !is_file($vendor . '/autoload.php')) throw new RuntimeException('Set FAMTASTIC_BACKEND_VENDOR to an existing matching vendor tree. No installation is performed.');
$loader = require $vendor . '/autoload.php';
$core = dirname($vendor) . '/web/core';
$loader->addPsr4('Drupal\\Core\\', $core . '/lib/Drupal/Core', TRUE);
$loader->addPsr4('Drupal\\Component\\', $core . '/lib/Drupal/Component', TRUE);
$loader->addPsr4('Drupal\\sqlite\\', $core . '/modules/sqlite/src', TRUE);
$loader->addPsr4('Drupal\\Tests\\', $core . '/tests/Drupal/Tests', TRUE);
$loader->addPsr4('Drupal\\TestTools\\', $core . '/tests/Drupal/TestTools', TRUE);
$loader->addPsr4('Drupal\\famtastic_pipeline\\', dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src', TRUE);
require_once $core . '/lib/Drupal.php';
