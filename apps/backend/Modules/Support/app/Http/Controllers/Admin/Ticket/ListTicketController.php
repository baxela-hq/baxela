<?php

namespace Modules\Support\Http\Controllers\Admin\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Support\Actions\Admin\Ticket\ListTicketAction;
use Modules\Support\Transformers\Admin\Ticket\TicketResource;

class ListTicketController extends Controller
{
    public function __construct(protected ListTicketAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return TicketResource::collection($this->action->handle());
    }
}
