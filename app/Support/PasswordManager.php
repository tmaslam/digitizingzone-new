<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PasswordManager
{
    private static ?bool $secureColumnsAvailable = null;

    public static function matches(AdminUser $user, string $plainPassword): bool
    {
        // During rollout we accept legacy plain-text values only long enough to
        // upgrade them into a one-way hash without forcing the user to reset.
        if (! self::secureColumnsAvailable()) {
            return (string) $user->user_password === $plainPassword;
        }

        $hash = trim((string) ($user->password_hash ?? ''));

        if ($hash !== '' && Hash::check($plainPassword, $hash)) {
            if (Hash::needsRehash($hash)) {
                self::storeSecurePassword($user, $plainPassword);
            }

            return true;
        }

        if ((string) $user->user_password !== $plainPassword) {
            return false;
        }

        self::storeSecurePassword($user, $plainPassword);

        return true;
    }

    public static function storeSecurePassword(AdminUser $user, string $plainPassword): void
    {
        $user->forceFill(self::payload($plainPassword))->save();
    }

    public static function payload(string $plainPassword): array
    {
        if (! self::secureColumnsAvailable()) {
            return [
                'user_password' => $plainPassword,
            ];
        }

        return [
            'user_password' => '',
            'password_hash' => Hash::make($plainPassword),
            'password_migrated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public static function refreshColumnAvailability(): void
    {
        self::$secureColumnsAvailable = null;
    }

    private static function secureColumnsAvailable(): bool
    {
        if (self::$secureColumnsAvailable !== null) {
            return self::$secureColumnsAvailable;
        }

        try {
            self::$secureColumnsAvailable = Schema::hasColumn('users', 'password_hash')
                && Schema::hasColumn('users', 'password_migrated_at');
        } catch (\Throwable) {
            self::$secureColumnsAvailable = false;
        }

        return self::$secureColumnsAvailable;
    }
}
