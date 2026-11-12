<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Schemas\SignInLockout\SignInLockoutSchema;

/**
 * S.4 account lockout state, one row per canonical-email hash (D2 rev 7).
 * The primary key is the email hash, not an auto-increment id, so the
 * unique constraint arbitrates concurrent row creation (D2a) and raw
 * addresses never land in the database. Rows are inert once their lock is
 * past AND the failure counter is stale — pruned daily.
 *
 * @mixin Builder
 */
class SignInLockout extends Model
{
    protected $table = SignInLockoutSchema::TABLE;

    protected $primaryKey = SignInLockoutSchema::EMAIL_HASH;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        SignInLockoutSchema::EMAIL_HASH,
        SignInLockoutSchema::FAILED_ATTEMPTS,
        SignInLockoutSchema::LAST_FAILED_AT,
        SignInLockoutSchema::LOCKED_UNTIL,
        SignInLockoutSchema::LOCKED_AT,
    ];

    protected function casts(): array
    {
        return [
            SignInLockoutSchema::FAILED_ATTEMPTS => 'integer',
            SignInLockoutSchema::LAST_FAILED_AT => 'datetime',
            SignInLockoutSchema::LOCKED_UNTIL => 'datetime',
            SignInLockoutSchema::LOCKED_AT => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->{SignInLockoutSchema::LOCKED_UNTIL} !== null
            && $this->{SignInLockoutSchema::LOCKED_UNTIL}->isFuture();
    }
}
