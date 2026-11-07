<?php

namespace Modules\Webhooks\Http\Controllers\Machine;

use App\Http\Controllers\Controller;

class MachineController extends Controller
{
    /**
     * The machine user's id — the ownership boundary (query-layer
     * enforcement, gap-C2 D14).
     */
    protected function machineUserId(): int
    {
        return (int) request()->user()->getKey();
    }
}
