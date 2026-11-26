<?php

namespace Modules\Tenant\Tests\Fixtures;

use Modules\Core\Support\Tenancy\CentralOperation;

/** A second registered operation whose handler fails after writing. */
final class FailingProbeOperation implements CentralOperation
{
    public function id(): string
    {
        return 'tenant.probe.failing';
    }
}
