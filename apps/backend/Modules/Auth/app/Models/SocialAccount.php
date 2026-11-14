<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Schemas\SocialAccount\SocialAccountSchema;
use Modules\Auth\Schemas\SocialAccount\SocialProviderEnum;

/**
 * A third-party identity (e.g. a Google account) linked to a local user.
 * The provider pair is the canonical identity — the immutable provider id
 * (Google's `sub`) is looked up first and the email is never used as the
 * long-term identifier. No provider access/refresh tokens are stored: the
 * sign-in flow only verifies ID tokens and never calls provider APIs.
 */
class SocialAccount extends Model
{
    protected $table = SocialAccountSchema::TABLE;

    protected $fillable = [
        SocialAccountSchema::USER_ID,
        SocialAccountSchema::PROVIDER,
        SocialAccountSchema::PROVIDER_USER_ID,
    ];

    protected function casts(): array
    {
        return [
            SocialAccountSchema::PROVIDER => SocialProviderEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
