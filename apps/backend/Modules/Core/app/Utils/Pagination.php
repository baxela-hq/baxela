<?php

namespace Modules\Core\Utils;

class Pagination
{
    public const int DEFAULT_PER_PAGE = 15;

    /** Upper bound for client-requested page sizes on list endpoints. */
    public const int MAX_PER_PAGE = 100;

    /**
     * Clamped `per_page` value for list endpoints: unbounded client-controlled
     * page sizes on public endpoints are a trivial memory/DoS vector, so the
     * request is bounded to [1, MAX_PER_PAGE] with the conventional default.
     */
    public static function perPage(): int
    {
        $requested = intval(request()->input('per_page', self::DEFAULT_PER_PAGE));

        return min(self::MAX_PER_PAGE, max(1, $requested));
    }
}
