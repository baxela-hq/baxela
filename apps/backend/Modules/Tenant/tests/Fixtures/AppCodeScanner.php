<?php

namespace Modules\Tenant\Tests\Fixtures;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Walks the application's PHP source for the central-mode architecture test. */
final class AppCodeScanner
{
    /** @return list<string> every PHP file of the application code, as a path relative to the backend root */
    public static function files(): array
    {
        $roots = [base_path('app'), ...glob(base_path('Modules').'/*/app')];

        $files = [];
        foreach ($roots as $root) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = str_replace('\\', '/', ltrim(Str::after($file->getPathname(), base_path()), '/\\'));
                }
            }
        }

        return $files;
    }

    /** The file's source with docblocks and line comments removed — a comment may name a boundary without using it. */
    public static function code(string $relative): string
    {
        $source = (string) file_get_contents(base_path($relative));
        $source = (string) preg_replace('!/\*.*?\*/!s', '', $source);

        return (string) preg_replace('/^\s*\/\/.*$/m', '', $source);
    }

    /**
     * Short imported names of the file, mapped to their fully-qualified ones.
     *
     * @return array<string, string>
     */
    public static function imports(string $code): array
    {
        preg_match_all('/^\s*use\s+([A-Za-z\\\\]+)(?:\s+as\s+(\w+))?;/m', $code, $matches);

        return array_combine(
            array_map(
                fn (int $i, string $fqcn): string => $matches[2][$i] ?: substr($fqcn, (int) strrpos($fqcn, '\\') + 1),
                array_keys($matches[1]),
                $matches[1],
            ),
            $matches[1],
        );
    }

    /** The class a source file declares, from its PSR-4 path. */
    public static function classOf(string $relative): string
    {
        $class = str_replace('/', '\\', preg_replace('/\.php$/', '', $relative));

        return str_starts_with($class, 'Modules\\')
            ? preg_replace('/^Modules\\\\([^\\\\]+)\\\\app\\\\/', 'Modules\\\\$1\\\\', $class)
            : preg_replace('/^app\\\\/', 'App\\\\', $class);
    }
}
