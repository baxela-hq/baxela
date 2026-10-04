<?php

namespace Modules\Support\Http\Controllers\User\Ticket;

use App\Http\Controllers\Controller;
use Modules\Support\Actions\User\Ticket\CreateTicketAction;
use Modules\Support\Http\Requests\User\Ticket\CreateTicketRequest;
use Modules\Support\Transformers\User\Ticket\TicketResource;

class CreateTicketController extends Controller
{
    public function __construct(protected CreateTicketAction $action) {}

    public function __invoke(CreateTicketRequest $request): TicketResource
    {
        $record = $this->action->handle($request->validated());

        return TicketResource::make($record);
    }
}
