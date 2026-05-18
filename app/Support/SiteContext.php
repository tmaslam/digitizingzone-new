<?php

namespace App\Support;

class SiteContext
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $legacyKey,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $brandName,
        public readonly string $host,
        public readonly string $supportEmail,
        public readonly string $fromEmail,
        public readonly string $websiteAddress,
        public readonly bool $isPrimary,
        public readonly string $activePaymentProvider = '',
        public readonly string $timezone = 'UTC',
        public readonly string $companyAddress = '',
    ) {
    }

    public function displayLabel(): string
    {
        return $this->brandName !== '' ? $this->brandName : $this->name;
    }

    public function matchesLegacyKey(?string $legacyKey): bool
    {
        return $legacyKey !== null && strcasecmp($this->legacyKey, trim($legacyKey)) === 0;
    }
}
