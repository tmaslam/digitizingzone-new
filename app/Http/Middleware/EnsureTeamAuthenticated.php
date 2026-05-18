<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\HttpCache;
use App\Support\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = $request->session()->get('team_user_id');

        if (! $teamId) {
            SecurityAudit::recordUnauthorizedAccess($request, 'Team route was requested without an active team session.');

            return redirect('/team');
        }

        $teamUser = AdminUser::query()
            ->teamPortalUsers()
            ->active()
            ->where('is_active', 1)
            ->where('user_id', $teamId)
            ->first();

        if (! $teamUser) {
            SecurityAudit::recordUnauthorizedAccess($request, 'Team session referenced a user that is no longer valid.', [
                'team_user_id' => $teamId,
            ]);

            $request->session()->forget(['team_user_id', 'team_user_name', 'team_user_type_id']);
            $request->session()->regenerateToken();

            return redirect('/team');
        }

        $request->attributes->set('teamUser', $teamUser);

        return HttpCache::applyPrivateNoStore($next($request));
    }
}
