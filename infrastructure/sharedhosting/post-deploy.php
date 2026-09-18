<?php

/**
 * Token-protected post-deploy runner for shared hosts without SSH.
 *
 * deploy.sh uploads this file temporarily (renamed, with a one-time token
 * baked in), calls it once over HTTP, and deletes it from the server again.
 * Without a valid token it answers 404 and reveals nothing. It runs a fixed
 * sequence of steps and nothing else — there is deliberately no way to pass
 * arbitrary commands through it:
 *
 *   1. fix permissions on storage/ and bootstrap/cache/
 *   2. php artisan migrate --force
 *   3. create the public/storage link (artisan in custom-docroot mode,
 *      a direct symlink in fixed-docroot mode)
 *   4. php artisan optimize (non-fatal if it fails)
 *
 * Ends with a "DEPLOY_RESULT: OK|FAILED" marker the deploy script checks.
 */

// Rendered by deploy.sh at upload time.
const DEPLOY_TOKEN = '{{TOKEN}}';
const DOCROOT_MODE = '{{DOCROOT_MODE}}'; // custom | fixed
const APP_RELATIVE = '{{APP_RELATIVE}}'; // runner location -> app root, e.g. '..' or '../baxela'

header('Content-Type: text/plain; charset=utf-8');

if (! hash_equals(DEPLOY_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

@set_time_limit(0);

$appDir = realpath(__DIR__.'/'.APP_RELATIVE);
if ($appDir === false || ! is_file($appDir.'/artisan')) {
    http_response_code(500);
    echo "FAIL: cannot locate the app at ".__DIR__.'/'.APP_RELATIVE."\n";
    exit(1);
}

$failures = 0;

// --- 1. Permissions ----------------------------------------------------------

/**
 * @return string|null null on success, an error message otherwise
 */
function fixPermissions(string $root, int &$dirs, int &$files): ?string
{
    if (! is_dir($root)) {
        return "directory missing: {$root}";
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $mode = $item->isDir() ? 0775 : 0664;
        if (! @chmod($item->getPathname(), $mode)) {
            return sprintf('chmod failed on %s (current owner may differ from the FTP user)', $item->getPathname());
        }
        $item->isDir() ? $dirs++ : $files++;
    }

    return null;
}

echo "--- permissions\n";
foreach ([$appDir.'/storage', $appDir.'/bootstrap/cache'] as $root) {
    $dirs = $files = 0;
    $error = fixPermissions($root, $dirs, $files);
    if ($error === null) {
        echo "    {$root}: ok ({$dirs} dirs, {$files} files)\n";
    } else {
        $failures++;
        echo "    {$root}: FAIL — {$error}\n";
    }
}

// --- 2-4. Artisan ------------------------------------------------------------

try {
    require $appDir.'/vendor/autoload.php';

    /** @var Illuminate\Foundation\Application $app */
    $app = require $appDir.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $run = function (string $command, array $parameters = []) use ($kernel): int {
        return $kernel->call($command, $parameters);
    };

    echo "--- migrate\n";
    if ($run('migrate', ['--force' => true]) === 0) {
        echo trim($kernel->output())."\n";
    } else {
        $failures++;
        echo trim($kernel->output())."\n    FAIL: migrate returned a non-zero exit code\n";
    }

    echo "--- public/storage link\n";
    if (DOCROOT_MODE === 'fixed') {
        // The docroot is not <app>/public, so artisan's storage:link would
        // put the link in the wrong place — link it from here instead.
        $link = __DIR__.'/storage';
        $target = APP_RELATIVE.'/storage/app/public'; // relative keeps the link valid regardless of the home path
        if (file_exists($link) && ! is_link($link)) {
            $failures++;
            echo "    FAIL: {$link} already exists and is not a symlink — remove it manually\n";
        } else {
            is_link($link) && unlink($link);
            if (symlink($target, $link)) {
                echo "    {$link} -> {$target}\n";
            } else {
                $failures++;
                echo "    FAIL: symlink() not permitted (or target missing): {$target}\n";
            }
        }
    } elseif ($run('storage:link') === 0) {
        echo trim($kernel->output())."\n";
    } else {
        $failures++;
        echo trim($kernel->output())."\n    FAIL: storage:link returned a non-zero exit code\n";
    }

    echo "--- optimize (non-fatal)\n";
    if ($run('optimize') === 0) {
        echo "    ok (config, routes, events, views cached)\n";
    } else {
        echo "    WARNING: optimize failed — the app still works, just uncached\n";
        echo '    '.trim(preg_replace('/\s*\n\s*/', "\n    ", $kernel->output()))."\n";
    }
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL: '.$e->getMessage()."\n";
}

echo "\nDEPLOY_RESULT: ".($failures === 0 ? 'OK' : 'FAILED')."\n";

if (($_GET['cleanup'] ?? '') === '1') {
    @unlink(__FILE__);
    echo "runner self-deleted\n";
}
