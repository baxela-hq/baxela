<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\IdempotencyKeyFactory;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $principal_key
 * @property string $key
 * @property string $status
 * @property string|null $claim_token
 * @property array<string, mixed>|null $response
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $committed_at
 * @property CarbonInterface $created_at
 */
class IdempotencyKey extends Model
{
    use HasFactory;
    use TenantScopedTrait;

    protected $table = IdempotencyKeysSchema::TABLE;

    public $timestamps = false;

    protected $fillable = [
        IdempotencyKeysSchema::USER_ID,
        IdempotencyKeysSchema::PRINCIPAL_KEY,
        IdempotencyKeysSchema::KEY,
        IdempotencyKeysSchema::STATUS,
        IdempotencyKeysSchema::CLAIM_TOKEN,
        IdempotencyKeysSchema::RESPONSE,
        IdempotencyKeysSchema::EXPIRES_AT,
        IdempotencyKeysSchema::COMMITTED_AT,
    ];

    protected $casts = [
        IdempotencyKeysSchema::RESPONSE => 'array',
        IdempotencyKeysSchema::EXPIRES_AT => 'datetime',
        IdempotencyKeysSchema::COMMITTED_AT => 'datetime',
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
