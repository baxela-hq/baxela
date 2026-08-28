<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;

// use Modules\Catalog\Database\Factories\OptionFactory;

class Option extends Model
{
    use BelongToProductTrait;
    use HasFactory;

    protected $table = OptionSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        OptionSchema::POSITION,
    ];

    // protected static function newFactory(): OptionFactory
    // {
    //     // return OptionFactory::new();
    // }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class, OptionValueSchema::OPTION_ID,
            OptionValueSchema::ID);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(OptionTranslation::class);
    }
}
