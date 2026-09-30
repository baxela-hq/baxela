<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Variant;
use Modules\Inventory\Database\Factories\InventoryStockFactory;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

class InventoryStock extends Model
{
    use HasFactory;

    protected $table = InventoryStockSchema::TABLE;

    protected $fillable = [
        InventoryStockSchema::VARIANT_ID,
        InventoryStockSchema::QUANTITY,
    ];

    protected static function newFactory(): InventoryStockFactory
    {
        return InventoryStockFactory::new();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, InventoryStockSchema::VARIANT_ID);
    }
}
