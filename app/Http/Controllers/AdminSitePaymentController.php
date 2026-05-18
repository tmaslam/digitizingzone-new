<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\AdminNavigation;
use App\Support\HostedPaymentProviders;
use App\Support\SiteResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminSitePaymentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('sites'), 404);

        return view('admin.tools.site-payments.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
            'providers' => HostedPaymentProviders::editableProviders(),
        ]);
    }

    public function update(Request $request, int $site)
    {
        abort_unless(Schema::hasTable('sites'), 404);

        $validated = $request->validate([
            'active_payment_provider' => ['required', 'in:'.implode(',', array_keys(HostedPaymentProviders::editableProviders()))],
        ]);

        $siteModel = Site::query()->findOrFail($site);
        $siteModel->update([
            'active_payment_provider' => (string) $validated['active_payment_provider'],
        ]);

        return redirect()->to(url('/v/site-payments.php'))
            ->with('success', 'Site payment provider updated successfully.');
    }

    public static function currentProviderLabel(Site $site): string
    {
        $siteContext = SiteResolver::fromLegacyKey((string) $site->legacy_key);
        $provider = trim((string) ($site->active_payment_provider ?: HostedPaymentProviders::defaultProvider($siteContext)));

        return HostedPaymentProviders::label($provider);
    }
}
