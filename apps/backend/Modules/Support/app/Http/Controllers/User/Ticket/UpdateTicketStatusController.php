<?php

namespace Modules\Support\Http\Controllers\User\Ticket;

use App\Http\Controllers\Controller;
use Modules\Support\Actions\User\Ticket\UpdateTicketStatusAction;
use Modules\Support\Http\Requests\User\Ticket\UpdateTicketStatusRequest;
use Modules\Support\Transformers\User\Ticket\TicketResource;

class UpdateTicketStatusController extends Controller
{
    public function __construct(protected UpdateTicketStatusAction $action) {}

    public function __invoke(string $id, UpdateTicketStatusRequest $request): TicketResource
    {
        return TicketResource::make($this->action->handle($id, $request->validated()));
    }
}
