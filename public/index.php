<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
    $_SERVER['REQUEST_SCHEME'] = 'https';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
    $_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
