"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type {
  ApiOrder,
  ApiOrderItem,
  ApiOrderStatus,
  Paginated,
} from "@/lib/api/types";
import { Link } from "@/i18n/navigation";
import type { OrderStatusFilter } from "@/components/account/orders-toolbar";

// Badge colour groups from the design: delivered is green, everything on
// its way is amber, cancelled/refunded red, the rest neutral.
const BADGE_CLASSES: Record<ApiOrderStatus, string> = {
  draft: "bg-muted text-secondary-text",
  pending_payment: "bg-muted text-secondary-text",
  paid: "bg-amber-100 text-amber-700",
  processing: "bg-amber-100 text-amber-700",
  shipped: "bg-amber-100 text-amber-700",
  completed: "bg-accent/10 text-accent",
  cancelled: "bg-red-100 text-red-600",
  refunded: "bg-red-100 text-red-600",
};

const STATUS_FILTER_GROUPS: Record<
  Exclude<OrderStatusFilter, "all">,
  ApiOrderStatus[]
> = {
  delivered: ["completed"],
  in_process: ["paid", "processing", "shipped"],
  cancelled: ["cancelled", "refunded"],
};

const CANCELLABLE_STATUSES: ApiOrderStatus[] = ["pending_payment", "paid"];

function statusHintKey(status: ApiOrderStatus) {
  if (status === "completed") return "delivered" as const;
  if (status === "cancelled" || status === "refunded") {
    return "cancelled" as const;
  }
  if (status === "draft" || status === "pending_payment") {
    return "awaiting_payment" as const;
  }
  return "in_process" as const;
}

interface OrderListProps {
  search: string;
  statusFilter: OrderStatusFilter;
}

/**
 * The "My Orders" view: one block per order with its items, a status badge
 * and the design's View Order / Cancel Order actions. The list endpoint
 * returns orders without items, so items are fetched per order and reused
 * for both the rows and the client-side search.
 */
