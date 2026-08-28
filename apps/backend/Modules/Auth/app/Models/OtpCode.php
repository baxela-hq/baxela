<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;

class OtpCode extends Model
{
    protected $table = OtpCodeSchema::TABLE;

    protected $fillable = [
        OtpCodeSchema::MOBILE,
        OtpCodeSchema::EMAIL,
        OtpCodeSchema::TYPE,
        OtpCodeSchema::CODE,
        OtpCodeSchema::ACTION,
        OtpCodeSchema::EXPIRES_AT,
        OtpCodeSchema::IS_USED,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            OtpCodeSchema::EXPIRES_AT => 'datetime',
            OtpCodeSchema::TYPE => OtpCodeTypeEnum::class,
            OtpCodeSchema::ACTION => OtpCodeActionEnum::class,
            OtpCodeSchema::IS_USED => 'boolean',
        ];
    }

    public function isValid(): bool
    {
        return ! $this->{OtpCodeSchema::IS_USED} && $this->{OtpCodeSchema::EXPIRES_AT}->isFuture();
    }

    public function markAsUsed(): bool
    {
        // 'now()' creates a Carbon timestamp for the current time
        return $this->forceFill([
            OtpCodeSchema::IS_USED => true,
        ])->save();
    }
}
