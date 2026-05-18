<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class TeamDesignInfoController extends Controller
{
    public function download(Request $request)
    {
        $order = Order::query()->findOrFail((int) $request->query('design_id'));

        return $order->order_type === 'qquote'
            ? app(TeamQuickQuoteController::class)->exportDesignInfo($request)
            : app(TeamOrderDetailController::class)->exportDesignInfo($request);
    }
}
