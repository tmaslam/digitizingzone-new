<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SitePromotion;
use App\Models\SitePromotionClaim;
use App\Support\AdminNavigation;
use App\Support\SignupOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminSiteOfferController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('site_promotions') && Schema::hasTable('sites'), 404);

        $offers = SitePromotion::query()
            ->with('site')
            ->signupOffers()
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', (int) $request->input('site_id')))
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.tools.site-offers.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'offers' => $offers,
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
        ]);
    }

    public function allClaims(Request $request)
    {
        abort_unless(Schema::hasTable('site_promotion_claims'), 404);

        $query = SitePromotionClaim::query()
            ->with(['customer', 'promotion', 'redeemedOrder'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('offer_id'), fn ($q) => $q->where('site_promotion_id', (int) $request->input('offer_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('created_at');

        $offers = Schema::hasTable('site_promotions')
            ? SitePromotion::query()->signupOffers()->orderByDesc('id')->get()
            : collect();

        $claims = $query->paginate(50)->withQueryString();

        return view('admin.tools.site-offers.all-claims', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'claims'    => $claims,
            'offers'    => $offers,
        ]);
    }

    public function claims(Request $request, int $offer)
    {
        abort_unless(Schema::hasTable('site_promotion_claims'), 404);

        $offer = SitePromotion::query()->signupOffers()->findOrFail($offer);

        $claims = SitePromotionClaim::query()
            ->with(['customer', 'redeemedOrder'])
            ->where('site_promotion_id', $offer->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString();

        $config = SignupOfferService::offerSummary($offer);
        $threshold = (int) ($config['first_order_free_under_stitches'] ?? 0);

        return view('admin.tools.site-offers.claims', [
            'adminUser'  => $request->attributes->get('adminUser'),
            'navCounts'  => AdminNavigation::counts(),
            'offer'      => $offer,
            'claims'     => $claims,
            'threshold'  => $threshold,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(Schema::hasTable('site_promotions') && Schema::hasTable('sites'), 404);

        return view('admin.tools.site-offers.form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'offer' => new SitePromotion([
                'is_active' => 1,
                'work_type' => 'signup',
                'discount_type' => 'signup_offer',
            ]),
            'mode' => 'create',
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
            'offerConfig' => [
                'headline' => 'Pay $1 and your first order is free under 10k stitches',
                'summary' => 'Verify your email address, complete the secure $1 onboarding payment, and unlock your first free order under 10,000 stitches.',
                'verification_message' => 'If the verification email is not in your inbox, please check your spam or junk folder.',
                'onboarding_payment_amount' => 1.00,
                'credit_amount' => 0.00,
                'first_order_flat_amount' => 0.00,
                'first_order_free_under_stitches' => 10000,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('site_promotions') && Schema::hasTable('sites'), 404);

        $validated = $this->validateOffer($request);
        $now = now()->format('Y-m-d H:i:s');

        SitePromotion::query()->create([
            'site_id' => (int) $validated['site_id'],
            'promotion_name' => $validated['promotion_name'],
            'promotion_code' => $validated['promotion_code'] ?: null,
            'work_type' => 'signup',
            'discount_type' => 'signup_offer',
            'discount_value' => number_format((float) $validated['first_order_flat_amount'], 2, '.', ''),
            'minimum_order_amount' => null,
            'starts_at' => $this->normalizeDateTime($validated['starts_at'] ?? null),
            'ends_at' => $this->normalizeDateTime($validated['ends_at'] ?? null),
            'config_json' => json_encode($this->configPayload($validated), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_active' => (int) $validated['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->to(url('/v/site-offers.php'))
            ->with('success', 'Site offer created successfully.');
    }

    public function edit(Request $request, int $offer)
    {
        abort_unless(Schema::hasTable('site_promotions') && Schema::hasTable('sites'), 404);

        $offer = SitePromotion::query()->signupOffers()->findOrFail($offer);

        return view('admin.tools.site-offers.form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'offer' => $offer,
            'mode' => 'edit',
            'sites' => Site::query()->active()->orderByDesc('is_primary')->orderBy('name')->get(),
            'offerConfig' => SignupOfferService::offerSummary($offer),
        ]);
    }

    public function update(Request $request, int $offer)
    {
        abort_unless(Schema::hasTable('site_promotions') && Schema::hasTable('sites'), 404);

        $offer = SitePromotion::query()->signupOffers()->findOrFail($offer);
        $validated = $this->validateOffer($request);

        $offer->update([
            'site_id' => (int) $validated['site_id'],
            'promotion_name' => $validated['promotion_name'],
            'promotion_code' => $validated['promotion_code'] ?: null,
            'work_type' => 'signup',
            'discount_type' => 'signup_offer',
            'discount_value' => number_format((float) $validated['first_order_flat_amount'], 2, '.', ''),
            'starts_at' => $this->normalizeDateTime($validated['starts_at'] ?? null),
            'ends_at' => $this->normalizeDateTime($validated['ends_at'] ?? null),
            'config_json' => json_encode($this->configPayload($validated), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_active' => (int) $validated['is_active'],
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return redirect()->to(url('/v/site-offers.php'))
            ->with('success', 'Site offer updated successfully.');
    }

    public function destroy(int $offer)
    {
        abort_unless(Schema::hasTable('site_promotions'), 404);

        $offer = SitePromotion::query()->signupOffers()->findOrFail($offer);
        $offer->delete();

        return redirect()->to(url('/v/site-offers.php'))
            ->with('success', 'Site offer deleted successfully.');
    }

    private function validateOffer(Request $request): array
    {
        return $request->validate([
            'site_id' => ['required', 'integer'],
            'promotion_name' => ['required', 'string', 'max:150'],
            'promotion_code' => ['nullable', 'string', 'max:100'],
            'headline' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:1000'],
            'verification_message' => ['required', 'string', 'max:500'],
            'onboarding_payment_amount' => ['required', 'numeric', 'min:0'],
            'credit_amount' => ['required', 'numeric', 'min:0'],
            'first_order_flat_amount' => ['required', 'numeric', 'min:0'],
            'first_order_free_under_stitches' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['required', 'in:0,1'],
        ], [], [
            'site_id' => 'site',
            'promotion_name' => 'offer name',
            'promotion_code' => 'offer code',
            'headline' => 'headline',
            'summary' => 'summary',
            'verification_message' => 'verification message',
            'onboarding_payment_amount' => 'welcome payment amount',
            'credit_amount' => 'credit amount',
            'first_order_flat_amount' => 'first order price',
            'first_order_free_under_stitches' => 'first-order free stitch limit',
            'starts_at' => 'start date',
            'ends_at' => 'end date',
            'is_active' => 'status',
        ]);
    }

    private function configPayload(array $validated): array
    {
        return [
            'headline' => trim((string) $validated['headline']),
            'summary' => trim((string) $validated['summary']),
            'verification_message' => trim((string) $validated['verification_message']),
            'onboarding_payment_amount' => round((float) $validated['onboarding_payment_amount'], 2),
            'credit_amount' => round((float) $validated['credit_amount'], 2),
            'first_order_flat_amount' => round((float) $validated['first_order_flat_amount'], 2),
            'first_order_free_under_stitches' => max(0, (int) ($validated['first_order_free_under_stitches'] ?? 0)),
        ];
    }

    private function normalizeDateTime(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : date('Y-m-d H:i:s', strtotime($value));
    }
}
