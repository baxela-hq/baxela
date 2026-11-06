<?php

namespace Modules\Core\Support\Erd;

use Illuminate\Support\Facades\File;

/**
 * Attributes every table to a module:
 *   1. by the module directory owning a model mapped to the table;
 *   2. by the longest matching module DB_PREFIX (for model-less tables
 *      such as pivots) — read from each module's Schemas\Module metadata,
 *      parsed from the file so no cross-module class is imported;
 *   3. anything left over (unprefixed tables) goes to "other".
 */
class ModuleTableResolver
{
    public const string OTHER = 'other';

    public function __construct(
        protected string $modulesBasePath,
    ) {}

    /**
     * @param  array<string, string>  $tableModules  table => module, from model discovery
     * @param  array<int, string>  $allTables
     * @return array<string, string> table => module, every table assigned
     */
    public function resolve(array $tableModules, array $allTables): array
    {
        $prefixes = $this->modulePrefixes();
        $resolved = $tableModules;

        foreach ($allTables as $table) {
            if (isset($resolved[$table])) {
                continue;
            }

            $resolved[$table] = $this->matchPrefix($table, $prefixes) ?? self::OTHER;
        }

        ksort($resolved);

        return $resolved;
    }

    /**
     * @return array<string, string> prefix => module, sorted by prefix length (longest first)
     */
    protected function modulePrefixes(): array
    {
        $prefixes = [];

        foreach (glob($this->modulesBasePath.'/*/app/Schemas/Module.php') ?: [] as $path) {
            $relative = substr($path, strlen($this->modulesBasePath.DIRECTORY_SEPARATOR));
            $module = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];

            $contents = (string) File::get($path);
            if (preg_match("/DB_PREFIX\s*=\s*'([^']+)'/", $contents, $matches)) {
                $prefixes[$matches[1]] = $module;
            }
        }

        uksort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $prefixes;
    }

    /**
     * @param  array<string, string>  $prefixes
     */
    protected function matchPrefix(string $table, array $prefixes): ?string
    {
        foreach ($prefixes as $prefix => $module) {
            if (str_starts_with($table, $prefix)) {
                return $module;
            }
        }

        return null;
    }
}
