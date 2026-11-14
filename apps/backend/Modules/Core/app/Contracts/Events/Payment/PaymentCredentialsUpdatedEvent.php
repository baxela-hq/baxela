<?php

namespace Modules\Core\Contracts\Events\Payment;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

/**
 * A committed mutation of payment-driver credentials (marketplace P5.2
 * D6/F5) — dispatched only after the database transaction commits. The
 * payload carries the driver and the affected key NAMES; credential
 * values (plaintext or ciphertext) never enter the event.
 */
class PaymentCredentialsUpdatedEvent extends AbstractBaseEvent
{
    public string $driver;

    /** @var list<string> the affected credential key names */
    public array $keys;

    /** Whether the mutation was a patch (upsert) or a delete. */
    public string $action;
}
