<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema;

// use Modules\Catalog\Database\Factories\OptionFactory;

class OptionTranslation extends Model
{
    use BelongToProductTrait;
    use HasFactory;

    protected $table = OptionTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        OptionTranslationSchema::OPTION_ID,
        OptionTranslationSchema::LANGUAGE_ID,
        OptionTranslationSchema::TITLE,
        OptionTranslationSchema::SLUG,
    ];

    // protected static function newFactory(): OptionFactory
    // {
    //     // return OptionFactory::new();
    // }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
