<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderComment;

class TeamNavigation
{
    public static function counts(int $teamUserId, ?int $teamUserTypeId = null): array
    {
        $ids = [$teamUserId];
        $teamUser = null;

        if ($teamUserTypeId !== null) {
            $teamUser = AdminUser::query()->find($teamUserId);

            if (! $teamUser) {
                $teamUser = new AdminUser([
                    'user_id' => $teamUserId,
                    'usre_type_id' => $teamUserTypeId,
                ]);
            }

            $ids = TeamAccess::accessibleUserIds($teamUser);
        }

        $memberIds = $teamUser && (int) $teamUser->usre_type_id === AdminUser::TYPE_SUPERVISOR
            ? TeamAccess::teamMembers($teamUser)->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return [
            'new_orders' => Order::query()
                ->active()
                ->whereIn('order_type', ['order', 'vector', 'color'])
                ->where('status', 'Underprocess')
                ->whereIn('assign_to', $ids)
                ->where(function ($query) {
                    $query->whereNull('working')->orWhere('working', '');
                })
                ->count(),
            'working_orders' => Order::query()
                ->active()
                ->whereIn('assign_to', $ids)
                ->whereIn('order_type', ['order', 'vector', 'color'])
                ->where('status', 'Underprocess')
                ->where(function ($query) {
                    $query->whereNotNull('working')->where('working', '!=', '');
                })
                ->count(),
            'disapproved_orders' => Order::query()
                ->active()
                ->whereIn('assign_to', $ids)
                ->whereIn('order_type', ['order', 'vector', 'color'])
                ->whereIn('status', ['disapprove', 'disapproved'])
                ->count(),
            'quotes' => Order::query()
                ->active()
                ->whereIn('order_type', ['quote', 'digitzing', 'q-vector', 'qcolor'])
                ->where('status', 'Underprocess')
                ->whereIn('assign_to', $ids)
                ->count(),
            'quick_quotes' => Order::query()
                ->active()
                ->where('order_type', 'qquote')
                ->where('status', 'Underprocess')
                ->whereIn('assign_to', $ids)
                ->count(),
            'ready_review' => $teamUserTypeId === AdminUser::TYPE_SUPERVISOR
                ? Order::query()
                    ->active()
                    ->where('status', 'Ready')
                    ->whereIn('assign_to', $memberIds)
                    ->count()
                : 0,
            'verified_jobs' => $teamUserTypeId === AdminUser::TYPE_SUPERVISOR
                ? OrderComment::query()
                    ->where('comment_source', 'supervisorReview')
                    ->whereIn('order_id', Order::query()
                        ->active()
                        ->whereIn('assign_to', $memberIds)
                        ->pluck('order_id'))
                    ->distinct('order_id')
                    ->count('order_id')
                : 0,
            'team_members' => $teamUserTypeId === AdminUser::TYPE_SUPERVISOR ? count($memberIds) : 0,
        ];
    }
}
