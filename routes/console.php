<?php

use App\Models\AdminUser;
use App\Support\PasswordManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
