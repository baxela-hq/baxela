<?php

namespace Modules\Transfer\Http\Controllers\Admin\Capabilities;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Transfer\Actions\Admin\Capabilities\ShowTransferCapabilitiesAction;
use Modules\Transfer\Transformers\Admin\TransferModuleResource;

class ShowTransferCapabilitiesController extends Controller
{
    public function __construct(protected ShowTransferCapabilitiesAction $action) {}

    /**
     * The module coverage matrix of the data-transfer standard.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return TransferModuleResource::collection($this->action->handle());
    }
}
