<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema;

class AttributeTranslation extends Model
{
    use HasFactory;

    protected $table = AttributeTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeTranslationSchema::ATTRIBUTE_ID,
        AttributeTranslationSchema::LANGUAGE_ID,
        AttributeTranslationSchema::TITLE,
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
