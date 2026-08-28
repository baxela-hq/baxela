<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\AttributeValueFactory;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;

class AttributeValue extends Model
{
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

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, AttributeValueSchema::ATTRIBUTE_ID);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class);
    }
}
