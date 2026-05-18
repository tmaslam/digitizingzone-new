<?php

namespace App\Http\Controllers;

use App\Support\TeamNavigation;
use App\Support\TeamWorkQueues;
use Illuminate\Http\Request;

class TeamDashboardController extends Controller
{
    public function index(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');

        $navCounts = TeamNavigation::counts($teamUser->user_id, (int) $teamUser->usre_type_id);

        return view('team.dashboard', [
            'teamUser' => $teamUser,
            'navCounts' => $navCounts,
            'queueNavigation' => TeamWorkQueues::navigation($navCounts),
            'currentQueueKey' => null,
        ]);
    }
}
