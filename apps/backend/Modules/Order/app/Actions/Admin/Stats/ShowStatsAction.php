<?php

namespace Modules\Order\Actions\Admin\Stats;

use Carbon\CarbonImmutable;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\Stats\StatsSchema;

class ShowStatsAction
{
    private const int MONTHS = 12;

    private const int DAYS = 30;

    private const int RECENT_LIMIT = 5;

    private const int TOP_PRODUCTS_LIMIT = 5;

    /**
     * @return array<string, mixed> Stats payload keyed by StatsSchema constants.
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $prevMonthStart = $monthStart->subMonth();

        $paidRevenue = $this->paidRevenue();
        $paidCount = $this->paymentStatusCount(OrderPaymentStatusEnum::PAID);

        return [
            StatsSchema::RES_TOTAL_REVENUE => $this->money($paidRevenue),
            StatsSchema::RES_REVENUE_CHANGE_PERCENT => $this->changePercent(
                $this->paidRevenue($monthStart),
                $this->paidRevenue($prevMonthStart, $monthStart),
            ),
            StatsSchema::RES_ORDERS_COUNT => Order::query()->count(),
            StatsSchema::RES_ORDERS_CHANGE_PERCENT => $this->changePercent(
                $this->ordersCountBetween($monthStart),
                $this->ordersCountBetween($prevMonthStart, $monthStart),
            ),
            StatsSchema::RES_PENDING_ORDERS_COUNT => $this->statusCount(OrderStatusEnum::PENDING),
            StatsSchema::RES_CANCELLED_ORDERS_COUNT => $this->statusCount(OrderStatusEnum::CANCELLED),
            StatsSchema::RES_PAID_ORDERS_COUNT => $paidCount,
            StatsSchema::RES_AVG_ORDER_VALUE => $paidCount > 0
                ? $this->money($paidRevenue / $paidCount)
                : null,
            StatsSchema::RES_REVENUE_BY_MONTH => $this->revenueByMonth($now),
            StatsSchema::RES_ORDERS_PER_DAY => $this->ordersPerDay($now),
            StatsSchema::RES_ORDERS_BY_STATUS => $this->ordersByStatus(),
            StatsSchema::RES_TOP_PRODUCTS => $this->topProducts(),
            StatsSchema::RES_RECENT_ORDERS => $this->recentOrders(),
        ];
    }

    private function paidRevenue(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): float
    {
        return (float) Order::query()
            ->where(OrderSchema::PAYMENT_STATUS, OrderPaymentStatusEnum::PAID->value)
            ->when($from, fn ($query) => $query->where(OrderSchema::CREATED_AT, '>=', $from))
            ->when($to, fn ($query) => $query->where(OrderSchema::CREATED_AT, '<', $to))
            ->sum(OrderSchema::TOTAL_AMOUNT);
    }

    private function paymentStatusCount(OrderPaymentStatusEnum $status): int
    {
        return Order::query()
            ->where(OrderSchema::PAYMENT_STATUS, $status->value)
            ->count();
    }

    private function statusCount(OrderStatusEnum $status): int
    {
        return Order::query()
            ->where(OrderSchema::STATUS, $status->value)
            ->count();
    }

    /**
     * @param  CarbonImmutable|null  $to  Exclusive upper bound; null (the
     *                                    current-period case) leaves it open — nothing is created in the future,
     *                                    and a "now" bound would drop rows created in the same second as the
     *                                    request, since timestamps are stored with second precision.
     */
    private function ordersCountBetween(CarbonImmutable $from, ?CarbonImmutable $to = null): int
    {
        return Order::query()
            ->where(OrderSchema::CREATED_AT, '>=', $from)
            ->when($to, fn ($query) => $query->where(OrderSchema::CREATED_AT, '<', $to))
            ->count();
    }

