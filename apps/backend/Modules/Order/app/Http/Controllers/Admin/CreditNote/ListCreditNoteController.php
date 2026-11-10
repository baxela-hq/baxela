<?php

namespace Modules\Order\Http\Controllers\Admin\CreditNote;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Actions\Admin\CreditNote\ListCreditNotesAction;
use Modules\Order\Transformers\Admin\CreditNote\OrderCreditNoteResource;

class ListCreditNoteController extends Controller
{
    public function __construct(protected ListCreditNotesAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return OrderCreditNoteResource::collection($this->action->handle());
    }
}
