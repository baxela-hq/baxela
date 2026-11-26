<?php

namespace Modules\Tenant\Tests\Fixtures;

use Modules\Core\Support\Tenancy\CentralOperation;

/** A registered central operation used by the registry tests. */
final class ProbeOperation implements CentralOperation
{
    public const string RESULT = 'probe-result';

    public function __construct(public readonly string $label) {}

    public function id(): string
    {
        return 'tenant.probe';
    }
}
