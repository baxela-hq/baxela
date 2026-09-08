<?php

namespace Modules\Contact\Http\Controllers\Admin\ContactMessage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Contact\Actions\Admin\ContactMessage\ShowContactMessageAction;
use Modules\Contact\Transformers\Admin\ContactMessage\ContactMessageResource;

class ShowContactMessageController extends Controller
{
    public function __construct(protected ShowContactMessageAction $action) {}

    public function __invoke(string $id, Request $request): ContactMessageResource
    {
        return ContactMessageResource::make($this->action->handle($id));
    }
}
