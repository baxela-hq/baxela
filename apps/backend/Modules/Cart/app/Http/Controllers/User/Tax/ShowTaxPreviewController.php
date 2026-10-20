<?php

namespace Modules\Cart\Http\Controllers\User\Tax;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\Tax\ShowTaxPreviewAction;
use Modules\Cart\Http\Requests\User\Tax\TaxPreviewRequest;
use Modules\Cart\Transformers\User\Tax\TaxPreviewResource;

class ShowTaxPreviewController extends Controller
{
    public function __construct(protected ShowTaxPreviewAction $action) {}

    public function __invoke(TaxPreviewRequest $request): TaxPreviewResource
    {
        return new TaxPreviewResource($this->action->handle($request));
    }
}
