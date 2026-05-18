<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\HttpCache;
use App\Support\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupervisorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $teamUser = $request->attributes->get('teamUser');

        if (! $teamUser || (int) $teamUser->usre_type_id !== AdminUser::TYPE_SUPERVISOR) {
            SecurityAudit::recordUnauthorizedAccess($request, 'Supervisor-only route was requested by a non-supervisor account.', [
                'team_user_id' => $teamUser?->user_id,
                'team_user_type_id' => $teamUser?->usre_type_id,
            ]);

            return redirect('/team/welcome.php');
        }

        return HttpCache::applyPrivateNoStore($next($request));
    }
}
