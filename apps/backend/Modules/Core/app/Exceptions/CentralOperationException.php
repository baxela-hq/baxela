<?php

namespace Modules\Core\Exceptions;

/**
 * A central operation was refused (multi-tenancy §6.5): its class is not
 * registered, it was invoked from a class that is not an allowed caller,
 * it ran without a reason, or a queued job is not allowed to carry it.
 * All of these are programming errors, never client outcomes.
 */
class CentralOperationException extends BaseException
{
    /** @param  array<string, mixed>  $meta */
    private function __construct(string $code, array $meta = [])
    {
        parent::__construct(code: $code, httpStatus: 500, meta: $meta, isSafe: false);
    }

    public static function unregistered(string $operation): self
    {
        return new self('core.tenant.operation_unregistered', ['operation' => $operation]);
    }

    public static function notAllowedCaller(string $operation, string $caller): self
    {
        return new self('core.tenant.operation_not_allowed', ['operation' => $operation, 'caller' => $caller]);
    }

    public static function reasonRequired(string $operation): self
    {
        return new self('core.tenant.reason_required', ['operation' => $operation]);
    }

    public static function alreadyRegistered(string $operation): self
    {
        return new self('core.tenant.operation_already_registered', ['operation' => $operation]);
    }

    public static function invalidDefinition(string $operation, string $part): self
    {
        return new self('core.tenant.operation_invalid_definition', ['operation' => $operation, 'part' => $part]);
    }

    public static function jobNotAllowed(string $operation, string $job): self
    {
        return new self('core.tenant.operation_job_not_allowed', ['operation' => $operation, 'job' => $job]);
    }
}
