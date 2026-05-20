<?php
if (!hash_equals('__DEPLOY_TOKEN__', (string) ($_GET['t'] ?? ''))) {
    http_response_code(404); exit;
}
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Artisan::call('config:clear');
Artisan::call('route:clear');
Artisan::call('view:clear');
@unlink(__FILE__);
echo 'OK';
