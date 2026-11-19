<?php

namespace Modules\Catalog\Search;

use RuntimeException;

/**
 * Expected engine-infrastructure failure: connection refused, timeout,
 * Meili 5xx, missing index. Callers normalize THIS exception into the
 * SQL fallback; any other exception is a programming error and must
 * propagate (never silently become a SQL search).
 */
final class SearchInfrastructureException extends RuntimeException {}
