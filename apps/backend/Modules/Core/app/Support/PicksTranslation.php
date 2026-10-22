<?php

namespace Modules\Core\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

/**
 * Picks a translation row for display: the requested language first, then
 * the DEFAULT language (deliberately not "whatever row was inserted
 * first"), then any row. The default language id is cache-memoized — a
 * default-language switch is a rare admin action, and a short TTL keeps
 * listing pages from re-querying it per row.
 */
class PicksTranslation
{
    private const CACHE_KEY = 'core.language.default_id';
    private const CACHE_TTL = 300;

    /**
     * @param  \Illuminate\Support\Collection<int, object>|null  $translations
     *                                        (Eloquent collections extend it)
     */
    public static function pick(
        ?Collection $translations,
        ?int $languageId,
        string $languageColumn = LanguageSchema::ID,
    ): ?object {
        if (is_null($translations) || $translations->isEmpty()) {
            return null;
        }

        if (! is_null($languageId)) {
            $requested = $translations->firstWhere($languageColumn, $languageId);
            if (! is_null($requested)) {
                return $requested;
            }
        }

        $defaultId = self::defaultLanguageId();
        if (! is_null($defaultId) && $defaultId !== $languageId) {
            $default = $translations->firstWhere($languageColumn, $defaultId);
            if (! is_null($default)) {
                return $default;
            }
        }

        return $translations->first();
    }

    public static function defaultLanguageId(): ?int
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn (): ?int => app(CoreGatewayInterface::class)->getDefaultLanguage()?->id,
        );
    }
}
