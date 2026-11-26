<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\Tenant\CentralOperationRunEvent;
use Modules\Core\Exceptions\CentralOperationException;
use Throwable;

/**
 * The ONLY public entry to MODE_CENTRAL (multi-tenancy §6.5). Central mode
 * is reserved for cross-store reads and platform-tier writes; everything
 * passes through run(), which enforces the operation registry:
 *
 *  - the operation's exact class must be registered (§6.5, F3);
 *  - the calling class must be one of its authorized callers;
 *  - a reason is mandatory and recorded;
 *  - the operation and its success audit record commit together.
 *
 * A refused attempt writes no audit row (nothing ran) but is logged.
 * `TenantContext::central()` is @internal; the architecture test fails any
 * direct use of it or of MODE_CENTRAL outside this namespace.
 */
final class CentralOperations
{
    private const string TENANCY_PREFIX = 'Modules\\Core\\Support\\Tenancy\\';

    public function __construct(
        private readonly TenantContext $context,
        private readonly CentralOperationRegistry $registry,
        private readonly Container $container,
    ) {}

    /**
     * @throws CentralOperationException
     * @throws Throwable
     */
    public function run(CentralOperation $operation, string $reason): mixed
    {
        try {
            ['handler' => $handler, 'caller' => $caller] = $this->admit($operation, $reason);
        } catch (CentralOperationException $e) {
            Log::warning('Central operation refused', array_merge($e->meta, [
                'reason_given' => trim($reason),
                'actor_id' => Auth::id(),
            ]));

            throw $e;
        }

        $reason = trim($reason);

        try {
            // One transaction for the operation and its audit record: a
            // handler's own beginTransaction() nests as a savepoint.
            return DB::transaction(function () use ($operation, $reason, $caller, $handler): mixed {
                $result = $this->context->runCentral(
                    $operation::class,
                    fn () => $this->container->call($handler, ['operation' => $operation]),
                );

                event($this->runEvent($operation, $reason, $caller, 'succeeded'));

                return $result;
            });
        } catch (Throwable $e) {
            // The attempt itself stays on the record, written after the
            // rollback — unless recording is what failed.
            $this->recordFailedAttempt($operation, $reason, $caller);

            throw $e;
        }
    }

    /**
     * The registry and call-site checks, before anything runs.
     *
     * @return array{handler: array{object, string}, caller: string}
     */
    private function admit(CentralOperation $operation, string $reason): array
    {
        if (trim($reason) === '') {
            throw CentralOperationException::reasonRequired($operation::class);
        }

        $definition = $this->registry->definition($operation::class);
        $caller = $this->caller();

        if (! in_array($caller, $definition['callers'], true)) {
            throw CentralOperationException::notAllowedCaller($operation::class, $caller);
        }

        // The handler is an instance method: resolve the class through the
        // container — a bare [class, method] array is called statically.
        [$handlerClass, $handlerMethod] = $definition['handler'];

        return [
            'handler' => [$this->container->make($handlerClass), $handlerMethod],
            'caller' => $caller,
        ];
    }

    /**
     * The first frame outside this namespace is the caller. Closures
     * report the class they were defined in, so an Action that calls run()
     * from inside a transaction closure is still that Action.
     */
    private function caller(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16) as $frame) {
            $class = $frame['class'] ?? null;

            if (! is_string($class) || str_starts_with($class, self::TENANCY_PREFIX)) {
                continue;
            }

            return $class;
        }

        return '(unknown)';
    }

    private function runEvent(
        CentralOperation $operation,
        string $reason,
        string $caller,
        string $outcome,
    ): CentralOperationRunEvent {
        $actorId = Auth::id();

        return new CentralOperationRunEvent(
            $operation::class,
            $operation->id(),
            $reason,
            $caller,
            $outcome,
            $actorId === null ? null : (int) $actorId,
        );
    }

    private function recordFailedAttempt(CentralOperation $operation, string $reason, string $caller): void
    {
        try {
            // Its own transaction: nested, that is a savepoint, so a record
            // that fails rolls back alone and leaves the caller's
            // transaction usable — PostgreSQL would otherwise abort it.
            DB::transaction(fn () => event($this->runEvent($operation, $reason, $caller, 'failed')));
        } catch (Throwable) {
            // Never mask the operation's own failure with an audit one.
        }
    }
}
