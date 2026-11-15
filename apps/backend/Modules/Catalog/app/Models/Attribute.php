<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\AttributeFactory;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;

/**
 * @property int $id
 * @property int $group_id
 * @property string $code
 * @property AttributeTypeEnum $data_type
 * @property bool $is_filterable
 * @property int|null $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    protected $table = AttributeSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeSchema::GROUP_ID,
        AttributeSchema::CODE,
        AttributeSchema::DATA_TYPE,
        AttributeSchema::IS_FILTERABLE,
        AttributeSchema::POSITION,
    ];

    protected function casts(): array
    {
        return [
            AttributeSchema::DATA_TYPE => AttributeTypeEnum::class,
            AttributeSchema::IS_FILTERABLE => 'boolean',
        ];
    }

    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }

    /** @return BelongsTo<AttributeGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, AttributeSchema::GROUP_ID);
    }

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, AttributeValueSchema::ATTRIBUTE_ID,
            AttributeValueSchema::ID);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, CategoryAttributeSchema::TABLE,
            CategoryAttributeSchema::ATTRIBUTE_ID, CategoryAttributeSchema::CATEGORY_ID)
            ->withPivot(CategoryAttributeSchema::POSITION);
    }

    /** @return HasMany<AttributeTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class);
    }
}
