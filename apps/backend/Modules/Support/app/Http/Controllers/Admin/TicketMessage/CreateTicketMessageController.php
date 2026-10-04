<?php

namespace Modules\Support\Http\Controllers\Admin\TicketMessage;

use App\Http\Controllers\Controller;
use Modules\Support\Actions\Admin\TicketMessage\CreateTicketMessageAction;
use Modules\Support\Http\Requests\Admin\TicketMessage\CreateTicketMessageRequest;
use Modules\Support\Transformers\Admin\TicketMessage\TicketMessageResource;

class CreateTicketMessageController extends Controller
{
    public function __construct(protected CreateTicketMessageAction $action) {}

    public function __invoke(string $id, CreateTicketMessageRequest $request): TicketMessageResource
    {
        $record = $this->action->handle($id, $request->validated());

        return TicketMessageResource::make($record);
    }
}
