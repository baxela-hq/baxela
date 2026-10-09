<?php

namespace Modules\Order\Transformers\Admin\Stats;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Schemas\Stats\StatsSchema;
use Modules\Order\Transformers\Admin\Order\OrderResource;

class StatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            StatsSchema::RES_TOTAL_REVENUE => $this->resource[StatsSchema::RES_TOTAL_REVENUE],
            StatsSchema::RES_REVENUE_CHANGE_PERCENT => $this->resource[StatsSchema::RES_REVENUE_CHANGE_PERCENT],
            StatsSchema::RES_ORDERS_COUNT => $this->resource[StatsSchema::RES_ORDERS_COUNT],
            StatsSchema::RES_ORDERS_CHANGE_PERCENT => $this->resource[StatsSchema::RES_ORDERS_CHANGE_PERCENT],
            StatsSchema::RES_PENDING_ORDERS_COUNT => $this->resource[StatsSchema::RES_PENDING_ORDERS_COUNT],
            StatsSchema::RES_CANCELLED_ORDERS_COUNT => $this->resource[StatsSchema::RES_CANCELLED_ORDERS_COUNT],
            StatsSchema::RES_PAID_ORDERS_COUNT => $this->resource[StatsSchema::RES_PAID_ORDERS_COUNT],
            StatsSchema::RES_AVG_ORDER_VALUE => $this->resource[StatsSchema::RES_AVG_ORDER_VALUE],
            StatsSchema::RES_REVENUE_BY_MONTH => $this->resource[StatsSchema::RES_REVENUE_BY_MONTH],
            StatsSchema::RES_ORDERS_PER_DAY => $this->resource[StatsSchema::RES_ORDERS_PER_DAY],
            StatsSchema::RES_ORDERS_BY_STATUS => $this->resource[StatsSchema::RES_ORDERS_BY_STATUS],
            StatsSchema::RES_TOP_PRODUCTS => $this->resource[StatsSchema::RES_TOP_PRODUCTS],
            StatsSchema::RES_RECENT_ORDERS => OrderResource::collection(
                $this->resource[StatsSchema::RES_RECENT_ORDERS]
            ),
        ];
    }
}
