<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;

// use Modules\Catalog\Database\Factories\OptionFactory;

/**
 * @property int $id
 * @property int $option_id
 * @property int|null $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, OptionValueTranslation> $translations
 */
class OptionValue extends Model
{
    use BelongToProductTrait;
    use HasFactory;

    protected $table = OptionValueSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        OptionValueSchema::OPTION_ID,
        OptionValueSchema::POSITION,
    ];

    // protected static function newFactory(): OptionFactory
    // {
    //     // return OptionFactory::new();
    // }

    /** @return BelongsTo<Option, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, OptionValueSchema::OPTION_ID);
    }

    /** @return BelongsToMany<Variant, $this> */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(Variant::class, VariantOptionValueSchema::TABLE,
            VariantOptionValueSchema::OPTION_VALUE_ID, VariantOptionValueSchema::VARIANT_ID);
    }

    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            Variant::class,
        )->distinct();
    }

    /** @return HasMany<OptionValueTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(OptionValueTranslation::class);
    }
}
