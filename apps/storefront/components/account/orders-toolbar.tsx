"use client";

import { useTranslations } from "next-intl";
import { ChevronDownIcon, SearchIcon } from "@/components/ui/icons";

export type OrderStatusFilter = "all" | "delivered" | "in_process" | "cancelled";

const STATUS_OPTIONS: OrderStatusFilter[] = [
  "all",
  "delivered",
  "in_process",
  "cancelled",
];

interface OrdersToolbarProps {
  search: string;
  onSearchChange: (value: string) => void;
  status: OrderStatusFilter;
  onStatusChange: (value: OrderStatusFilter) => void;
}

/**
 * The search box and dark filter select from the profile screen, shown on
 * the page title row while the orders tab is active. Filtering itself is
 * client-side over the loaded page of orders.
 */
export function OrdersToolbar({
  search,
  onSearchChange,
  status,
  onStatusChange,
}: OrdersToolbarProps) {
  const t = useTranslations("account.account");

  return (
    <div className="flex flex-1 flex-wrap items-center justify-end gap-3">
      <div className="relative w-full sm:max-w-xs">
        <span className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-secondary-text">
          <SearchIcon className="size-5" />
        </span>
        <input
          type="search"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder={t("orders.placeholders.search")}
          aria-label={t("orders.placeholders.search")}
          className="h-12 w-full rounded-default border border-border bg-white pe-4 ps-11 text-sm text-foreground outline-none transition-colors placeholder:text-secondary-text focus:border-primary"
        />
      </div>
      <div className="relative">
        <select
          value={status}
          onChange={(event) =>
            onStatusChange(event.target.value as OrderStatusFilter)
          }
          aria-label={t("orders.labels.filter")}
          className="h-12 w-40 appearance-none rounded-default bg-primary ps-5 pe-11 text-sm font-medium text-primary-foreground outline-none"
        >
          {STATUS_OPTIONS.map((option) => (
            <option key={option} value={option}>
              {t(`orders.filters.${option}`)}
            </option>
          ))}
        </select>
        <span className="pointer-events-none absolute end-4 top-1/2 -translate-y-1/2 text-primary-foreground">
          <ChevronDownIcon className="size-4" />
        </span>
      </div>
    </div>
  );
}
