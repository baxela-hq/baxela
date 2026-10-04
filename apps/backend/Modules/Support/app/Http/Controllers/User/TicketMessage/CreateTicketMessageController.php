<?php

namespace Modules\Support\Http\Controllers\User\TicketMessage;

use App\Http\Controllers\Controller;
use Modules\Support\Actions\User\TicketMessage\CreateTicketMessageAction;
use Modules\Support\Http\Requests\User\TicketMessage\CreateTicketMessageRequest;
use Modules\Support\Transformers\User\TicketMessage\TicketMessageResource;

class CreateTicketMessageController extends Controller
{
    public function __construct(protected CreateTicketMessageAction $action) {}

    public function __invoke(string $id, CreateTicketMessageRequest $request): TicketMessageResource
    {
        $record = $this->action->handle($id, $request->validated());

        return TicketMessageResource::make($record);
    }
}
