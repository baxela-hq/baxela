<?php

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shipping\Database\Factories\MethodFactory;
use Modules\Shipping\Schemas\Method\MethodSchema;

class Method extends Model
{
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

    public function translations(): HasMany
    {
        return $this->hasMany(MethodTranslation::class);
    }
}
