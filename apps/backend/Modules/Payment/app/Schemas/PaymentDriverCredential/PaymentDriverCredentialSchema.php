<?php

namespace Modules\Payment\Schemas\PaymentDriverCredential;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Payment\Schemas\Module;

class PaymentDriverCredentialSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'driver_credentials';

    /** The payment driver slug (e.g. stripe) — matches config('payment.drivers') keys. */
    public const string DRIVER = 'driver';

    /** The credential key, as declared by the driver's credentialKeys(). */
    public const string KEY = 'key';

    /** Encrypted at rest (marketplace P5.2) — plaintext never stored, never served. */
    public const string VALUE = 'value';
}
