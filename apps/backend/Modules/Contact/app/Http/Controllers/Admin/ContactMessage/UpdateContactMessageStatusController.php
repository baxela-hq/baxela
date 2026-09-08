<?php

namespace Modules\Contact\Http\Controllers\Admin\ContactMessage;

use App\Http\Controllers\Controller;
use Modules\Contact\Actions\Admin\ContactMessage\UpdateContactMessageStatusAction;
use Modules\Contact\Http\Requests\Admin\ContactMessage\UpdateContactMessageStatusRequest;
use Modules\Contact\Transformers\Admin\ContactMessage\ContactMessageResource;

class UpdateContactMessageStatusController extends Controller
{
    public function __construct(protected UpdateContactMessageStatusAction $action) {}

    public function __invoke(string $id, UpdateContactMessageStatusRequest $request): ContactMessageResource
    {
        return ContactMessageResource::make($this->action->handle($id, $request->validated()));
    }
}
