<?php

namespace Modules\Contact\Http\Controllers\Public\ContactMessage;

use App\Http\Controllers\Controller;
use Modules\Contact\Actions\Public\ContactMessage\SubmitContactMessageAction;
use Modules\Contact\Http\Requests\Public\ContactMessage\SubmitContactMessageRequest;
use Modules\Contact\Transformers\Public\ContactMessage\ContactMessageResource;

class SubmitContactMessageController extends Controller
{
    public function __construct(protected SubmitContactMessageAction $action) {}

    public function __invoke(SubmitContactMessageRequest $request): ContactMessageResource
    {
        $record = $this->action->handle($request->validated(), $request->ip());

        return ContactMessageResource::make($record);
    }
}
