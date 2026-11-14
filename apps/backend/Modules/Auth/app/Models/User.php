<?php

namespace Modules\Auth\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Database\factories\UserFactory;
use Modules\Auth\Schemas\SocialAccount\SocialAccountSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $mobile_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_active
 * @property string|null $comment
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $table = UserSchema::TABLE;

    protected $fillable = [
        UserSchema::EMAIL,
        UserSchema::EMAIL_VERIFIED_AT,
        UserSchema::PASSWORD,
        UserSchema::IS_ACTIVE,
        UserSchema::COMMENT,
        UserSchema::TWO_FACTOR_SECRET,
        UserSchema::TWO_FACTOR_RECOVERY_CODES,
        UserSchema::TWO_FACTOR_CONFIRMED_AT,
        UserSchema::TWO_FACTOR_LAST_USED_STEP,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        UserSchema::PASSWORD,
        UserSchema::REMEMBER_TOKEN,
        UserSchema::TWO_FACTOR_SECRET,
        UserSchema::TWO_FACTOR_RECOVERY_CODES,
        UserSchema::TWO_FACTOR_LAST_USED_STEP,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            UserSchema::EMAIL_VERIFIED_AT => 'datetime',
            UserSchema::MOBILE_VERIFIED_AT => 'datetime',
            UserSchema::PASSWORD => 'hashed',
            UserSchema::IS_ACTIVE => 'boolean',
            UserSchema::TWO_FACTOR_SECRET => 'encrypted',
            UserSchema::TWO_FACTOR_RECOVERY_CODES => 'encrypted:array',
            UserSchema::TWO_FACTOR_CONFIRMED_AT => 'datetime',
            UserSchema::TWO_FACTOR_LAST_USED_STEP => 'integer',
        ];
    }

    protected static function newFactory(): UserFactory|Factory
    {
        return UserFactory::new();
    }

    /** Third-party identities linked to this account (Google sign-in). */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, SocialAccountSchema::USER_ID);
    }

    public function markEmailAsVerified(): bool
    {
        // 'now()' creates a Carbon timestamp for the current time
        return $this->forceFill([
            UserSchema::EMAIL_VERIFIED_AT => now(),
        ])->save();
    }

    public function markAsActive(): bool
    {
        // 'now()' creates a Carbon timestamp for the current time
        return $this->forceFill([
            UserSchema::IS_ACTIVE => true,
        ])->save();
    }

    public function isActive(): bool
    {
        return (bool) $this->{UserSchema::IS_ACTIVE};
    }
}
