<?php

namespace Modules\Contact\Http\Controllers\Admin\NewsletterSubscriber;

use App\Http\Controllers\Controller;
use Modules\Contact\Actions\Admin\NewsletterSubscriber\UpdateNewsletterSubscriberStatusAction;
use Modules\Contact\Http\Requests\Admin\NewsletterSubscriber\UpdateNewsletterSubscriberStatusRequest;
use Modules\Contact\Transformers\Admin\NewsletterSubscriber\NewsletterSubscriberResource;

class UpdateNewsletterSubscriberStatusController extends Controller
{
    public function __construct(protected UpdateNewsletterSubscriberStatusAction $action) {}

    public function __invoke(string $id, UpdateNewsletterSubscriberStatusRequest $request): NewsletterSubscriberResource
    {
        return new NewsletterSubscriberResource($this->action->handle($id, $request->validated()));
    }
}
