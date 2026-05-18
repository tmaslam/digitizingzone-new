<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$cachePath = __DIR__.'/../bootstrap/cache';
$webRoutesPath = __DIR__.'/../routes/web.php';

if (is_file($webRoutesPath) && is_dir($cachePath)) {
    $webRoutesMtime = @filemtime($webRoutesPath) ?: 0;

    foreach (glob($cachePath.'/routes*.php') ?: [] as $routeCacheFile) {
        $routeCacheMtime = @filemtime($routeCacheFile) ?: 0;

        // Manual folder overwrites can leave an old cached route file behind.
        if ($webRoutesMtime > 0 && $routeCacheMtime > 0 && $webRoutesMtime > $routeCacheMtime) {
            @unlink($routeCacheFile);
        }
    }
}

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
