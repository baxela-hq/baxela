<?php

namespace Modules\Catalog\Models;

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

class Attribute extends Model
{
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, AttributeSchema::GROUP_ID);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, AttributeValueSchema::ATTRIBUTE_ID,
            AttributeValueSchema::ID);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, CategoryAttributeSchema::TABLE,
            CategoryAttributeSchema::ATTRIBUTE_ID, CategoryAttributeSchema::CATEGORY_ID)
            ->withPivot(CategoryAttributeSchema::POSITION);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class);
    }
}
