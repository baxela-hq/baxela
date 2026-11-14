<?php

namespace Modules\Transfer\Transformers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the capabilities matrix — a module, the sections it
 * exposes to the data-transfer standard, and (for wired modules) its
 * own data-endpoint URLs relative to the API root.
 *
 * @property array{module: string, supported: bool, endpoints: array{export: string, import_preview: string, import: string, imports: string}|null, sections: list<array<string, mixed>>} $resource
 */
class TransferModuleResource extends JsonResource
{
    /**
     * @return array{module: string, supported: bool, endpoints: array{export: string, import_preview: string, import: string, imports: string}|null, sections: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        return [
            'module' => $this->resource['module'],
            'supported' => $this->resource['supported'],
            'endpoints' => $this->resource['endpoints'],
            'sections' => $this->resource['sections'],
        ];
    }
}
