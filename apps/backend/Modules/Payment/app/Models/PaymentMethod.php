<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

class PaymentMethod extends Model
{
    protected $table = PaymentMethodSchema::TABLE;

    protected $fillable = [
        PaymentMethodSchema::METHOD,
        PaymentMethodSchema::IS_ACTIVE,
        PaymentMethodSchema::SORT_ORDER,
    ];

    protected function casts(): array
    {
        return [
            PaymentMethodSchema::METHOD => PaymentMethodEnum::class,
            PaymentMethodSchema::IS_ACTIVE => 'boolean',
            PaymentMethodSchema::SORT_ORDER => 'integer',
        ];
    }
}
