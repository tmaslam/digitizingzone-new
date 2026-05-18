<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\AdminSimulation;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;

class AdminSimulationController extends Controller
{
    public function start(Request $request, AdminUser $user)
    {
        $admin = $request->attributes->get('adminUser');
        abort_unless($admin && (int) $admin->usre_type_id === AdminUser::TYPE_ADMIN, 403);
        abort_unless((int) $user->is_active === 1, 404);
        abort_unless(in_array((int) $user->usre_type_id, [
            AdminUser::TYPE_CUSTOMER,
            AdminUser::TYPE_TEAM,
            AdminUser::TYPE_SUPERVISOR,
            AdminUser::TYPE_ADMIN,
        ], true), 404);

        $destination = AdminSimulation::start($request, $admin, $user);

        SecurityAudit::record($request, 'auth.simulation_started', 'Admin started a simulated portal session.', [
            'target_user_id' => (int) $user->user_id,
            'target_user_name' => (string) $user->user_name,
            'target_role' => $user->role_label,
            'target_site' => (string) $user->website,
        ], 'notice', [
            'actor_user_id' => (int) $admin->user_id,
            'actor_login' => (string) $admin->user_name,
        ]);

        return redirect($destination)->with('success', 'Simulated session started successfully.');
    }

    public function stop(Request $request)
    {
        abort_unless(AdminSimulation::active($request), 403);

        $targetUserId = (int) $request->session()->get('impersonation_target_user_id', 0);
        $targetRole = (string) $request->session()->get('impersonation_target_role', '');
        $destination = AdminSimulation::stop($request);

        SecurityAudit::record($request, 'auth.simulation_stopped', 'Admin returned from a simulated portal session.', [
            'target_user_id' => $targetUserId,
            'target_role' => $targetRole,
        ], 'info');

        return redirect($destination)->with('success', 'You have returned to your admin session.');
    }
}
