<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxClass;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxClass\DeleteTaxClassAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteTaxClassController extends Controller
{
    public function __construct(protected DeleteTaxClassAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
