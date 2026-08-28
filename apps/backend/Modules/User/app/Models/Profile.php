<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\User\Database\Factories\ProfileFactory;
use Modules\User\Schemas\Profile\ProfileSchema;

/**
 * @mixin Builder
 */
class Profile extends Model
{
    use HasFactory;

    protected $table = ProfileSchema::TABLE;

    protected $fillable = [
        ProfileSchema::USER_ID,
        ProfileSchema::FIRST_NAME,
        ProfileSchema::LAST_NAME,
    ];

    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }

    public function getByUserId(int|string $userId): ?Profile
    {
        return $this->where(ProfileSchema::USER_ID, $userId)->first();
    }
}
