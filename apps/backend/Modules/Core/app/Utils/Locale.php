<?php

namespace Modules\Core\Utils;

use Illuminate\Http\Request;
use Throwable;

/**
 * Locale captured at event-dispatch time, so customer notifications keep the
 * visitor's language even when the listener runs later on a queue worker
 * (where no request scope exists).
 */
class Locale
{
    public const array SUPPORTED = ['en', 'fa'];

    public static function fromRequest(): string
    {
        try {
            $request = request();
        } catch (Throwable) {
            return self::fallback();
        }

        if (! $request instanceof Request) {
            return self::fallback();
        }

        return $request->getPreferredLanguage(self::SUPPORTED) ?? self::fallback();
    }

    private static function fallback(): string
    {
        return config('app.locale');
    }
}
