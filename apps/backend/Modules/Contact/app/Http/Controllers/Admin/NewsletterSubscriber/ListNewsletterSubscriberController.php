<?php

namespace Modules\Contact\Http\Controllers\Admin\NewsletterSubscriber;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Contact\Actions\Admin\NewsletterSubscriber\ListNewsletterSubscriberAction;
use Modules\Contact\Transformers\Admin\NewsletterSubscriber\NewsletterSubscriberResource;

class ListNewsletterSubscriberController extends Controller
{
    public function __construct(protected ListNewsletterSubscriberAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return NewsletterSubscriberResource::collection($this->action->handle());
    }
}
