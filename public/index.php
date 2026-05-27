<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// AUTO-CLEAR: aggressively invalidate stale OPcache after deployments
$opcacheMarker = __DIR__ . '/../storage/framework/opcache-cleared-' . date('Ymd-H');
if (! is_file($opcacheMarker)) {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    // Also invalidate key files individually (covers multi-worker pools)
    $invalidateTargets = [
        __DIR__ . '/../routes/web.php',
        __DIR__ . '/../app/Support/TrustedTwoFactorDevice.php',
        __DIR__ . '/../app/Http/Controllers/AdminAuthController.php',
        __DIR__ . '/../app/Http/Controllers/AdminTwoFactorController.php',
        __DIR__ . '/../app/Http/Controllers/CustomerAuthController.php',
        __DIR__ . '/../app/Http/Controllers/CustomerTwoFactorController.php',
        __DIR__ . '/../app/Http/Controllers/CustomerOrderEntryController.php',
    ];
    if (function_exists('opcache_invalidate')) {
        foreach ($invalidateTargets as $target) {
            if (is_file($target)) {
                @opcache_invalidate($target, true);
            }
        }
    }
    // Clean up old markers
    foreach (glob(__DIR__ . '/../storage/framework/opcache-cleared-*') ?: [] as $old) {
        @unlink($old);
    }
    @file_put_contents($opcacheMarker, date('Y-m-d H:i:s'));
}

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
