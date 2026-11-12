<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeTypeEnum;

/**
 * A single-use challenge bridging password verification and the second
 * factor (S.1 D3/D4). The raw 64-char hex token is shown to the client
 * once; only its SHA-256 hash is stored as the PK. Context (device,
 * remember, cart token) is encrypted; nulled on consumption.
 *
 * @mixin Builder
 */
class TwoFactorChallenge extends Model
{
    public const int MAX_FAILED_ATTEMPTS = 5;

    public const int MAX_ACTIVE_SIGN_IN = 3;

    protected $table = TwoFactorChallengeSchema::TABLE;

    /** The PK is the token hash, not an auto-increment id. */
    protected $primaryKey = TwoFactorChallengeSchema::TOKEN_HASH;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        TwoFactorChallengeSchema::TOKEN_HASH,
        TwoFactorChallengeSchema::USER_ID,
        TwoFactorChallengeSchema::TYPE,
        TwoFactorChallengeSchema::CONTEXT,
        TwoFactorChallengeSchema::FAILED_ATTEMPTS,
        TwoFactorChallengeSchema::EXPIRES_AT,
        TwoFactorChallengeSchema::CONSUMED_AT,
    ];

    protected $hidden = [
        TwoFactorChallengeSchema::CONTEXT,
    ];

    protected function casts(): array
    {
        return [
            TwoFactorChallengeSchema::CONTEXT => 'encrypted:array',
            TwoFactorChallengeSchema::FAILED_ATTEMPTS => 'integer',
            TwoFactorChallengeSchema::EXPIRES_AT => 'datetime',
            TwoFactorChallengeSchema::CONSUMED_AT => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: challenges that can still be used — not consumed, not
     * expired, under the failed-attempt cap.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull(TwoFactorChallengeSchema::CONSUMED_AT)
            ->where(TwoFactorChallengeSchema::EXPIRES_AT, '>', now())
            ->where(TwoFactorChallengeSchema::FAILED_ATTEMPTS, '<', self::MAX_FAILED_ATTEMPTS);
    }

    public function scopeOfType(Builder $query, TwoFactorChallengeTypeEnum $type): Builder
    {
        return $query->where(TwoFactorChallengeSchema::TYPE, $type->value);
    }

    public function isExpired(): bool
    {
        return $this->{TwoFactorChallengeSchema::EXPIRES_AT}->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->{TwoFactorChallengeSchema::CONSUMED_AT} !== null;
    }

    public function isExhausted(): bool
    {
        return $this->{TwoFactorChallengeSchema::FAILED_ATTEMPTS} >= self::MAX_FAILED_ATTEMPTS;
    }
}
