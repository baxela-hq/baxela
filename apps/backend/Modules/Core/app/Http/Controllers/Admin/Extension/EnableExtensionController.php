<?php

namespace Modules\Core\Http\Controllers\Admin\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Actions\Admin\Extension\ToggleExtensionAction;
use Modules\Core\Http\Requests\Admin\Extension\ToggleExtensionRequest;
use Modules\Core\Transformers\Admin\Extension\ExtensionResource;

class EnableExtensionController extends Controller
{
    public function __construct(protected ToggleExtensionAction $action) {}

    public function __invoke(ToggleExtensionRequest $request, string $vendor, string $package): JsonResource
    {
        return ExtensionResource::make($this->action->handle($vendor.'/'.$package, true));
    }
}
