<?php
/**
 * Emergency OPcache clear for legacy.digitizingzone.com
 * Visit: https://legacy.digitizingzone.com/_clear.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== OPcache Aggressive Clear ===\n\n";

$cleared = 0;
$errors = [];

// 1. Reset OPcache globally
if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo "opcache_reset(): " . ($ok ? "SUCCESS" : "FAILED") . "\n";
} else {
    echo "opcache_reset(): NOT AVAILABLE\n";
}

// 2. Force-invalidate every PHP file under project root
$root = __DIR__;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getRealPath();
    if ($path === false) {
        continue;
    }
    if (function_exists('opcache_invalidate')) {
        $ok = @opcache_invalidate($path, true);
        if ($ok) {
            $cleared++;
        }
    }
}

echo "opcache_invalidate() files cleared: {$cleared}\n\n";

// 3. Clear Laravel caches if possible
echo "=== Laravel Cache Clear ===\n";
$laravelCaches = [
    $root . '/bootstrap/cache/routes-v7.php',
    $root . '/bootstrap/cache/packages.php',
    $root . '/bootstrap/cache/services.php',
    $root . '/bootstrap/cache/config.php',
];
foreach ($laravelCaches as $cacheFile) {
    if (file_exists($cacheFile)) {
        $deleted = @unlink($cacheFile);
        echo "Deleted: " . basename($cacheFile) . " -> " . ($deleted ? "YES" : "NO") . "\n";
    }
}

// 4. Clear view compiled files
$viewCacheDir = $root . '/storage/framework/views';
if (is_dir($viewCacheDir)) {
    $viewFiles = glob($viewCacheDir . '/*');
    $viewDeleted = 0;
    foreach ($viewFiles as $vf) {
        if (is_file($vf) && @unlink($vf)) {
            $viewDeleted++;
        }
    }
    echo "Compiled views deleted: {$viewDeleted}\n";
}

// 5. Verify our new code is actually on disk
echo "\n=== Code Verification ===\n";

$routeFile = $root . '/routes/web.php';
$routeContent = file_get_contents($routeFile);
echo "routes/web.php contains '/opcache-clear': " . (str_contains($routeContent, '/opcache-clear') ? "YES" : "NO") . "\n";

$trustedFile = $root . '/app/Support/TrustedTwoFactorDevice.php';
$trustedContent = file_get_contents($trustedFile);
echo "TrustedTwoFactorDevice.php contains 'HMAC': " . (str_contains($trustedContent, 'hash_hmac') ? "YES" : "NO") . "\n";

$viewFile = $root . '/resources/views/admin/auth/two-factor.blade.php';
$viewContent = file_get_contents($viewFile);
echo "two-factor.blade.php contains '[v3]': " . (str_contains($viewContent, '[v3]') ? "YES" : "NO") . "\n";

// 6. Show OPcache status
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    if ($status) {
        echo "\n=== OPcache Status ===\n";
        echo "Enabled: " . ($status['opcache_enabled'] ? 'YES' : 'NO') . "\n";
        echo "Cache hits: " . ($status['opcache_statistics']['hits'] ?? 'N/A') . "\n";
        echo "Cache misses: " . ($status['opcache_statistics']['misses'] ?? 'N/A') . "\n";
        echo "Cached scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? 'N/A') . "\n";
    }
}

echo "\n=== DONE ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "\nNow refresh: https://legacy.digitizingzone.com/v/login-2fa\n";
