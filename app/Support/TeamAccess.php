<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\SupervisorTeamMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TeamAccess
{
    public static function accessibleUserIds(AdminUser $teamUser): array
    {
        $ids = [$teamUser->user_id];

        if ((int) $teamUser->usre_type_id === AdminUser::TYPE_SUPERVISOR) {
            $ids = array_merge($ids, self::supervisorMemberIds($teamUser));
        }

        $teamPortalIds = AdminUser::query()
            ->teamPortalUsers()
            ->active()
            ->where('is_active', 1)
            ->whereIn('user_id', array_unique(array_map('intval', $ids)))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique($teamPortalIds));
    }

    public static function teamMembers(AdminUser $teamUser): Collection
    {
        if ((int) $teamUser->usre_type_id !== AdminUser::TYPE_SUPERVISOR) {
            return collect();
        }

        $memberIds = self::supervisorMemberIds($teamUser);

        if ($memberIds === []) {
            return collect();
        }

        return AdminUser::query()
            ->teams()
            ->active()
            ->where('is_active', 1)
            ->whereIn('user_id', $memberIds)
            ->orderBy('user_name')
            ->get();
    }

    public static function assignableUsers(AdminUser $teamUser): Collection
    {
        if ((int) $teamUser->usre_type_id !== AdminUser::TYPE_SUPERVISOR) {
            return collect([$teamUser]);
        }

        $ids = self::accessibleUserIds($teamUser);

        return AdminUser::query()
            ->teamPortalUsers()
            ->active()
            ->where('is_active', 1)
            ->whereIn('user_id', $ids)
            ->orderByRaw('CASE WHEN usre_type_id = ? THEN 0 ELSE 1 END', [AdminUser::TYPE_SUPERVISOR])
            ->orderBy('user_name')
            ->get();
    }

    public static function canManageUser(AdminUser $teamUser, int $managedUserId): bool
    {
        return in_array($managedUserId, self::accessibleUserIds($teamUser), true);
    }

    private static function supervisorMemberIds(AdminUser $teamUser): array
    {
        $memberIds = [];

        if (Schema::hasTable('supervisor_team_members')) {
            $memberIds = SupervisorTeamMember::query()
                ->active()
                ->where('supervisor_user_id', $teamUser->user_id)
                ->pluck('member_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $fallbackIds = AdminUser::query()
            ->teams()
            ->active()
            ->where('is_active', 1)
            ->where('register_by', $teamUser->user_name)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($memberIds, $fallbackIds)));
    }
}
