<?php

namespace Modules\Transfer\Transformers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the capabilities matrix — a module and the sections it
 * exposes to the data-transfer standard.
 */
class TransferModuleResource extends JsonResource
{
    /**
     * @param  array{module: string, supported: bool, sections: list<array<string, mixed>>}  $this->resource
     */
    public function toArray(Request $request): array
    {
        return [
            'module' => $this->resource['module'],
            'supported' => $this->resource['supported'],
            'sections' => $this->resource['sections'],
        ];
    }
}
