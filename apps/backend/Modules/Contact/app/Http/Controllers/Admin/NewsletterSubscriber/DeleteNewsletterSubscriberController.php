<?php

namespace Modules\Contact\Http\Controllers\Admin\NewsletterSubscriber;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Contact\Actions\Admin\NewsletterSubscriber\DeleteNewsletterSubscriberAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteNewsletterSubscriberController extends Controller
{
    public function __construct(protected DeleteNewsletterSubscriberAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
