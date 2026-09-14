<?php
declare(strict_types=1);
$application=dirname(__DIR__,2).'/private/owner-app/current';
require $application.'/vendor/autoload.php';
require $application.'/bootstrap/normalize-request.php';
$app=require $application.'/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
