<?php

namespace Modules\Core\Tests\Feature\Http\Middleware;

/**
 * A throwaway write endpoint's execution tally, so the middleware's behaviour
 * is exercised over HTTP without coupling the test to another module's routes.
 */
class IdempotencySideEffectCounter
{
    public static int $executions = 0;
}
