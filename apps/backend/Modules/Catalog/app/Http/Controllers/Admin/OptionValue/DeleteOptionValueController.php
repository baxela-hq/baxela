<?php

namespace Modules\Catalog\Http\Controllers\Admin\OptionValue;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\OptionValue\DeleteOptionValueAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteOptionValueController extends Controller
{
    public function __construct(protected DeleteOptionValueAction $action) {}

    public function __invoke(string $id, string $valueId): Response
    {
        $this->action->handle($valueId);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
