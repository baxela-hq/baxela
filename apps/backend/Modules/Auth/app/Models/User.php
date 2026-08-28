<?php

namespace Modules\Auth\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Database\factories\UserFactory;
use Modules\Auth\Schemas\User\UserSchema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = UserSchema::TABLE;

    protected $fillable = [
        UserSchema::EMAIL,
        UserSchema::EMAIL_VERIFIED_AT,
        UserSchema::PASSWORD,
        UserSchema::IS_ACTIVE,
        UserSchema::IS_ADMIN,
        UserSchema::COMMENT,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        UserSchema::PASSWORD,
        UserSchema::REMEMBER_TOKEN,
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
            UserSchema::IS_ADMIN => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory|Factory
    {
        return UserFactory::new();
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
