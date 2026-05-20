<?php
/**
 * One-time cleanup: archive all orders + related records from before 2025-01-01.
 *
 * Visit: https://your-site.com/cleanup-2024.php?key=YOUR_SECRET_KEY
 * DELETE THIS FILE AFTER RUNNING.
 */

$secretKey = 'cleanup-legacy-2025-secure'; // Change this if you want, then visit with ?key=...

if (($secretKey !== '' && ($_GET['key'] ?? '') !== $secretKey)) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$cutoff = '2025-01-01';
$timestamp = now()->format('Y-m-d H:i:s');
$deletedBy = 'cleanup-2024-script';

echo '<pre>';
echo "=== Legacy Order Cleanup ===\n";
echo "Cutoff date: {$cutoff}\n";
echo "Timestamp:   {$timestamp}\n\n";

// ------------------------------------------------------------------
// 1. Find active old orders
// ------------------------------------------------------------------
$orderIds = DB::table('orders')
    ->where(function ($q) {
        $q->whereNull('end_date')
          ->orWhere('end_date', '')
          ->orWhere('end_date', '0000-00-00')
          ->orWhere('end_date', '0000-00-00 00:00:00');
    })
    ->where(function ($q) use ($cutoff) {
        $q->where(function ($q2) use ($cutoff) {
            $q2->whereNotNull('completion_date')
               ->where('completion_date', '!=', '')
               ->where('completion_date', '!=', '0000-00-00')
               ->whereDate('completion_date', '<', $cutoff);
        })->orWhere(function ($q2) use ($cutoff) {
            $q2->where(function ($q3) {
                $q3->whereNull('completion_date')
                   ->orWhere('completion_date', '')
                   ->orWhere('completion_date', '0000-00-00');
            })
            ->whereNotNull('submit_date')
            ->where('submit_date', '!=', '')
            ->where('submit_date', '!=', '0000-00-00')
            ->whereDate('submit_date', '<', $cutoff);
        });
    })
    ->pluck('order_id')
    ->all();

$total = count($orderIds);
echo "Found {$total} active order(s) to archive.\n\n";

if ($total === 0) {
    echo "Nothing to do.\n";
    exit;
}

// ------------------------------------------------------------------
// 2. Archive in batches to avoid huge UPDATE statements
// ------------------------------------------------------------------
$batchSize = 500;
$batches = array_chunk($orderIds, $batchSize);

$archivedOrders = 0;
$archivedComments = 0;
$archivedTeamComments = 0;
$archivedAttachments = 0;
$archivedBilling = 0;
$archivedNegotiations = 0;
$archivedWorkflowMeta = 0;
$updatedAdvancePayments = 0;

foreach ($batches as $batch) {
    $placeholders = implode(',', array_fill(0, count($batch), '?'));

    $archivedOrders += DB::update(
        "UPDATE orders SET end_date = ?, deleted_by = ? WHERE order_id IN ({$placeholders})",
        array_merge([$timestamp, $deletedBy], $batch)
    );

    $archivedComments += DB::update(
        "UPDATE comments SET end_date = ?, deleted_by = ? WHERE order_id IN ({$placeholders})",
        array_merge([$timestamp, $deletedBy], $batch)
    );

    if (Schema::hasTable('team_comments')) {
        $archivedTeamComments += DB::update(
            "UPDATE team_comments SET end_date = ?, deleted_by = ? WHERE order_id IN ({$placeholders})",
            array_merge([$timestamp, $deletedBy], $batch)
        );
    }

    $archivedAttachments += DB::update(
        "UPDATE attach_files SET end_date = ?, deleted_by = ? WHERE order_id IN ({$placeholders})",
        array_merge([$timestamp, $deletedBy], $batch)
    );

    $archivedBilling += DB::update(
        "UPDATE billing SET end_date = ?, deleted_by = ? WHERE order_id IN ({$placeholders})",
        array_merge([$timestamp, $deletedBy], $batch)
    );

    if (Schema::hasTable('quote_negotiations')) {
        $archivedNegotiations += DB::update(
            "UPDATE quote_negotiations SET end_date = ? WHERE order_id IN ({$placeholders})",
            array_merge([$timestamp], $batch)
        );
    }

    if (Schema::hasTable('order_workflow_meta')) {
        $archivedWorkflowMeta += DB::update(
            "UPDATE order_workflow_meta SET end_date = ? WHERE order_id IN ({$placeholders})",
            array_merge([$timestamp], $batch)
        );
    }

    if (Schema::hasTable('advancepayment')) {
        $updatedAdvancePayments += DB::update(
            "UPDATE advancepayment SET status = 0 WHERE order_id IN ({$placeholders})",
            $batch
        );
    }

    echo 'Archived batch of ' . count($batch) . " order(s)...\n";
    flush();
}

// ------------------------------------------------------------------
// 3. Summary
// ------------------------------------------------------------------
echo "\n=== DONE ===\n";
echo "Orders archived:          {$archivedOrders}\n";
echo "Comments archived:        {$archivedComments}\n";
echo "Team comments archived:   {$archivedTeamComments}\n";
echo "Attachments archived:     {$archivedAttachments}\n";
echo "Billing records archived: {$archivedBilling}\n";
echo "Quote negotiations:       {$archivedNegotiations}\n";
echo "Workflow meta records:    {$archivedWorkflowMeta}\n";
echo "Advance payments zeroed:  {$updatedAdvancePayments}\n";

echo "\n=== IMPORTANT ===\n";
echo "1. Refresh your archive page now.\n";
echo "2. DELETE this file (cleanup-2024.php) from the server after use.\n";
echo "3. If old orders still appear, clear OPcache: visit /public/clear-opcache.php\n";
