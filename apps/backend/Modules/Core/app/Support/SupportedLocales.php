<?php

namespace Modules\Core\Support;

use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;
use Throwable;

/**
 * Active store languages as base codes (e.g. "en", "fa"), sourced from the
 * languages table so new DB languages are picked up without code changes.
 * Falls back to the app locale when the database is unavailable (fresh
 * install, running migrations, console boot).
 */
class SupportedLocales
{
    /** @var array<string>|null */
    private static ?array $codes = null;

    /**
     * @return array<string>
     */
    public static function codes(): array
    {
        if (self::$codes !== null) {
            return self::$codes;
        }

        try {
            $codes = Language::query()
                ->where(LanguageSchema::IS_ACTIVE, true)
                ->pluck(LanguageSchema::CODE)
                ->all();
        } catch (Throwable) {
            $codes = [];
        }

        return self::$codes = ($codes !== [] ? $codes : self::fallback());
    }

    /**
     * @return array<string>
     */
    private static function fallback(): array
    {
        return array_values(array_unique(['en', (string) config('app.locale')]));
    }
}
