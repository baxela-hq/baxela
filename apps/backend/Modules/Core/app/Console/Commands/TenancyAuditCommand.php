<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Operations\AuditTenancyOperation;
use Modules\Core\Support\Tenancy\CentralOperations;

/**
 * Installation-wide ownership audit (multi-tenancy §4.3, layer 4).
 * Returns a failing exit code when any relation crosses a store boundary,
 * so cron/CI can treat drift as an incident rather than a log line.
 */
class TenancyAuditCommand extends Command
{
    protected $signature = 'tenancy:audit {--json : Emit machine-readable violations} {--reason=tenancy:audit command : Reason recorded with the central operation}';

    protected $description = 'Report tenantless rows, unknown tenant references and cross-tenant relations';

    public function handle(CentralOperations $central): int
    {
        $reason = $this->option('reason');
        $reason = is_string($reason) && trim($reason) !== ''
            ? trim($reason)
            : 'tenancy:audit command';

        /** @var array{violations: list<array{type: string, table: string, detail: string}>, relations: int} $result */
        $result = $central->run(new AuditTenancyOperation, $reason);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR));

            return $result['violations'] === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($result['violations'] === []) {
            $this->components->info(sprintf(
                'Tenancy audit clean: %d relations checked, no violations.',
                $result['relations'],
            ));

            return self::SUCCESS;
        }

        $this->components->error(sprintf(
            'Tenancy audit found %d violation(s) across %d relations.',
            count($result['violations']),
            $result['relations'],
        ));
        $this->table(['Type', 'Table', 'Detail'], array_map(
            static fn (array $violation): array => [$violation['type'], $violation['table'], $violation['detail']],
            $result['violations'],
        ));

        return self::FAILURE;
    }
}
