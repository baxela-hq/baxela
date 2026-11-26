<?php

namespace Modules\Core\Support\Tenancy;

use Modules\Core\Exceptions\CentralOperationException;

/**
 * Which central operations exist, what executes each one, and which
 * classes may invoke it (multi-tenancy §6.5).
 *
 * A Core singleton. Each module registers its own operations from its
 * service provider, so Core never references a module's classes; the
 * architecture test reads the same registry.
 *
 * @phpstan-type Definition array{handler: array{string, string}, callers: list<string>, jobs: list<string>}
 */
final class CentralOperationRegistry
{
    /** @var array<string, Definition> */
    private array $operations = [];

    /**
     * `handler` is a [class, method] pair resolved through the container;
     * `callers` are the only classes allowed to run the operation; `jobs`
     * are the queued classes allowed to carry it in a payload (§6.1).
     *
     * @param  array<string, mixed>  $definition
     */
    public function register(string $operation, array $definition): void
    {
        if (isset($this->operations[$operation])) {
            throw CentralOperationException::alreadyRegistered($operation);
        }

        $handler = $definition['handler'] ?? null;
        $handlerClass = is_array($handler) ? ($handler[0] ?? null) : null;
        $handlerMethod = is_array($handler) ? ($handler[1] ?? null) : null;

        if (! is_string($handlerClass) || ! is_string($handlerMethod) || ! method_exists($handlerClass, $handlerMethod)) {
            throw CentralOperationException::invalidDefinition($operation, 'handler');
        }

        $callers = $definition['callers'] ?? [];
        $jobs = $definition['jobs'] ?? [];

        if (! is_array($callers) || ! is_array($jobs) || $callers === [] || ! $this->areClasses($callers) || ! $this->areClasses($jobs)) {
            throw CentralOperationException::invalidDefinition($operation, 'callers or jobs');
        }

        $this->operations[$operation] = [
            'handler' => [$handlerClass, $handlerMethod],
            'callers' => array_values($callers),
            'jobs' => array_values($jobs),
        ];
    }

    /**
     * @return Definition
     */
    public function definition(string $operation): array
    {
        return $this->operations[$operation] ?? throw CentralOperationException::unregistered($operation);
    }

    public function isRegistered(string $operation): bool
    {
        return isset($this->operations[$operation]);
    }

    /** @return array<string, Definition> */
    public function all(): array
    {
        return $this->operations;
    }

    public function flush(): void
    {
        $this->operations = [];
    }

    /** @param  array<mixed>  $classes */
    private function areClasses(array $classes): bool
    {
        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                return false;
            }
        }

        return true;
    }
}
