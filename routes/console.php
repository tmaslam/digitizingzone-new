<?php

use App\Models\AdvancePayment;
use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Support\LegacyQuerySupport;
use App\Support\PasswordManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('passwords:backfill-secure-hashes {--keep-legacy : Keep the old plain-text column populated after hashing}', function () {
    if (! Schema::hasColumn('users', 'password_hash')) {
        $this->error('The users.password_hash column does not exist. Apply phase_two_password_security.sql first.');

        return self::FAILURE;
    }

    $keepLegacy = (bool) $this->option('keep-legacy');
    $updated = 0;

    AdminUser::query()
        ->where(function ($query) {
            $query->whereNull('password_hash')
                ->orWhere('password_hash', '');
        })
        ->whereNotNull('user_password')
        ->where('user_password', '!=', '')
        ->orderBy('user_id')
        ->chunkById(200, function ($users) use (&$updated, $keepLegacy) {
            foreach ($users as $user) {
                $payload = PasswordManager::payload((string) $user->user_password);

                if ($keepLegacy) {
                    $payload['user_password'] = (string) $user->user_password;
                }

                $user->forceFill($payload)->save();
                $updated++;
            }
        }, 'user_id');

    $this->info('Secure password hashes prepared for '.$updated.' account(s).');

    return self::SUCCESS;
})->purpose('Backfill secure password hashes for legacy accounts');

Artisan::command('release:check {--strict : Fail when any required release item is missing}', function () {
    $requiredTables = [
        'sites',
        'site_domains',
        'site_pricing_profiles',
        'site_promotions',
        'site_promotion_claims',
        'customer_activation_tokens',
        'customer_password_reset_tokens',
        'customer_remember_tokens',
        'two_factor_trusted_devices',
        'customer_credit_ledger',
        'payment_transactions',
        'payment_transaction_items',
        'payment_provider_events',
        'quote_negotiations',
        'order_workflow_meta',
        'email_templates',
        'security_audit_events',
        'admin_login_attempts',
        'supervisor_team_members',
    ];

    $requiredUserColumns = [
        'site_id',
        'password_hash',
        'password_migrated_at',
    ];

    $issues = [];
    $warnings = [];

    foreach ($requiredTables as $table) {
        if (! Schema::hasTable($table)) {
            $issues[] = 'Missing required table: '.$table;
        }
    }

    if (! Schema::hasTable('users')) {
        $issues[] = 'Missing required table: users';
    } else {
        foreach ($requiredUserColumns as $column) {
            if (! Schema::hasColumn('users', $column)) {
                $issues[] = 'Missing required users column: '.$column;
            }
        }
    }

    $appUrl = trim((string) config('app.url'));
    $primaryHost = trim((string) config('sites.primary_host'));
    $sharedUploadsPath = trim((string) env('SHARED_UPLOADS_PATH', ''));
    $mailMailer = trim((string) config('mail.default'));
    $mailHost = trim((string) config('mail.mailers.smtp.host'));
    $mailUsername = trim((string) config('mail.mailers.smtp.username'));
    $paymentDefaultProvider = trim((string) config('services.payments.default_provider'));

    if ($appUrl === '' || str_contains($appUrl, 'localhost')) {
        $warnings[] = 'APP_URL still looks local. Set the real website URL before release.';
    }

    if ($primaryHost === '' || str_contains($primaryHost, 'localhost')) {
        $warnings[] = 'PRIMARY_SITE_HOST still looks local. Set the live host before release.';
    }

    if ($sharedUploadsPath === '') {
        $issues[] = 'SHARED_UPLOADS_PATH is not configured.';
    } elseif (! is_dir($sharedUploadsPath)) {
        $issues[] = 'SHARED_UPLOADS_PATH does not exist on disk: '.$sharedUploadsPath;
    }

    if ($mailMailer === '' || $mailMailer === 'log') {
        $warnings[] = 'MAIL_MAILER is set to log or empty. Configure real mail delivery before release.';
    }

    if ($mailHost === '' || $mailUsername === '') {
        $warnings[] = 'SMTP host/username are incomplete. Portal email delivery may fail.';
    }

    if ($paymentDefaultProvider === '') {
        $issues[] = 'PAYMENT_DEFAULT_PROVIDER is not configured.';
    } elseif ($paymentDefaultProvider === 'stripe_checkout') {
        if (trim((string) config('services.stripe.secret_key')) === '' || trim((string) config('services.stripe.publishable_key')) === '') {
            $issues[] = 'Stripe is the default payment provider but Stripe API keys are missing.';
        }

        if (trim((string) config('services.stripe.webhook_secret')) === '') {
            $warnings[] = 'Stripe webhook secret is missing. Return flow may work, but verified webhook reconciliation will not.';
        }
    } elseif ($paymentDefaultProvider === '2checkout_hosted') {
        if (trim((string) config('services.twocheckout.seller_id')) === '' || trim((string) config('services.twocheckout.secret_word')) === '') {
            $issues[] = '2Checkout is the default payment provider but seller credentials are missing.';
        }
    } else {
        $warnings[] = 'PAYMENT_DEFAULT_PROVIDER uses an unexpected value: '.$paymentDefaultProvider;
    }

    if (! Schema::hasTable('sites') || ! Schema::hasTable('site_domains')) {
        $warnings[] = 'Site tables are not fully installed, so fallback-site config will be used.';
    }

    $this->newLine();
    $this->info('Unified release readiness check');
    $this->line('Primary host: '.($primaryHost !== '' ? $primaryHost : '[not set]'));
    $this->line('App URL: '.($appUrl !== '' ? $appUrl : '[not set]'));
    $this->line('Default payment provider: '.($paymentDefaultProvider !== '' ? $paymentDefaultProvider : '[not set]'));
    $this->line('Mailer: '.($mailMailer !== '' ? $mailMailer : '[not set]'));

    if ($issues === [] && $warnings === []) {
        $this->info('No release issues detected.');

        return self::SUCCESS;
    }

    if ($issues !== []) {
        $this->newLine();
        $this->error('Blocking issues');

        foreach ($issues as $issue) {
            $this->line('- '.$issue);
        }
    }

    if ($warnings !== []) {
        $this->newLine();
        $this->warn('Warnings');

        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }
    }

    if ((bool) $this->option('strict') && ($issues !== [] || $warnings !== [])) {
        return self::FAILURE;
    }

    return $issues === [] ? self::SUCCESS : self::FAILURE;
})->purpose('Validate unified release prerequisites before QA or cutover');


