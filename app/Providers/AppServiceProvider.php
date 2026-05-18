<?php

namespace App\Providers;

use App\Support\SharedUploads;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $forcedUrl = trim((string) env('APP_FORCE_URL', ''));
        if ($forcedUrl !== '') {
            URL::forceRootUrl(rtrim($forcedUrl, '/'));
        }

        if (filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            URL::forceScheme('https');
        }

        SharedUploads::ensureReady();
        Paginator::defaultView('pagination.admin');
        Paginator::defaultSimpleView('pagination.admin');
    }
}
