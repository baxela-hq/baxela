<?php

namespace Modules\Contact\Http\Controllers\Admin\ContactMessage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Contact\Actions\Admin\ContactMessage\ListContactMessageAction;
use Modules\Contact\Transformers\Admin\ContactMessage\ContactMessageResource;

class ListContactMessageController extends Controller
{
    public function __construct(protected ListContactMessageAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ContactMessageResource::collection($this->action->handle());
    }
}
