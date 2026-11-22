<?php

use Modules\Core\Support\Dispatch\SanctionedDispatch;

// Self-contained by design: an architecture test scans files and reads the
// registry config directly — no booted application, no database.

/**
 * The dispatch-registry architecture test (postgres-migration §3.3.0a).
 * Enforcement layers:
 *
 * 1. DISCOVERY — every `ShouldQueue` class under `Modules/.../app` must be
 *    a registry key (`core.dispatch.queued`). A new queued class fails
 *    the build until it is classified (required ⇒ durable backing record;
 *    best-effort ⇒ accepted loss, stated).
 * 2. VALIDITY — every registry key resolves to a real class with the
 *    required fields populated.
 * 3. WRAPPER BOUNDARY — variable-target dispatch (`dispatch($var)`,
 *    `dispatch(new $dyn)`) is legal ONLY inside the sanctioned wrappers
 *    (SanctionedDispatch, NotificationDispatcherInterface consumers).
 *    Statically-visible direct dispatch (`dispatch(new X(...))`,
 *    `Event::dispatch(new X)`) needs no wrapper — the registry + listener
 *    maps audit it.
 *
 * NOT claimed as discoverable: bus facades behind variables, string
 * handlers, custom helper closures — the convention is that such code
 * routes through SanctionedDispatch; the boundary scan catches the
 * syntactically visible variable-target forms.
 */

it('registers every ShouldQueue class in the dispatch registry', function () {
    $registered = config('core.dispatch.queued', []);
    $discovered = discover_should_queue_classes();

    $unregistered = array_values(array_diff($discovered, array_keys($registered)));

    expect($unregistered)->toBe([], sprintf(
        "Unregistered ShouldQueue classes (classify in Modules/Core/config/dispatch.php —\n".
        "required entries need a durable in-transaction backing record):\n  %s",
        implode("\n  ", $unregistered),
    ));
});

it('keeps only real classes with complete entries in the registry', function () {
    foreach (config('core.dispatch.queued', []) as $class => $entry) {
        expect(class_exists($class))->toBeTrue("Registry entry {$class} does not resolve to a class.")
            ->and(in_array($entry['classification'] ?? '', ['required', 'best-effort'], true))
            ->toBeTrue("Registry entry {$class} lacks a valid classification.")
            ->and(trim((string) ($entry['durable_record'] ?? '')))->not->toBe('')
            ->and(trim((string) ($entry['processor'] ?? '')))->not->toBe('');
    }
});

it('requires durable backing for required-classified entries', function () {
    foreach (config('core.dispatch.queued', []) as $class => $entry) {
        if (($entry['classification'] ?? '') === 'required') {
            expect($entry['durable_record'])->not->toBe('none (accepted loss — current semantics)',
                "{$class} is classified required but declares no durable record.");
        }
    }
});

it('confines variable-target dispatch to the sanctioned wrappers', function () {
    $violations = [];

    foreach (app_source_files() as $path) {
        $source = file_get_contents($path);
        $lines = explode("\n", $source);

        // Sanctioned contexts: the wrapper itself, or files consuming the
        // NotificationDispatcherInterface boundary.
        $sanctioned = str_contains($source, 'NotificationDispatcherInterface')
            || str_contains($source, SanctionedDispatch::class);

        foreach ($lines as $number => $line) {
            if (preg_match('/dispatch\(\s*(?:new\s+)?\$/', trim($line)) !== 1) {
                continue;
            }

            if (! $sanctioned) {
                $violations[] = "{$path}:".($number + 1)." {$line}";
            }
        }
    }

    expect($violations)->toBe([], sprintf(
        "Variable-target dispatch outside the sanctioned wrappers — route through\n".
        "SanctionedDispatch (Modules/Core/Support/Dispatch) and classify it in\n".
        "the registry:\n  %s",
        implode("\n  ", $violations),
    ));
});

// ---------------------------------------------------------------------------
// Discovery helpers.
// ---------------------------------------------------------------------------

/** @return list<string> FQCNs of ShouldQueue classes under Modules/.../app */
function discover_should_queue_classes(): array
{
    $classes = [];

    foreach (app_source_files() as $path) {
        $source = file_get_contents($path);

        if (! str_contains($source, 'ShouldQueue')) {
            continue;
        }

        if (preg_match('/namespace\s+([^;]+);/', $source, $ns) !== 1
            && preg_match("/namespace\s+([^;]+);/", $source, $ns) !== 1) {
            continue;
        }

        if (preg_match('/(?:abstract\s+|final\s+)?class\s+(\w+)/', $source, $class) !== 1) {
            continue;
        }

        $classes[] = $ns[1].'\\'.$class[1];
    }

    return array_values(array_unique($classes));
}

/** @return list<string> PHP files under Modules/.../app */
function app_source_files(): array
{
    $files = [];

    foreach (glob(base_path('Modules/.../app'), GLOB_ONLYDIR) ?: [] as $moduleApp) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleApp));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}
