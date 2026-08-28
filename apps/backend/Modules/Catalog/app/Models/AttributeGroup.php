<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\AttributeGroupFactory;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;

class AttributeGroup extends Model
{
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

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class, AttributeSchema::GROUP_ID);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeGroupTranslation::class);
    }
}
