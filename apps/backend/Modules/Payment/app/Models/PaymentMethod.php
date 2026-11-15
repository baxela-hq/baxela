<?php

namespace Modules\Payment\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

/**
 * @property int $id
 * @property string $method
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
            PaymentMethodSchema::IS_ACTIVE => 'boolean',
            PaymentMethodSchema::SORT_ORDER => 'integer',
        ];
    }
}
