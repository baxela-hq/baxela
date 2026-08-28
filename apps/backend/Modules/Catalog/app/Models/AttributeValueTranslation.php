<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema;

class AttributeValueTranslation extends Model
{
    use HasFactory;

    protected $table = AttributeValueTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeValueTranslationSchema::ATTRIBUTE_VALUE_ID,
        AttributeValueTranslationSchema::LANGUAGE_ID,
        AttributeValueTranslationSchema::TITLE,
    ];

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }
}
