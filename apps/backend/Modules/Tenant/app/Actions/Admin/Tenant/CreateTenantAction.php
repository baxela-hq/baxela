<?php

namespace Modules\Tenant\Actions\Admin\Tenant;

use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Tenant\Exceptions\Tenant\CreationFailedException;
use Modules\Tenant\Exceptions\Tenant\SlugTakenException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Operations\CreateTenantOperation;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Support\UniqueViolation;
use Throwable;

/**
 * Creates a store — a platform operation (multi-tenancy §6.5, Category 1).
 * handle() is the operation's only authorized caller; execute() is its
 * registered handler and runs in central mode under an audit record.
 */
class CreateTenantAction
{
    public function __construct(private readonly CentralOperations $central) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Tenant
    {
        $result = $this->central->run(new CreateTenantOperation($data), static::class);

        return $result instanceof Tenant
            ? $result
            : throw new LogicException('The tenant.create operation did not return a tenant.');
    }

    /** Runs inside CentralOperations::run(). */
    public function execute(CreateTenantOperation $operation): Tenant
    {
        try {
            return Tenant::query()->create([
                TenantSchema::NAME => $operation->data[TenantSchema::NAME],
                TenantSchema::SLUG => $operation->data[TenantSchema::SLUG],
                TenantSchema::EMAIL => $operation->data[TenantSchema::EMAIL],
                TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Decide from the violation itself — on PostgreSQL the
            // transaction is already aborted, so no follow-up query may
            // run here — and match the constraint, not a word in the
            // message (it contains the statement, which names every column).
            if (UniqueViolation::on($e, TenantSchema::SLUG)) {
                throw new SlugTakenException;
            }

            throw new CreationFailedException;
        } catch (Throwable $e) {
            report($e);

            throw new CreationFailedException;
        }
    }
}
