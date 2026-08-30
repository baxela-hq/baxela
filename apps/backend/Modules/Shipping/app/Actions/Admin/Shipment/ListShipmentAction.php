<?php

namespace Modules\Shipping\Actions\Admin\Shipment;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Shipping\Models\Shipment;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListShipmentAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = ShipmentSchema::TABLE.'.'.ShipmentSchema::ID;

        return QueryBuilder::for(Shipment::class)
            ->allowedFilters(
                AllowedFilter::exact(ShipmentSchema::STATUS),
                AllowedFilter::exact(ShipmentSchema::ORDER_ID),
            )
            ->allowedSorts(
                ShipmentSchema::ID,
                ShipmentSchema::SHIPPED_AT,
                ShipmentSchema::DELIVERED_AT,
            )
            ->select([
                $id,
                ShipmentSchema::ORDER_ID,
                ShipmentSchema::CARRIER_NAME,
                ShipmentSchema::TRACKING_NUMBER,
                ShipmentSchema::TRACKING_URL,
                ShipmentSchema::STATUS,
                ShipmentSchema::SHIPPED_AT,
                ShipmentSchema::DELIVERED_AT,
                ShipmentSchema::NOTES,
                ShipmentSchema::TABLE.'.'.ShipmentSchema::CREATED_AT,
                ShipmentSchema::TABLE.'.'.ShipmentSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
