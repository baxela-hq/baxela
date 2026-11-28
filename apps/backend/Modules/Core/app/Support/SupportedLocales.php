<?php

namespace Modules\Core\Support;

use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Core\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Active store languages as base codes (e.g. "en", "fa"), sourced from the
 * languages table so new DB languages are picked up without code changes.
 * The cache is keyed by tenant: 0 is the platform/neutral entry, a positive
 * id is one store's runtime snapshot (§6.7 item 4). Falls back to the app
 * locale when the database is unavailable (fresh install, migrating).
 */
class SupportedLocales
{
    /** @var array<int, array<string>> */
    private static array $codes = [];

    /**
     * Drop every tenant's cached list so the next read goes back to the
     * languages table — for callers that change the active-language set
     * (tests seeding a language; any future admin toggle).
     */
    public static function flush(): void
    {
        self::$codes = [];
    }

    /**
     * Install a runtime's explicit locale list for the current context.
     *
     * @param  array<string>  $codes
     */
    public static function useCodes(array $codes): void
    {
        self::$codes[self::key()] = array_values(array_unique($codes));
    }

    /** Drop only the current context's entry — safe inside a job attempt. */
    public static function forget(): void
    {
        unset(self::$codes[self::key()]);
    }

    /**
     * The current context's cached list, if one has been installed.
     *
     * @return array<string>|null
     */
    public static function current(): ?array
    {
        return self::$codes[self::key()] ?? null;
    }

    /**
     * @return array<string>
     */
    public static function codes(): array
    {
        $key = self::key();

        if (isset(self::$codes[$key])) {
            return self::$codes[$key];
        }

        try {
            $codes = Language::query()
                ->where(LanguageSchema::IS_ACTIVE, true)
                ->pluck(LanguageSchema::CODE)
                ->all();
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            $codes = [];
        }

        return self::$codes[$key] = ($codes !== [] ? $codes : self::fallback());
    }

    /** @return array<string> */
    private static function fallback(): array
    {
        return array_values(array_unique(['en', (string) config('app.locale')]));
    }

    private static function key(): int
    {
        return app(TenantContext::class)->tenantId() ?? 0;
    }
}
