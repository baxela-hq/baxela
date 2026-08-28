<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\IdempotencyKeyFactory;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $table = IdempotencyKeysSchema::TABLE;

    public $timestamps = false;

    protected $fillable = [
        IdempotencyKeysSchema::USER_ID,
        IdempotencyKeysSchema::KEY,
        IdempotencyKeysSchema::RESPONSE,
        IdempotencyKeysSchema::EXPIRED_AT,
    ];

    protected $casts = [
        IdempotencyKeysSchema::RESPONSE => 'array',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    protected static function newFactory(): IdempotencyKeyFactory
    {
        return IdempotencyKeyFactory::new();
    }
}
