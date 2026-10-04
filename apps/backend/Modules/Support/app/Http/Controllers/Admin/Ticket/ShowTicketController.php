<?php

namespace Modules\Support\Http\Controllers\Admin\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Support\Actions\Admin\Ticket\ShowTicketAction;
use Modules\Support\Transformers\Admin\Ticket\TicketResource;

class ShowTicketController extends Controller
{
    public function __construct(protected ShowTicketAction $action) {}

    public function __invoke(string $id, Request $request): TicketResource
    {
        return TicketResource::make($this->action->handle($id));
    }
}
