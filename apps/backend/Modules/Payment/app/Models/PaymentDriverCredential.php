<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;

/**
 * @property string $driver
 * @property string $key
 * @property string $value
 *
 * One credential of one payment driver, encrypted at rest. Plaintext
 * never leaves the resolver — API responses carry resolution states
 * only (marketplace P5.2 D5/D8).
 */
class PaymentDriverCredential extends Model
{
    protected $table = PaymentDriverCredentialSchema::TABLE;

    protected $fillable = [
        PaymentDriverCredentialSchema::DRIVER,
        PaymentDriverCredentialSchema::KEY,
        PaymentDriverCredentialSchema::VALUE,
    ];

    protected function casts(): array
    {
        return [
            PaymentDriverCredentialSchema::VALUE => 'encrypted',
        ];
    }
}
