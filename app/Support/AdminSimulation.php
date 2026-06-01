<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;

class AdminSimulation
{
    public static function start(Request $request, AdminUser $admin, AdminUser $target): string
    {
        self::rememberImpersonator($request, $admin);
        self::rememberReturnPath($request);
        self::clearPortalSessions($request);
        $request->session()->regenerate();

        $targetRole = match ((int) $target->usre_type_id) {
            AdminUser::TYPE_CUSTOMER => 'customer',
            AdminUser::TYPE_SUPERVISOR => 'supervisor',
            AdminUser::TYPE_TEAM => 'team',
            default => 'admin',
        };

        $request->session()->put([
            'impersonation_target_user_id' => (int) $target->user_id,
            'impersonation_target_role' => $targetRole,
            'impersonation_target_name' => (string) ($target->display_name ?: $target->user_name),
        ]);

        if ((int) $target->usre_type_id === AdminUser::TYPE_CUSTOMER) {
            $siteKey = CustomerBalance::normalizeWebsite($target->website);
            $request->session()->put([
                'customer_user_id' => (int) $target->user_id,
                'customer_user_name' => (string) $target->display_name,
                'customer_site_key' => $siteKey,
            ]);

            return url('/dashboard.php');
        }

        if (in_array((int) $target->usre_type_id, [AdminUser::TYPE_TEAM, AdminUser::TYPE_SUPERVISOR], true)) {
            $request->session()->put([
                'team_user_id' => (int) $target->user_id,
                'team_user_name' => (string) $target->user_name,
                'team_user_type_id' => (int) $target->usre_type_id,
            ]);

            return url('/team/welcome.php');
        }

        $request->session()->put([
            'admin_user_id' => (int) $target->user_id,
            'admin_user_name' => (string) $target->user_name,
        ]);

        return url('/welcome.php');
    }

    public static function stop(Request $request): string
    {
        $adminId = (int) $request->session()->get('impersonator_admin_id', 0);
        $adminName = trim((string) $request->session()->get('impersonator_admin_name', ''));
        $returnPath = self::normalizeReturnPath((string) $request->session()->get('impersonator_return_path', ''));

        self::clearPortalSessions($request);
        $request->session()->regenerate();
        $request->session()->forget([
            'impersonation_target_user_id',
            'impersonation_target_role',
            'impersonation_target_name',
            'impersonator_return_path',
        ]);

        if ($adminId > 0) {
            $request->session()->put([
                'admin_user_id' => $adminId,
                'admin_user_name' => $adminName,
            ]);
        }

        $request->session()->forget([
            'impersonator_admin_id',
            'impersonator_admin_name',
        ]);

        return $returnPath !== '' ? $returnPath : url('/welcome.php');
    }

    public static function active(Request $request): bool
    {
        return (int) $request->session()->get('impersonator_admin_id', 0) > 0;
    }

    private static function rememberImpersonator(Request $request, AdminUser $admin): void
    {
        if ((int) $request->session()->get('impersonator_admin_id', 0) > 0) {
            return;
        }

        $request->session()->put([
            'impersonator_admin_id' => (int) $admin->user_id,
            'impersonator_admin_name' => (string) $admin->user_name,
        ]);
    }

    private static function rememberReturnPath(Request $request): void
    {
        if (trim((string) $request->session()->get('impersonator_return_path', '')) !== '') {
            return;
        }

        $requested = trim((string) $request->input('return_to', ''));
        if ($requested === '') {
            $requested = trim((string) $request->headers->get('referer', ''));
        }

        $normalized = self::normalizeReturnPath($requested);
        if ($normalized === '') {
            return;
        }

        $request->session()->put('impersonator_return_path', $normalized);
    }

    private static function normalizeReturnPath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $parsed = parse_url($trimmed);
            if (! empty($parsed['host'])) {
                return '';
            }
            $path = (string) ($parsed['path'] ?? '');
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';
            $trimmed = $path.$query;
        }

        if (! str_starts_with($trimmed, '/')) {
            return '';
        }

        if (str_starts_with($trimmed, '//')) {
            return '';
        }

        return str_contains($trimmed, '/v/simulate-login/') ? '' : $trimmed;
    }

    private static function clearPortalSessions(Request $request): void
    {
        $request->session()->forget([
            'admin_user_id',
            'admin_user_name',
            'admin_pending_2fa_user_id',
            'team_user_id',
            'team_user_name',
            'team_user_type_id',
            'customer_user_id',
            'customer_user_name',
            'customer_site_key',
            'customer_pending_2fa',
        ]);
    }
}
