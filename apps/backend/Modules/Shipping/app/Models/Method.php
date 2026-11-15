<?php

namespace Modules\Shipping\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shipping\Database\Factories\MethodFactory;
use Modules\Shipping\Schemas\Method\MethodSchema;

/**
 * @property int $id
 * @property string $code
 * @property bool $is_active
 * @property int $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, MethodTranslation> $translations
 * @property-read Collection<int, Rate> $rates
 */
class Method extends Model
{
    /** @use HasFactory<MethodFactory> */
    use HasFactory;

    protected $table = MethodSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        MethodSchema::CODE,
        MethodSchema::IS_ACTIVE,
        MethodSchema::POSITION,
    ];

    protected function casts(): array
    {
        return [
            MethodSchema::IS_ACTIVE => 'boolean',
        ];
    }

    protected static function newFactory(): MethodFactory
    {
        return MethodFactory::new();
    }

    /** @return HasMany<MethodTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(MethodTranslation::class);
    }
}
