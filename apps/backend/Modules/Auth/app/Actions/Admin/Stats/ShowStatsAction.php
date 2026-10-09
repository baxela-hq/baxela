<?php

namespace Modules\Auth\Actions\Admin\Stats;

use Carbon\CarbonImmutable;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Stats\StatsSchema;
use Modules\Auth\Schemas\User\UserSchema;

class ShowStatsAction
{
    /**
     * Customers are users carrying no role — role-bearing users are admin
     * staff by definition in this platform.
     *
     * @return array<string, mixed> Stats payload keyed by StatsSchema constants.
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $prevMonthStart = $monthStart->subMonth();

        $newThisMonth = $this->customersCountBetween($monthStart);
        $newLastMonth = $this->customersCountBetween($prevMonthStart, $monthStart);

        return [
            StatsSchema::RES_CUSTOMERS_COUNT => $this->customersQuery()->count(),
            StatsSchema::RES_NEW_CUSTOMERS_THIS_MONTH => $newThisMonth,
            StatsSchema::RES_CUSTOMERS_CHANGE_PERCENT => $this->changePercent($newThisMonth, $newLastMonth),
        ];
    }

    private function customersQuery()
    {
        return User::query()->whereDoesntHave(UserSchema::ROLES);
    }

    /**
     * @param  CarbonImmutable|null  $to  Exclusive upper bound; null (the
     *                                    current-period case) leaves it open — nothing is created in the future,
     *                                    and a "now" bound would drop rows created in the same second as the
     *                                    request, since timestamps are stored with second precision.
     */
    private function customersCountBetween(CarbonImmutable $from, ?CarbonImmutable $to = null): int
    {
        return $this->customersQuery()
            ->where(UserSchema::CREATED_AT, '>=', $from)
            ->when($to, fn ($query) => $query->where(UserSchema::CREATED_AT, '<', $to))
            ->count();
    }

    private function changePercent(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
