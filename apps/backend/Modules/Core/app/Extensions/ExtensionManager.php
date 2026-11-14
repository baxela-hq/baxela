<?php

namespace Modules\Core\Extensions;

use App\Baxela;
use Composer\Semver\Semver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Extension;
use Modules\Core\Schemas\Extension\ExtensionSchema;

/**
 * Single owner of extension runtime state. The `core_extensions` table
 * holds the only state that isn't derivable from Composer — the
 * merchant's enabled/disabled choice (extension plan §4 decision 1) —
 * and everything else (version, presence) is read live from the
 * installed packages. Rows are cached once per request; command
 * mutations write through and must flush.
 *
 * The manager also records WHY an extension was skipped at boot. These
 * recorded reasons are boot diagnostics scoped to this process (per
 * worker under Octane) — the admin API and `baxela:list` do NOT consume
 * them; they surface *derived* state instead (see ExtensionStateBuilder),
 * so a runtime toggle can never report a stale boot reason.
 */
class ExtensionManager
{
    /** @var array<string, string> skip reasons recorded by the boot guard, slug => reason */
    protected array $skipReasons = [];

    /** @var array<string, Extension>|null */
    protected static ?array $state = null;

    /**
     * The three fail-closed guards (plan §4 decision 3), in order:
     * missing row → disabled → compat mismatch. Returns true only when
     * the extension may boot; a skipped extension records its reason and
     * is inert — never fatal.
     *
     * @param  array<string, mixed>  $manifest  the provider's manifest(): slug, name, type, version, compat
     */
    public function shouldBoot(string $slug, array $manifest): bool
    {
        $row = $this->row($slug);

        if ($row === null) {
            // Installed on disk but no row (hand-deleted, or installed
            // without baxela:install): treat as disabled, not broken.
            Log::warning("Extension [{$slug}] has no row in core_extensions; treating as disabled.");

            return $this->skip($slug, 'missing_row');
        }

        if (! $row->{ExtensionSchema::ENABLED}) {
            return $this->skip($slug, 'disabled');
        }

        $constraint = (string) ($manifest['compat']['core'] ?? '');

        if ($constraint === '' || ! Semver::satisfies(Baxela::VERSION, $constraint)) {
            Log::warning("Extension [{$slug}] requires core {$constraint}; this core is ".Baxela::VERSION.'.');

            return $this->skip($slug, "compat_mismatch (requires core {$constraint}, core is ".Baxela::VERSION.')');
        }

        unset($this->skipReasons[$slug]);

        return true;
    }

    /**
     * The reason the boot guard recorded the last time it evaluated this
     * extension in this process — nothing more. It is NOT derived here:
     * the API's skip_reason (missing_row / not_installed / disabled) is
     * computed from current rows + Composer by ExtensionStateBuilder,
     * and a null there does not guarantee the extension will boot.
     */
    public function skipReason(string $slug): ?string
    {
        return $this->skipReasons[$slug] ?? null;
    }

    public function enabled(string $slug): bool
    {
        return (bool) $this->row($slug)?->{ExtensionSchema::ENABLED};
    }

    /** @return array<string, Extension> rows keyed by slug */
    public function all(): array
    {
        return $this->state();
    }

    public function row(string $slug): ?Extension
    {
        return $this->state()[$slug] ?? null;
    }

    /**
     * Write-through mutations: the database row changes AND the loaded
     * cache reflects it immediately, so one request never sees its own
     * writes stale. Callers that mutate beyond this request (commands)
     * still flush().
     */
    public function install(string $slug): Extension
    {
        $row = Extension::query()->firstOrCreate(
            [ExtensionSchema::SLUG => $slug],
            [ExtensionSchema::ENABLED => true],
        );

        $this->cacheWrite($slug, $row);

        return $row;
    }

    public function enable(string $slug): void
    {
        $this->toggle($slug, true);
    }

    public function disable(string $slug): void
    {
        $this->toggle($slug, false);
    }

    public function remove(string $slug): void
    {
        Extension::query()
            ->where(ExtensionSchema::SLUG, $slug)
            ->delete();

        $this->cacheWrite($slug, null);
    }

    public static function flush(): void
    {
        static::$state = null;
    }

    protected function toggle(string $slug, bool $enabled): void
    {
        Extension::query()
            ->where(ExtensionSchema::SLUG, $slug)
            ->update([ExtensionSchema::ENABLED => $enabled]);

        // Refreshed from the database: the cached model object may hold
        // the pre-toggle attribute values.
        $this->cacheWrite($slug, $this->row($slug)?->refresh());
    }

    /**
     * Update the per-request cache only when it has been loaded — a
     * request that never read extension state has nothing to update,
     * and the next read loads fresh rows including this write.
     */
    protected function cacheWrite(string $slug, ?Extension $row): void
    {
        if (static::$state !== null) {
            if ($row === null) {
                unset(static::$state[$slug]);
            } else {
                static::$state[$slug] = $row;
            }
        }
    }

    protected function skip(string $slug, string $reason): bool
    {
        $this->skipReasons[$slug] = $reason;

        return false;
    }

    /** @return array<string, Extension> */
    protected function state(): array
    {
        if (static::$state === null) {
            try {
                static::$state = Extension::query()
                    ->get()
                    ->keyBy(ExtensionSchema::SLUG)
                    ->all();
            } catch (QueryException) {
                // The table does not exist yet: a database being built
                // (fresh test run, prod cold start before the first
                // migrate). Fail closed — every extension counts as
                // row-missing until the table exists; boot never dies
                // over extension state.
                static::$state = [];
            }
        }

        return static::$state;
    }
}
