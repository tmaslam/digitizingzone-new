<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SiteResolver
{
    public static function forRequest(Request $request): SiteContext
    {
        return self::fromHost((string) $request->getHost());
    }

    public static function fromHost(string $host): SiteContext
    {
        $normalizedHost = self::normalizeHost($host);

        if ($normalizedHost !== '' && Schema::hasTable('sites') && Schema::hasTable('site_domains')) {
            $domain = SiteDomain::query()
                ->with('site')
                ->active()
                ->whereRaw('LOWER(host) = ?', [$normalizedHost])
                ->first();

            if ($domain?->site) {
                return self::fromSiteModel($domain->site, $normalizedHost);
            }

            $primarySite = Site::query()->active()->primary()->first()
                ?: Site::query()->active()->orderByDesc('is_primary')->orderBy('id')->first();

            if ($primarySite) {
                return self::fromSiteModel($primarySite, $normalizedHost);
            }
        }

        return self::fromFallbackConfig($normalizedHost);
    }

    public static function fromLegacyKey(?string $legacyKey): ?SiteContext
    {
        $legacyKey = trim((string) $legacyKey);

        if ($legacyKey === '') {
            return null;
        }

        if (Schema::hasTable('sites')) {
            $site = Site::query()->active()->legacyKey($legacyKey)->first();

            if ($site) {
                return self::fromSiteModel($site);
            }
        }

        $fallbackSites = (array) config('sites.fallback_sites', []);

        foreach ($fallbackSites as $fallbackSite) {
            if (strcasecmp((string) ($fallbackSite['legacy_key'] ?? ''), $legacyKey) === 0) {
                return self::siteContextFromArray($fallbackSite);
            }
        }

        return null;
    }

    private static function fromSiteModel(Site $site, string $resolvedHost = ''): SiteContext
    {
        $primaryDomain = $site->primary_domain ?: $site->domains()->primary()->value('host') ?: $resolvedHost;
        $fallbackPrefix = 'sites.fallback_sites.'.$site->legacy_key.'.';

        return new SiteContext(
            id: $site->getAttribute('id') ? (int) $site->getAttribute('id') : null,
            legacyKey: (string) ($site->legacy_key ?: config('sites.primary_legacy_key', '1dollar')),
            slug: (string) ($site->slug ?: $site->legacy_key ?: 'site'),
            name: (string) ($site->name ?: $site->brand_name ?: 'Site'),
            brandName: (string) ($site->brand_name ?: $site->name ?: 'Site'),
            host: self::normalizeHost($primaryDomain),
            supportEmail: (string) (config($fallbackPrefix.'support_email') ?: $site->support_email ?: config('mail.site_from.address')),
            fromEmail: (string) (config($fallbackPrefix.'from_email') ?: $site->from_email ?: config('mail.from.address')),
            websiteAddress: (string) (config($fallbackPrefix.'website_address') ?: $site->website_address ?: $primaryDomain),
            companyAddress: (string) (config($fallbackPrefix.'company_address') ?: $site->company_address ?: ''),
            isPrimary: (bool) $site->is_primary,
            activePaymentProvider: (string) ($site->active_payment_provider ?: ''),
            timezone: (string) ($site->timezone ?: config('app.timezone', 'UTC')),
        );
    }

    private static function fromFallbackConfig(string $normalizedHost): SiteContext
    {
        $fallbackSites = (array) config('sites.fallback_sites', []);

        foreach ($fallbackSites as $fallbackSite) {
            if (self::normalizeHost((string) ($fallbackSite['host'] ?? '')) === $normalizedHost && $normalizedHost !== '') {
                return self::siteContextFromArray($fallbackSite);
            }
        }

        $primaryLegacyKey = (string) config('sites.primary_legacy_key', '1dollar');
        $primaryFallback = $fallbackSites[$primaryLegacyKey] ?? reset($fallbackSites) ?: [];

        return self::siteContextFromArray($primaryFallback);
    }

    private static function siteContextFromArray(array $site): SiteContext
    {
        $legacyKey = (string) ($site['legacy_key'] ?? config('sites.primary_legacy_key', '1dollar'));
        $host = self::normalizeHost((string) ($site['host'] ?? config('sites.primary_host', 'localhost')));

        return new SiteContext(
            id: isset($site['id']) ? (int) $site['id'] : null,
            legacyKey: $legacyKey,
            slug: (string) ($site['slug'] ?? $legacyKey),
            name: (string) ($site['name'] ?? $legacyKey),
            brandName: (string) ($site['brand_name'] ?? $site['name'] ?? $legacyKey),
            host: $host,
            supportEmail: (string) ($site['support_email'] ?? config('mail.site_from.address')),
            fromEmail: (string) ($site['from_email'] ?? config('mail.from.address')),
            websiteAddress: (string) ($site['website_address'] ?? $host),
            companyAddress: (string) ($site['company_address'] ?? ''),
            isPrimary: (bool) ($site['is_primary'] ?? false),
            activePaymentProvider: (string) ($site['active_payment_provider'] ?? ''),
            timezone: (string) ($site['timezone'] ?? config('app.timezone', 'UTC')),
        );
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return $host;
    }
}
