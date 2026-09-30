<?php

namespace Modules\Contact\Http\Controllers\Public\NewsletterSubscriber;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Contact\Actions\Public\NewsletterSubscriber\SubscribeNewsletterAction;
use Modules\Contact\Http\Requests\Public\NewsletterSubscriber\SubscribeNewsletterRequest;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Transformers\Admin\NewsletterSubscriber\NewsletterSubscriberResource;

class SubscribeNewsletterController extends Controller
{
    public function __construct(protected SubscribeNewsletterAction $action) {}

    public function __invoke(SubscribeNewsletterRequest $request): JsonResponse
    {
        /** @var NewsletterSubscriber $record */
        $record = $this->action->handle($request->validated(), app()->getLocale());

        return NewsletterSubscriberResource::make($record)
            ->response()
            ->setStatusCode(201);
    }
}
