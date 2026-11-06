<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Core\Support\Erd\ErdDocument;
use Modules\Core\Support\Erd\ErdGenerator;
use Modules\Core\Support\Erd\MermaidErdFormatter;

class ErdGenerateCommand extends Command
{
    protected $signature = 'erd:generate
        {--module= : Only write this module page (plus the index)}
        {--check : Compare generated output with the files on disk without writing}
        {--path= : Output directory (default: docs/erd)}';

    protected $description = 'Generate per-module Mermaid ERD markdown docs from the schema and Eloquent relations';

    public function handle(ErdGenerator $generator, MermaidErdFormatter $formatter): int
    {
        $document = $generator->generate();
        $modules = $document->modules();

        $module = null;
        if ($option = (string) $this->option('module')) {
            $module = $this->resolveModule($option, $modules);

            if ($module === null) {
                $this->error("Unknown module [{$option}]. Valid modules: ".implode(', ', $modules).'.');

                return self::FAILURE;
            }
        }

        $targetModules = $module !== null ? [$module] : $modules;

        $files = ['index.md' => $formatter->formatIndex($document)];
        foreach ($targetModules as $targetModule) {
            $files[$formatter->fileName($targetModule).'.md'] = $formatter->formatModule($targetModule, $document);
        }

        return $this->option('check')
            ? $this->check($files, $this->path())
            : $this->write($files, $this->path(), $document, $module);
    }

    protected function path(): string
    {
        return (string) ($this->option('path') ?: base_path('docs/erd'));
    }

    /**
     * @param  array<int, string>  $modules
     */
    protected function resolveModule(string $requested, array $modules): ?string
    {
        foreach ($modules as $candidate) {
            if (strcasecmp($candidate, $requested) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $files
     */
    protected function write(array $files, string $path, ErdDocument $document, ?string $module): int
    {
        File::ensureDirectoryExists($path);

        foreach ($files as $name => $contents) {
            File::put($path.'/'.$name, $contents);
            $this->line("Wrote {$path}/{$name}");
        }

        $this->summary($document, $module);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $files
     */
    protected function check(array $files, string $path): int
    {
        $stale = [];

        foreach ($files as $name => $contents) {
            $file = $path.'/'.$name;

            if (! is_file($file)) {
                $stale[] = [$name, 'missing'];
            } elseif ((string) file_get_contents($file) !== $contents) {
                $stale[] = [$name, 'out of date'];
            }
        }

        if ($stale === []) {
            $this->info('ERD docs are up to date.');

            return self::SUCCESS;
        }

        foreach ($stale as [$name, $reason]) {
            $this->line("{$name}: {$reason}");
        }

        $this->error(sprintf('%d file(s) stale — run `php artisan erd:generate`%s.', count($stale), $this->option('path') ? '' : ' and commit docs/erd'));

        return self::FAILURE;
    }

    protected function summary(ErdDocument $document, ?string $module): void
    {
        $rows = [];
        foreach ($document->modules() as $name) {
            if ($module !== null && $name !== $module) {
                continue;
            }

            $counts = $document->countsFor($name);
            $rows[] = [$name, count($document->tablesFor($name)), $counts['fk'], $counts['app'], $counts['unresolved']];
        }

        $this->table(['Module', 'Tables', 'FK edges', 'App-level', 'Unresolved'], $rows);

        if ($document->skipDiagnostics !== []) {
            $this->warn('Skipped relations:');

            foreach ($document->skipDiagnostics as $skip) {
                $this->line("  {$skip}");
            }
        }
    }
}
