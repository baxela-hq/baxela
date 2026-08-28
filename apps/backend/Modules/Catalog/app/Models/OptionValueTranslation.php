<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;

// use Modules\Catalog\Database\Factories\OptionFactory;

class OptionValueTranslation extends Model
{
    use BelongToProductTrait;
    use HasFactory;

    protected $table = OVTSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        OVTSchema::OPTION_VALUE_ID,
        OVTSchema::LANGUAGE_ID,
        OVTSchema::TITLE,
        OVTSchema::SLUG,
    ];

    // protected static function newFactory(): OptionFactory
    // {
    //     // return OptionFactory::new();
    // }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
