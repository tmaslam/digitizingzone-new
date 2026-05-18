<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\HttpCache;
use App\Support\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_user_id');

        if (! $adminId) {
            SecurityAudit::recordUnauthorizedAccess($request, 'Admin route was requested without an active admin session.');

            return redirect('/v');
        }

        $admin = AdminUser::query()
            ->admins()
            ->where('user_id', $adminId)
            ->first();

        if (! $admin) {
            SecurityAudit::recordUnauthorizedAccess($request, 'Admin session referenced a user that is no longer valid.', [
                'admin_user_id' => $adminId,
            ]);

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/v');
        }

        $request->attributes->set('adminUser', $admin);

        return HttpCache::applyPrivateNoStore($next($request));
    }
}