    /**
     * Paid-order revenue per month, zero-filled so the chart axis is
     * continuous. substr() on the timestamp keeps the grouping portable
     * across MySQL (dev/prod) and SQLite (tests).
     *
     * @return array<int, array<string, string>>
     */
    private function revenueByMonth(CarbonImmutable $now): array
    {
        $start = $now->startOfMonth()->subMonths(self::MONTHS - 1);

        $rows = Order::query()
            ->where(OrderSchema::PAYMENT_STATUS, OrderPaymentStatusEnum::PAID->value)
            ->where(OrderSchema::CREATED_AT, '>=', $start)
            ->selectRaw(
                'substr('.OrderSchema::CREATED_AT.', 1, 7) as '.StatsSchema::RES_MONTH
                .', sum('.OrderSchema::TOTAL_AMOUNT.') as '.StatsSchema::RES_TOTAL
            )
            ->groupBy(StatsSchema::RES_MONTH)
            ->pluck(StatsSchema::RES_TOTAL, StatsSchema::RES_MONTH);

        $series = [];
        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $month = $start->addMonths($i)->format('Y-m');

            $series[] = [
                StatsSchema::RES_MONTH => $month,
                StatsSchema::RES_TOTAL => $this->money((float) ($rows[$month] ?? 0)),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function ordersPerDay(CarbonImmutable $now): array
    {
        $start = $now->startOfDay()->subDays(self::DAYS - 1);

        $rows = Order::query()
            ->where(OrderSchema::CREATED_AT, '>=', $start)
            ->selectRaw(
                'substr('.OrderSchema::CREATED_AT.', 1, 10) as '.StatsSchema::RES_DATE
                .', count(*) as '.StatsSchema::RES_COUNT
            )
            ->groupBy(StatsSchema::RES_DATE)
            ->pluck(StatsSchema::RES_COUNT, StatsSchema::RES_DATE);

        $series = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $day = $start->addDays($i)->format('Y-m-d');

            $series[] = [
                StatsSchema::RES_DATE => $day,
                StatsSchema::RES_COUNT => (int) ($rows[$day] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * One entry per OrderStatusEnum case, zero-filled.
     *
     * @return array<int, array<string, string|int>>
     */
    private function ordersByStatus(): array
    {
        $rows = Order::query()
            ->selectRaw(
                OrderSchema::STATUS.' as '.StatsSchema::RES_STATUS
                .', count(*) as '.StatsSchema::RES_COUNT
            )
            ->groupBy(OrderSchema::STATUS)
            ->pluck(StatsSchema::RES_COUNT, OrderSchema::STATUS);

        return array_map(
            fn (OrderStatusEnum $status): array => [
                StatsSchema::RES_STATUS => $status->value,
                StatsSchema::RES_COUNT => (int) ($rows[$status->value] ?? 0),
            ],
            OrderStatusEnum::cases(),
        );
    }

    /**
     * Best sellers by units sold, keyed by the product name snapshot.
     *
     * @return array<int, array<string, string|int>>
     */
    private function topProducts(): array
    {
        return OrderItem::query()
            ->whereNotNull(OrderItemSchema::PRODUCT_NAME_SNAPSHOT)
            ->selectRaw(
                OrderItemSchema::PRODUCT_NAME_SNAPSHOT.' as '.StatsSchema::RES_NAME
                .', sum('.OrderItemSchema::QUANTITY.') as '.StatsSchema::RES_QUANTITY
            )
            ->groupBy(OrderItemSchema::PRODUCT_NAME_SNAPSHOT)
            ->orderByDesc(StatsSchema::RES_QUANTITY)
            ->limit(self::TOP_PRODUCTS_LIMIT)
            ->get()
            ->map(fn ($row): array => [
                StatsSchema::RES_NAME => $row->{StatsSchema::RES_NAME},
                StatsSchema::RES_QUANTITY => (int) $row->{StatsSchema::RES_QUANTITY},
            ])
            ->all();
    }

    private function recentOrders()
    {
        return Order::query()
            ->orderByDesc(OrderSchema::CREATED_AT)
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    private function changePercent(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
