<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema;

// use Modules\Catalog\Database\Factories\OptionFactory;

/**
 * @property int $id
 * @property int $option_id
 * @property int|null $language_id
 * @property string $title
 * @property string $slug
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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

    /** @return BelongsTo<Option, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
