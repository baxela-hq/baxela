<?php

namespace Modules\Tenant\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\TenantCurrency;

/**
 * A queued job that records the tenant context it runs under. On request
 * it touches a store-scoped model — which throws when the context is
 * missing — and/or invokes a registered central operation through the
 * authorized caller, so a test can see which mode the payload granted.
 */
class RecordsContextJob implements ShouldQueue
{
    /** @var list<array{label: string, tenant_id: int|null, mode: int}> */
    public static array $observed = [];

    public function __construct(
        public readonly string $label,
        private readonly bool $touchScopedModel = false,
        private readonly ?ProbeOperation $invoke = null,
        public readonly ?int $nestedTenantId = null,
    ) {}

    public function handle(): void
    {
        $context = app(TenantContext::class);

        self::$observed[] = [
            'label' => $this->label,
            'tenant_id' => $context->tenantId(),
            'mode' => $context->mode(),
        ];

        if ($this->touchScopedModel) {
            TenantCurrency::query()->count();
        }

        if ($this->invoke !== null) {
            app(ProbeCentralCaller::class)->run($this->invoke, 'queued run');
        }

        if ($this->nestedTenantId !== null) {
            // Dispatch as a statement: `dispatch()` returns a PendingDispatch
            // that is sent when it is destroyed — returning it from the
            // closure would send it after the tenant was restored.
            $context->runForTenant($this->nestedTenantId, function (): void {
                dispatch(new RecordsContextJob('inner'));
            });
        }
    }

    public static function flush(): void
    {
        self::$observed = [];
    }
}
