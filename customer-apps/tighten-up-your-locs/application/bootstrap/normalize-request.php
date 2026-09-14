<?php
// Gateways live in /admin, /api and /appointment, but routes are rooted at /.
// Keep REQUEST_URI intact; do not let Symfony infer a nested script base URL.
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['PHP_SELF']='/index.php';
