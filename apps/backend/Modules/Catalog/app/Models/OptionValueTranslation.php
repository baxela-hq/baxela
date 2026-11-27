<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

// use Modules\Catalog\Database\Factories\OptionFactory;

/**
 * @property int $id
 * @property int $option_value_id
 * @property int $language_id
 * @property string $title
 * @property string $slug
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class OptionValueTranslation extends Model
{
    use BelongToProductTrait;
    use HasFactory;
    use TenantScopedTrait;

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

    /** @return BelongsTo<OptionValue, $this> */
    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
