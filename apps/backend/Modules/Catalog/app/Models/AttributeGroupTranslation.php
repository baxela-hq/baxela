<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema;

class AttributeGroupTranslation extends Model
{
    use HasFactory;

    protected $table = AttributeGroupTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeGroupTranslationSchema::ATTRIBUTE_GROUP_ID,
        AttributeGroupTranslationSchema::LANGUAGE_ID,
        AttributeGroupTranslationSchema::TITLE,
    ];

    public function attributeGroup(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class);
    }
}