export function OrderList({ search, statusFilter }: OrderListProps) {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { token } = useAuth();

  const [orders, setOrders] = useState<ApiOrder[] | null>(null);
  const [itemsByOrderId, setItemsByOrderId] = useState<
    Record<number, ApiOrderItem[]>
  >({});
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [confirmingCancelId, setConfirmingCancelId] = useState<number | null>(
    null,
  );
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const usd = { style: "currency", currency: "USD" } as const;

  const load = useCallback(
    async (nextPage: number) => {
      if (!token) return;
      try {
        const paginated = await api.get<Paginated<ApiOrder>>(
          `/order/user/orders?page=${nextPage}`,
          { token },
        );
        setOrders(paginated.data);
        setLastPage(paginated.meta.last_page);
        const itemLists = await Promise.all(
          paginated.data.map(async (order) => {
            const items = await api.get<ApiOrderItem[]>(
              `/order/user/orders/${order.id}/items`,
              { token },
            );
            return [order.id, items] as const;
          }),
        );
        setItemsByOrderId(Object.fromEntries(itemLists));
        setError(null);
      } catch (cause) {
        setError(
          cause instanceof ApiError
            ? cause.message
            : t("orders.messages.error.load_failed"),
        );
      }
    },
    [token, t],
  );

  useEffect(() => {
    if (token) {
      // See cart page: fetch-on-auth is a legitimate external-system sync;
      // the rule flags setState statically even though it only runs in the
      // async continuation after the fetch resolves.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load(page);
    }
  }, [token, page, load]);

  const cancelOrder = async (order: ApiOrder) => {
    setCancelling(true);
    try {
      const updated = await api.patch<ApiOrder>(
        `/order/user/orders/${order.id}/cancel`,
        undefined,
        { token },
      );
      setOrders(
        (previous) =>
          previous?.map((entry) => (entry.id === updated.id ? updated : entry)) ??
          previous,
      );
      toast.success(t("orders.messages.success.cancelled"));
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("orders.messages.error.cancel_failed"),
      );
    } finally {
      setCancelling(false);
      setConfirmingCancelId(null);
    }
  };

  const visibleOrders = useMemo(() => {
    if (orders === null) return null;
    const query = search.trim().toLowerCase();
    const allowed =
      statusFilter === "all" ? null : STATUS_FILTER_GROUPS[statusFilter];
    return orders.filter((order) => {
      if (allowed && !allowed.includes(order.status)) return false;
      if (query === "") return true;
      const items = itemsByOrderId[order.id] ?? [];
      return (
        items.some((item) =>
          item.product_name_snapshot.toLowerCase().includes(query),
        ) || String(order.id).includes(query)
      );
    });
  }, [orders, itemsByOrderId, search, statusFilter]);

  if (orders === null || visibleOrders === null) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {error ?? tCommon("messages.info.loading")}
      </p>
    );
  }

  if (orders.length === 0) {
    return (
      <div className="py-16 text-center">
        <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.orders_empty")}
        </p>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.orders_empty_hint")}
        </p>
        <Link
          href="/products"
          className="mt-6 inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          {t("orders.actions.continue_shopping")}
        </Link>      </div>
    );
  }

  if (visibleOrders.length === 0) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("texts.orders_none_matching")}
      </p>
    );
  }

  return (
    <>
      {error ? (
        <p
          role="alert"
          className="mb-6 text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
        >
          {error}
        </p>
      ) : null}

      <ul className="divide-y divide-border-light">
        {visibleOrders.map((order) => {
          const items = itemsByOrderId[order.id] ?? [];
          const expanded = expandedId === order.id;
          const shippingAddress = order.addresses.find(
            (address) => address.type === "shipping",
          );
          const cancellable = CANCELLABLE_STATUSES.includes(order.status);

          return (
            <li key={order.id} className="py-8 first:pt-0 last:pb-0">
              <p className="text-sm font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("labels.order_number", { id: order.id })}
              </p>

              {items.map((item) => (
                <div
                  key={item.id}
                  className="mt-4 flex flex-wrap items-center gap-4"
                >
                  <span
                    aria-hidden="true"
                    className="size-16 shrink-0 rounded-default border border-border bg-muted"
                  />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                      {item.product_name_snapshot}
                    </p>
                    <p className="mt-1 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      {t("labels.qty", { count: item.quantity })}
                    </p>
                  </div>
                  <p className="w-24 text-end text-sm font-semibold text-foreground">
                    {format.number(
                      Number(item.price_snapshot) * item.quantity,
                      usd,
                    )}
                  </p>
                </div>
              ))}

              <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
                <p className="flex flex-wrap items-center gap-3">
                  <span
                    className={`inline-flex items-center rounded-default px-3 py-1 text-xs font-medium rtl:normal-case rtl:tracking-normal ${BADGE_CLASSES[order.status]}`}
                  >
                    {t(`orders.status.${order.status}`)}
                  </span>
                  <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {t(`orders.hints.${statusHintKey(order.status)}`)}
                  </span>
                </p>

                <div className="flex flex-wrap items-center gap-3">
                  {confirmingCancelId === order.id ? (
                    <>
                      <button
                        type="button"
                        disabled={cancelling}
                        onClick={() => cancelOrder(order)}
                        className="inline-flex h-10 items-center justify-center rounded-default bg-red-500 px-5 text-sm font-medium text-white transition-colors hover:bg-red-600 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                      >
                        {cancelling
                          ? tCommon("messages.info.loading")
                          : t("orders.actions.confirm_cancel")}
                      </button>
                      <button
                        type="button"
                        onClick={() => setConfirmingCancelId(null)}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                      >
                        {t("orders.actions.keep_order")}
                      </button>
                    </>
                  ) : (
                    <>
                      <button
                        type="button"
                        onClick={() =>
                          setExpandedId(expanded ? null : order.id)
                        }
                        aria-expanded={expanded}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                      >
                        {expanded
                          ? t("orders.actions.hide_order")
                          : t("orders.actions.view_order")}
                      </button>
                      {cancellable ? (
                        <button
                          type="button"
                          onClick={() => setConfirmingCancelId(order.id)}
                          className="inline-flex h-10 items-center justify-center rounded-default bg-red-500 px-5 text-sm font-medium text-white transition-colors hover:bg-red-600 rtl:normal-case rtl:tracking-normal"
                        >
                          {t("orders.actions.cancel_order")}
                        </button>
                      ) : null}
                    </>
                  )}
                </div>
              </div>

              {expanded ? (
                <div className="mt-6 grid grid-cols-1 gap-8 rounded-default border border-border-light p-6 sm:grid-cols-2">
                  <div>
                    <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                      {t("labels.shipping_address")}
                    </p>
                    {shippingAddress ? (
                      <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                        <span className="block">
                          {shippingAddress.full_name}
                        </span>
                        <span className="mt-1 block">
                          {shippingAddress.address_line}،{" "}
                          {shippingAddress.city}، {shippingAddress.country_code}
                          {shippingAddress.postal_code
                            ? `، ${shippingAddress.postal_code}`
                            : ""}
                        </span>
                        <span className="mt-1 block">
                          {shippingAddress.phone}
                        </span>
                      </p>
                    ) : null}
                  </div>
                  <div>
                    <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                      {t("labels.shipping_method")}
                    </p>
                    <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      {order.shipping_method_name ?? "—"}
                    </p>
                    <p className="mt-4 flex items-center justify-between text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      <span>{t("labels.total")}</span>
                      <span className="text-base font-semibold text-foreground">
                        {format.number(Number(order.total_amount), usd)}
                      </span>
                    </p>
                  </div>
                </div>
              ) : null}
            </li>
          );
        })}
      </ul>

      {lastPage > 1 ? (
        <nav
          aria-label={t("labels.my_orders")}
          className="mt-10 flex items-center justify-between"
        >
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((value) => Math.max(1, value - 1))}
            className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
          >
            {t("orders.actions.prev_page")}
          </button>
          <p className="text-sm text-secondary-text">
            {page} / {lastPage}
          </p>
          <button
            type="button"
            disabled={page >= lastPage}
            onClick={() => setPage((value) => Math.min(lastPage, value + 1))}
            className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
          >
            {t("orders.actions.next_page")}
          </button>
        </nav>
      ) : null}
    </>
  );
}
