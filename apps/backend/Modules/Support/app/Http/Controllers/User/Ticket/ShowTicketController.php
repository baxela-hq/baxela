<?php

namespace Modules\Support\Http\Controllers\User\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Support\Actions\User\Ticket\ShowTicketAction;
use Modules\Support\Transformers\User\Ticket\TicketResource;

class ShowTicketController extends Controller
{
    public function __construct(protected ShowTicketAction $action) {}

    public function __invoke(string $id, Request $request): TicketResource
    {
        return TicketResource::make($this->action->handle($id));
    }
}
