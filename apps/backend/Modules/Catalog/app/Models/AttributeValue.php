<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\AttributeValueFactory;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

/**
 * @property int $id
 * @property int $attribute_id
 * @property int|null $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    protected $table = AttributeValueSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeValueSchema::ATTRIBUTE_ID,
        AttributeValueSchema::POSITION,
    ];

    protected static function newFactory(): AttributeValueFactory
    {
        return AttributeValueFactory::new();
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, AttributeValueSchema::ATTRIBUTE_ID);
    }

    /** @return HasMany<AttributeValueTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class);
    }
}
