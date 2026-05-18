<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SitePricingProfile;
use App\Support\AdminNavigation;
use App\Support\SitePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminSitePricingController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('site_pricing_profiles') && Schema::hasTable('sites'), 404);

        $profiles = SitePricingProfile::query()
            ->with('site')
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', (int) $request->input('site_id')))
            ->when($request->filled('work_type'), fn ($query) => $query->where('work_type', (string) $request->input('work_type')))
            ->orderByDesc('is_active')
            ->orderBy('site_id')
            ->orderBy('work_type')
            ->orderBy('turnaround_code')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tools.site-pricing.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'profiles' => $profiles,
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
            'workTypes' => $this->workTypes(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(Schema::hasTable('site_pricing_profiles') && Schema::hasTable('sites'), 404);

        return view('admin.tools.site-pricing.form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'profile' => new SitePricingProfile([
                'work_type' => 'digitizing',
                'turnaround_code' => 'standard',
                'pricing_mode' => 'per_thousand',
                'is_active' => 1,
            ]),
            'mode' => 'create',
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
            'workTypes' => $this->workTypes(),
            'turnaroundCodes' => $this->turnaroundCodes(),
            'pricingModes' => $this->pricingModes(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('site_pricing_profiles') && Schema::hasTable('sites'), 404);

        $validated = $this->validateProfile($request);
        $now = now()->format('Y-m-d H:i:s');

        SitePricingProfile::query()->create([
            'site_id' => (int) $validated['site_id'],
            'profile_name' => trim((string) $validated['profile_name']),
            'work_type' => strtolower(trim((string) $validated['work_type'])),
            'turnaround_code' => strtolower(trim((string) ($validated['turnaround_code'] ?? ''))),
            'pricing_mode' => strtolower(trim((string) $validated['pricing_mode'])),
            'fixed_price' => $request->filled('fixed_price') ? number_format((float) $validated['fixed_price'], 2, '.', '') : null,
            'per_thousand_rate' => $request->filled('per_thousand_rate') ? number_format((float) $validated['per_thousand_rate'], 4, '.', '') : null,
            'minimum_charge' => $request->filled('minimum_charge') ? number_format((float) $validated['minimum_charge'], 2, '.', '') : null,
            'included_units' => $request->filled('included_units') ? number_format((float) $validated['included_units'], 2, '.', '') : null,
            'overage_rate' => $request->filled('overage_rate') ? number_format((float) $validated['overage_rate'], 4, '.', '') : null,
            'package_name' => trim((string) ($validated['package_name'] ?? '')) ?: null,
            'config_json' => null,
            'is_active' => (int) $validated['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->to(url('/v/site-pricing.php'))
            ->with('success', 'Site pricing profile created successfully.');
    }

    public function edit(Request $request, int $profile)
    {
        abort_unless(Schema::hasTable('site_pricing_profiles') && Schema::hasTable('sites'), 404);

        return view('admin.tools.site-pricing.form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'profile' => SitePricingProfile::query()->findOrFail($profile),
            'mode' => 'edit',
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
            'workTypes' => $this->workTypes(),
            'turnaroundCodes' => $this->turnaroundCodes(),
            'pricingModes' => $this->pricingModes(),
        ]);
    }

    public function update(Request $request, int $profile)
    {
        abort_unless(Schema::hasTable('site_pricing_profiles') && Schema::hasTable('sites'), 404);

        $profile = SitePricingProfile::query()->findOrFail($profile);
        $validated = $this->validateProfile($request);

        $profile->update([
            'site_id' => (int) $validated['site_id'],
            'profile_name' => trim((string) $validated['profile_name']),
            'work_type' => strtolower(trim((string) $validated['work_type'])),
            'turnaround_code' => strtolower(trim((string) ($validated['turnaround_code'] ?? ''))),
            'pricing_mode' => strtolower(trim((string) $validated['pricing_mode'])),
            'fixed_price' => $request->filled('fixed_price') ? number_format((float) $validated['fixed_price'], 2, '.', '') : null,
            'per_thousand_rate' => $request->filled('per_thousand_rate') ? number_format((float) $validated['per_thousand_rate'], 4, '.', '') : null,
            'minimum_charge' => $request->filled('minimum_charge') ? number_format((float) $validated['minimum_charge'], 2, '.', '') : null,
            'included_units' => $request->filled('included_units') ? number_format((float) $validated['included_units'], 2, '.', '') : null,
            'overage_rate' => $request->filled('overage_rate') ? number_format((float) $validated['overage_rate'], 4, '.', '') : null,
            'package_name' => trim((string) ($validated['package_name'] ?? '')) ?: null,
            'updated_at' => now()->format('Y-m-d H:i:s'),
            'is_active' => (int) $validated['is_active'],
        ]);

        return redirect()->to(url('/v/site-pricing.php'))
            ->with('success', 'Site pricing profile updated successfully.');
    }

    public function destroy(int $profile)
    {
        abort_unless(Schema::hasTable('site_pricing_profiles'), 404);

        SitePricingProfile::query()->findOrFail($profile)->delete();

        return redirect()->to(url('/v/site-pricing.php'))
            ->with('success', 'Site pricing profile deleted successfully.');
    }

    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'site_id' => ['required', 'integer'],
            'profile_name' => ['required', 'string', 'max:150'],
            'work_type' => ['required', 'in:'.implode(',', array_keys($this->workTypes()))],
            'turnaround_code' => ['nullable', 'in:'.implode(',', array_keys($this->turnaroundCodes()))],
            'pricing_mode' => ['required', 'in:'.implode(',', array_keys($this->pricingModes()))],
            'fixed_price' => ['nullable', 'numeric', 'min:0'],
            'per_thousand_rate' => ['nullable', 'numeric', 'min:0'],
            'minimum_charge' => ['nullable', 'numeric', 'min:0'],
            'included_units' => ['nullable', 'numeric', 'min:0'],
            'overage_rate' => ['nullable', 'numeric', 'min:0'],
            'package_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'in:0,1'],
        ]);
    }

    private function workTypes(): array
    {
        return [
            'digitizing' => SitePricing::workTypeLabel('digitizing'),
            'vector' => SitePricing::workTypeLabel('vector'),
        ];
    }

    private function turnaroundCodes(): array
    {
        return [
            'standard' => SitePricing::turnaroundLabel('standard'),
            'normal' => SitePricing::turnaroundLabel('normal'),
            'priority' => SitePricing::turnaroundLabel('priority'),
            'express' => SitePricing::turnaroundLabel('express'),
            'superrush' => SitePricing::turnaroundLabel('superrush'),
        ];
    }

    private function pricingModes(): array
    {
        return [
            'per_thousand' => 'Per 1,000 Stitches',
            'fixed_price' => 'Fixed / Hourly',
            'customer_rate' => 'Customer Rate Compatible',
            'package' => 'Package / Custom',
        ];
    }
}
