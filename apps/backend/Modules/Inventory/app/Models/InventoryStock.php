<?php

namespace Modules\Inventory\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Variant;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Inventory\Database\Factories\InventoryStockFactory;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $quantity
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Variant|null $variant
 */
class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = InventoryStockSchema::TABLE;

    protected $fillable = [
        InventoryStockSchema::VARIANT_ID,
        InventoryStockSchema::QUANTITY,
    ];

    protected static function newFactory(): InventoryStockFactory
    {
        return InventoryStockFactory::new();
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, InventoryStockSchema::VARIANT_ID);
    }
}
