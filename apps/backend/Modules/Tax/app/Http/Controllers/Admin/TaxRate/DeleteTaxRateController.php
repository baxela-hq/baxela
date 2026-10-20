<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxRate;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxRate\DeleteTaxRateAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteTaxRateController extends Controller
{
    public function __construct(protected DeleteTaxRateAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
