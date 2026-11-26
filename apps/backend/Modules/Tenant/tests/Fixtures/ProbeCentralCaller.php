<?php

namespace Modules\Tenant\Tests\Fixtures;

use Modules\Core\Support\Tenancy\CentralOperation;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use RuntimeException;

/**
 * The only authorized caller of ProbeOperation, and its registered
 * handlers. Each handler records the mode it ran in.
 */
final class ProbeCentralCaller
{
    /** @var list<array{mode: int, central_operation: string|null}> */
    public static array $observed = [];

    public function __construct(private readonly CentralOperations $central) {}

    public function run(CentralOperation $operation, string $reason): mixed
    {
        return $this->central->run($operation, $reason);
    }

    /** Registered handler: the plain operation. */
    public function execute(ProbeOperation $operation): string
    {
        self::record();

        return ProbeOperation::RESULT;
    }

    /** Registered handler: writes a store and succeeds. */
    public function writesAndSucceeds(FailingProbeOperation $operation): string
    {
        self::record();

        Tenant::query()->create([
            TenantSchema::NAME => 'Written by the operation',
            TenantSchema::SLUG => 'written-by-operation',
            TenantSchema::EMAIL => 'written-by-operation@example.test',
        ]);

        return 'written';
    }

    /** Registered handler: writes a store, then fails. */
    public function writesThenFails(FailingProbeOperation $operation): never
    {
        self::record();

        Tenant::query()->create([
            TenantSchema::NAME => 'should be rolled back',
            TenantSchema::SLUG => 'rolled-back',
            TenantSchema::EMAIL => 'rolled-back@example.test',
        ]);

        throw new RuntimeException('handler failure');
    }

    public static function flush(): void
    {
        self::$observed = [];
    }

    private static function record(): void
    {
        $context = app(TenantContext::class);

        self::$observed[] = [
            'mode' => $context->mode(),
            'central_operation' => $context->centralOperation(),
        ];
    }
}