Artisan::command('cleanup:old-orders {--date=2025-01-01 : Archive orders completed or submitted before this date} {--dry-run : Show counts without making changes} {--batch-size=500 : Number of orders to process per batch}', function () {
    $cutoffDate = trim((string) $this->option('date'));
    $dryRun = (bool) $this->option('dry-run');
    $batchSize = (int) $this->option('batch-size');

    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffDate)) {
        $this->error('Date must be in YYYY-MM-DD format.');

        return self::FAILURE;
    }

    $this->info('Cutoff date: ' . $cutoffDate);
    $this->info('Mode: ' . ($dryRun ? 'DRY RUN (no changes)' : 'LIVE'));
    $this->newLine();

    // Build query for old active orders (use completion_date, fall back to submit_date)
    $orderQuery = Order::query()
        ->active()
        ->where(function ($q) use ($cutoffDate) {
            $q->where(function ($q2) use ($cutoffDate) {
                $q2->whereNotNull('completion_date')
                    ->where('completion_date', '!=', '')
                    ->where('completion_date', '!=', '0000-00-00')
                    ->whereDate('completion_date', '<', $cutoffDate);
            })->orWhere(function ($q2) use ($cutoffDate) {
                $q2->where(function ($q3) {
                    $q3->whereNull('completion_date')
                        ->orWhere('completion_date', '')
                        ->orWhere('completion_date', '0000-00-00');
                })
                    ->whereNotNull('submit_date')
                    ->where('submit_date', '!=', '')
                    ->where('submit_date', '!=', '0000-00-00')
                    ->whereDate('submit_date', '<', $cutoffDate);
            });
        });

    $totalOrders = (int) $orderQuery->count();

    if ($totalOrders === 0) {
        $this->info('No active orders found before ' . $cutoffDate);

        return self::SUCCESS;
    }

    $this->warn('Found ' . $totalOrders . ' active order(s) to archive.');

    if (! $dryRun && ! $this->confirm('Do you want to proceed with archiving these orders and all related records?')) {
        $this->info('Aborted.');

        return self::SUCCESS;
    }

    $timestamp = now()->format('Y-m-d H:i:s');
    $deletedBy = 'cleanup-old-orders';

    $archivedOrders = 0;
    $archivedComments = 0;
    $archivedTeamComments = 0;
    $archivedAttachments = 0;
    $archivedBilling = 0;
    $archivedNegotiations = 0;
    $archivedWorkflowMeta = 0;
    $updatedAdvancePayments = 0;

    $orderQuery->select('order_id')
        ->orderBy('order_id')
        ->chunkById($batchSize, function ($orders) use ($timestamp, $deletedBy, $dryRun, &$archivedOrders, &$archivedComments, &$archivedTeamComments, &$archivedAttachments, &$archivedBilling, &$archivedNegotiations, &$archivedWorkflowMeta, &$updatedAdvancePayments) {
            $ids = $orders->pluck('order_id')->all();

            if ($dryRun) {
                $archivedOrders += count($ids);
                $archivedComments += OrderComment::query()->whereIn('order_id', $ids)->where(function ($q) {
                    LegacyQuerySupport::applyActiveEndDate($q, 'comments');
                })->count();
                $archivedTeamComments += DB::table('team_comments')->whereIn('order_id', $ids)->where(function ($q) {
                    LegacyQuerySupport::applyActiveEndDate($q, 'team_comments');
                })->count();
                $archivedAttachments += Attachment::query()->whereIn('order_id', $ids)->where(function ($q) {
                    LegacyQuerySupport::applyActiveEndDate($q, 'attach_files');
                })->count();
                $archivedBilling += Billing::query()->whereIn('order_id', $ids)->where(function ($q) {
                    LegacyQuerySupport::applyActiveEndDate($q, 'billing');
                })->count();

                if (Schema::hasTable('quote_negotiations')) {
                    $archivedNegotiations += DB::table('quote_negotiations')->whereIn('order_id', $ids)->whereNull('end_date')->count();
                }

                if (Schema::hasTable('order_workflow_meta')) {
                    $archivedWorkflowMeta += DB::table('order_workflow_meta')->whereIn('order_id', $ids)->whereNull('end_date')->count();
                }

                $updatedAdvancePayments += AdvancePayment::query()->whereIn('order_id', $ids)->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 0);
                })->count();
            } else {
                $archivedOrders += Order::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $deletedBy]);
                $archivedComments += OrderComment::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $deletedBy]);
                $archivedTeamComments += DB::table('team_comments')->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $deletedBy]);
                $archivedAttachments += Attachment::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $deletedBy]);
                $archivedBilling += Billing::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $deletedBy]);

                if (Schema::hasTable('quote_negotiations')) {
                    $archivedNegotiations += DB::table('quote_negotiations')->whereIn('order_id', $ids)->update(['end_date' => $timestamp]);
                }

                if (Schema::hasTable('order_workflow_meta')) {
                    $archivedWorkflowMeta += DB::table('order_workflow_meta')->whereIn('order_id', $ids)->update(['end_date' => $timestamp]);
                }

                $updatedAdvancePayments += AdvancePayment::query()->whereIn('order_id', $ids)->update(['status' => 0]);
            }

            $this->info('Processed batch of ' . count($ids) . ' order(s)...');
        }, 'order_id');

    $this->newLine();
    $this->info('Summary:');
    $this->line('  Orders archived:          ' . $archivedOrders);
    $this->line('  Comments archived:        ' . $archivedComments);
    $this->line('  Team comments archived:   ' . $archivedTeamComments);
    $this->line('  Attachments archived:     ' . $archivedAttachments);
    $this->line('  Billing records archived: ' . $archivedBilling);
    $this->line('  Quote negotiations:       ' . $archivedNegotiations);
    $this->line('  Workflow meta records:    ' . $archivedWorkflowMeta);
    $this->line('  Advance payments zeroed:  ' . $updatedAdvancePayments);

    return self::SUCCESS;
})->purpose('Archive orders and related records older than the given cutoff date');
