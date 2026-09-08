<?php

namespace Modules\Contact\Http\Controllers\Admin\ContactMessage;

use App\Http\Controllers\Controller;
use Modules\Contact\Actions\Admin\ContactMessage\DeleteContactMessageAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteContactMessageController extends Controller
{
    public function __construct(protected DeleteContactMessageAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
