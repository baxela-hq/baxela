<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;

/*
 * §6.6: an idempotent route whose business transaction can commit without
 * the marker would let a committed operation be reclaimed as a clean retry.
 * The registry keeps the binding visible and reviewed like a permission
 * grant; an unregistered route or an unmarked action fails this test.
 */

it('registers every idempotent route with a commit-marking participant', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(static fn ($route): bool => in_array(
            IdempotencyMiddleware::class,
            $route->gatherMiddleware(),
            true,
        ))
        ->map(static fn ($route): ?string => $route->getName())
        ->filter(static fn (?string $name): bool => $name !== null)
        ->sort()
        ->values()
        ->all();

    $participants = config('core.idempotency.participants', []);
    $registered = array_keys($participants);
    sort($registered);

    expect($registered)->toBe($routes)
        ->and($participants)->not->toBeEmpty();

    foreach ($participants as $class) {
        $file = (new ReflectionClass($class))->getFileName();

        expect($file)->not->toBeFalse()
            ->and((string) file_get_contents((string) $file))
            ->toContain('IdempotencyCommitMarker::mark');
    }
});
