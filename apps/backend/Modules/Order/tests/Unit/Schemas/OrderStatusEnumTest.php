<?php

use Modules\Order\Schemas\Order\OrderStatusEnum;

it('maps every fulfillment transition', function (OrderStatusEnum $from, array $expected) {
    expect($from->transitions())->toBe($expected);
})->with([
    'pending' => [OrderStatusEnum::PENDING, [OrderStatusEnum::PROCESSING, OrderStatusEnum::SHIPPED, OrderStatusEnum::CANCELLED]],
    'processing' => [OrderStatusEnum::PROCESSING, [OrderStatusEnum::SHIPPED, OrderStatusEnum::CANCELLED]],
    'shipped' => [OrderStatusEnum::SHIPPED, [OrderStatusEnum::COMPLETED]],
    'completed is terminal' => [OrderStatusEnum::COMPLETED, []],
    'cancelled is terminal' => [OrderStatusEnum::CANCELLED, []],
]);

it('allows same-status and legal transitions, rejects the rest', function (OrderStatusEnum $from, OrderStatusEnum $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending to processing' => [OrderStatusEnum::PENDING, OrderStatusEnum::PROCESSING, true],
    'pending to shipped' => [OrderStatusEnum::PENDING, OrderStatusEnum::SHIPPED, true],
    'pending to cancelled' => [OrderStatusEnum::PENDING, OrderStatusEnum::CANCELLED, true],
    'processing to completed is illegal' => [OrderStatusEnum::PROCESSING, OrderStatusEnum::COMPLETED, false],
    'shipped back to processing is illegal' => [OrderStatusEnum::SHIPPED, OrderStatusEnum::PROCESSING, false],
    'completed to cancelled is illegal' => [OrderStatusEnum::COMPLETED, OrderStatusEnum::CANCELLED, false],
    'cancelled to shipped is illegal' => [OrderStatusEnum::CANCELLED, OrderStatusEnum::SHIPPED, false],
    'same status is idempotent' => [OrderStatusEnum::SHIPPED, OrderStatusEnum::SHIPPED, true],
]);
