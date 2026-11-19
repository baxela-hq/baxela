<?php

namespace Modules\Catalog\Search;

use RuntimeException;

/**
 * The mutation gate is closed (cutover in progress). Callers abort
 * before any database work; user-facing writes surface this as a
 * retriable failure. Never swallowed into a SQL fallback.
 */
final class SearchGateClosedException extends RuntimeException {}
