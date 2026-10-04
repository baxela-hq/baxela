<?php

namespace Modules\Support\Http\Controllers\Admin\Ticket;

use App\Http\Controllers\Controller;
use Modules\Support\Actions\Admin\Ticket\UpdateTicketStatusAction;
use Modules\Support\Http\Requests\Admin\Ticket\UpdateTicketStatusRequest;
use Modules\Support\Transformers\Admin\Ticket\TicketResource;

class UpdateTicketStatusController extends Controller
{
    public function __construct(protected UpdateTicketStatusAction $action) {}

    public function __invoke(string $id, UpdateTicketStatusRequest $request): TicketResource
    {
        return TicketResource::make($this->action->handle($id, $request->validated()));
    }
}
