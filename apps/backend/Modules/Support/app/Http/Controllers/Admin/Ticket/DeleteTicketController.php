<?php

namespace Modules\Support\Http\Controllers\Admin\Ticket;

use App\Http\Controllers\Controller;
use Modules\Support\Actions\Admin\Ticket\DeleteTicketAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteTicketController extends Controller
{
    public function __construct(protected DeleteTicketAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
