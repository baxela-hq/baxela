<?php

namespace Modules\Inventory\Transformers\Admin\InventoryStock;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

class InventoryStockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            InventoryStockSchema::VARIANT_ID => $this->resource->{InventoryStockSchema::VARIANT_ID},
            InventoryStockSchema::QUANTITY => $this->resource->{InventoryStockSchema::QUANTITY},
        ];
    }
}
