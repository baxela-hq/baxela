<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema;

/**
 * @property int $id
 * @property int $attribute_value_id
 * @property int $language_id
 * @property string $title
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AttributeValueTranslation /**
 * @property int $id
 * @property int $attribute_value_id
 * @property int $language_id
 * @property string $title
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
extends Model
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

    /** @return BelongsTo<AttributeValue, $this> */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }
}
