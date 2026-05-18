<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Facades\Schema;

class HostedPaymentProviders
{
    public const TWOCHECKOUT = '2checkout_hosted';
    public const STRIPE = 'stripe_checkout';

    public static function configuredOptions(?SiteContext $site = null): array
    {
        if ($site) {
            $provider = self::defaultProvider($site);
            $option = self::option($provider);

            return $option ? [$option] : [];
        }

        return array_values(array_filter([
            self::option(self::STRIPE),
            self::option(self::TWOCHECKOUT),
        ]));
    }

    public static function choose(?string $provider, ?SiteContext $site = null): string
    {
        $provider = trim((string) $provider);
        $configured = array_column(self::configuredOptions($site), 'key');

        if (in_array($provider, $configured, true)) {
            return $provider;
        }

        return self::defaultProvider($site);
    }

    public static function defaultProvider(?SiteContext $site = null): string
    {
        $configured = array_column(self::configuredOptions(), 'key');
        $preferred = self::sitePreferredProvider($site);

        if (in_array($preferred, $configured, true)) {
            return $preferred;
        }

        return $configured[0] ?? self::STRIPE;
    }

    public static function isReady(string $provider): bool
    {
        return match ($provider) {
            self::STRIPE => trim((string) config('services.stripe.secret_key', '')) !== '',
            self::TWOCHECKOUT => self::twocheckoutReady(),
            default => false,
        };
    }

    public static function label(?string $provider): string
    {
        return match ((string) $provider) {
            self::STRIPE => 'Stripe',
            self::TWOCHECKOUT => '2Checkout',
            default => 'Hosted Payment',
        };
    }

    public static function editableProviders(): array
    {
        return [
            self::TWOCHECKOUT => self::label(self::TWOCHECKOUT),
            self::STRIPE => self::label(self::STRIPE),
        ];
    }

    private static function sitePreferredProvider(?SiteContext $site = null): string
    {
        $siteProvider = trim((string) ($site?->activePaymentProvider ?? ''));

        if ($siteProvider === '' && $site?->id && Schema::hasTable('sites')) {
            $siteProvider = trim((string) Site::query()->whereKey($site->id)->value('active_payment_provider'));
        }

        if (in_array($siteProvider, array_keys(self::editableProviders()), true)) {
            return $siteProvider;
        }

        $primaryLegacyKey = (string) config('sites.primary_legacy_key', '1dollar');
        if ($site && strcasecmp($site->legacyKey, $primaryLegacyKey) === 0) {
            return self::TWOCHECKOUT;
        }

        return trim((string) config('services.payments.default_provider', self::STRIPE));
    }

    private static function option(string $provider): ?array
    {
        if (! self::isReady($provider)) {
            return null;
        }

        return [
            'key' => $provider,
            'label' => self::label($provider),
        ];
    }

    private static function twocheckoutReady(): bool
    {
        if (self::simulationReady()) {
            return true;
        }

        return trim((string) config('services.twocheckout.seller_id', '')) !== ''
            && trim((string) config('services.twocheckout.purchase_url', '')) !== ''
            && trim((string) config('services.twocheckout.secret_word', '')) !== '';
    }

    private static function simulationReady(): bool
    {
        if (! filter_var(config('services.twocheckout.simulation_enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        if (app()->environment('local') || config('app.env') === 'local') {
            return true;
        }

        return trim((string) config('services.twocheckout.simulation_customer_id', '')) !== ''
            || trim((string) config('services.twocheckout.simulation_customer_email', '')) !== '';
    }
}
