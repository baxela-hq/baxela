<?php

namespace Modules\Order\Http\Controllers\Admin\OrderItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShowOrderItemController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return response()->json([]);
    }
}
