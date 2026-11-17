<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\AttributeGroupFactory;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;

/**
 * @property int $id
 * @property int|null $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AttributeGroup extends Model
{
    /** @use HasFactory<AttributeGroupFactory> */
    use HasFactory;

    protected $table = AttributeGroupSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeGroupSchema::POSITION,
    ];

    protected static function newFactory(): AttributeGroupFactory
    {
        return AttributeGroupFactory::new();
    }

    /** @return HasMany<Attribute, $this> */
    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class, AttributeSchema::GROUP_ID);
    }

    /** @return HasMany<AttributeGroupTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeGroupTranslation::class);
    }
}
