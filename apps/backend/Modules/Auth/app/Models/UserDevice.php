<?php

namespace Modules\Auth\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Schemas\UserDevice\UserDeviceSchema;

/**
 * S.4 persistent device memory: every (user, device) pair ever seen,
 * written at the authentication completion boundary. Survives sign-out,
 * forced revocation and token expiry, so the new-device notification
 * cannot re-fire for a device the user has used all along. Device names
 * are client-reported labels — security history, never an authentication
 * factor (D6).
 *
 * @mixin Builder<UserDevice>
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $device_name
 * @property CarbonInterface|null $first_seen_at
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class UserDevice extends Model
{
    protected $table = UserDeviceSchema::TABLE;

    protected $fillable = [
        UserDeviceSchema::USER_ID,
        UserDeviceSchema::DEVICE_NAME,
        UserDeviceSchema::FIRST_SEEN_AT,
        UserDeviceSchema::LAST_SEEN_AT,
    ];

    protected function casts(): array
    {
        return [
            UserDeviceSchema::FIRST_SEEN_AT => 'datetime',
            UserDeviceSchema::LAST_SEEN_AT => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
