<?php

namespace Modules\Core\Transformers\Admin\Extension;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Schemas\Extension\ExtensionSchema;

class ExtensionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ExtensionSchema::SLUG => $this->resource[ExtensionSchema::SLUG],
            ExtensionSchema::ENABLED => $this->resource[ExtensionSchema::ENABLED],
            ExtensionSchema::RES_INSTALLED => $this->resource[ExtensionSchema::RES_INSTALLED],
            ExtensionSchema::RES_VERSION => $this->resource[ExtensionSchema::RES_VERSION],
            ExtensionSchema::RES_SKIP_REASON => $this->resource[ExtensionSchema::RES_SKIP_REASON],
        ];
    }
}
