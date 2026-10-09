<?php

namespace Modules\Auth\Transformers\Admin\Stats;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Schemas\Stats\StatsSchema;

class StatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            StatsSchema::RES_CUSTOMERS_COUNT => $this->resource[StatsSchema::RES_CUSTOMERS_COUNT],
            StatsSchema::RES_NEW_CUSTOMERS_THIS_MONTH => $this->resource[StatsSchema::RES_NEW_CUSTOMERS_THIS_MONTH],
            StatsSchema::RES_CUSTOMERS_CHANGE_PERCENT => $this->resource[StatsSchema::RES_CUSTOMERS_CHANGE_PERCENT],
        ];
    }
}
