<?php
if (!hash_equals('__DEPLOY_TOKEN__', (string) ($_GET['t'] ?? ''))) {
    http_response_code(404); exit;
}
if (function_exists('opcache_reset')) {
    opcache_reset();
}
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Artisan::call('config:clear');
Artisan::call('route:clear');
Artisan::call('view:clear');
// Delete per-hour OPcache markers so the next web request re-clears FPM workers
foreach (glob(__DIR__.'/storage/framework/opcache-cleared-*') ?: [] as $marker) {
    @unlink($marker);
}
@unlink(__FILE__);
echo 'OK';
