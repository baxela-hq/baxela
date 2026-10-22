"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useFormatter, useTranslations } from "next-intl";

import {
  convertAmount,
  currencyNumberOptions,
  type StoreCurrency,
} from "@/lib/currency";
import { useCurrency } from "@/context/currency-context";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type {
  ApiOrder,
  ApiOrderItem,
  ApiOrderPaymentStatus,
  ApiOrderStatus,
  Paginated,
} from "@/lib/api/types";
import { Link } from "@/i18n/navigation";
import type { OrderStatusFilter } from "@/components/account/orders-toolbar";

// Badge colour groups from the design: completed is green, everything on
// its way is amber, cancelled red, the rest neutral. Fulfillment and
// payment are separate tracks, so each gets its own map.
const BADGE_CLASSES: Record<ApiOrderStatus, string> = {
  pending: "bg-muted text-secondary-text",
  processing: "bg-amber-100 text-amber-700",
  shipped: "bg-amber-100 text-amber-700",
  completed: "bg-accent/10 text-accent",
  cancelled: "bg-red-100 text-red-600",
};

const PAYMENT_BADGE_CLASSES: Record<ApiOrderPaymentStatus, string> = {
  unpaid: "bg-amber-100 text-amber-700",
  paid: "bg-accent/10 text-accent",
  partially_refunded: "bg-orange-100 text-orange-700",
  refunded: "bg-red-100 text-red-600",
};

const STATUS_FILTER_GROUPS: Record<
  Exclude<OrderStatusFilter, "all">,
  ApiOrderStatus[]
> = {
  delivered: ["completed"],
  in_process: ["pending", "processing", "shipped"],
  cancelled: ["cancelled"],
};

const CANCELLABLE_STATUSES: ApiOrderStatus[] = ["pending", "processing"];

function statusHintKey(
  status: ApiOrderStatus,
  paymentStatus: ApiOrderPaymentStatus,
) {
  if (status === "completed") return "delivered" as const;
  if (status === "cancelled") return "cancelled" as const;
  if (paymentStatus === "unpaid") return "awaiting_payment" as const;
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
  const { base, currencies } = useCurrency();

  // Historical money is charged money: an order with a display snapshot
  // shows exactly what the customer saw at purchase (snapshot currency +
  // snapshot rate, never today's rate); orders without one are pure base
  // currency. The session's "price" format is deliberately unused here.
  const orderFormat = (order: ApiOrder): ((amount: number) => string) => {
    const snapshot = order.display_currency_code
      ? currencies.find((row) => row.code === order.display_currency_code)
      : null;

    if (order.display_fx_rate !== null && snapshot) {
      const currency: StoreCurrency = {
        code: snapshot.code,
        symbol: snapshot.symbol,
        is_symbol_right: snapshot.is_symbol_right,
        decimal_places: snapshot.decimal_places,
      };
      return (amount) =>
        format.number(
          convertAmount(
            amount,
            order.display_fx_rate ?? "1",
            base.decimal_places,
            snapshot.decimal_places,
          ),
          currencyNumberOptions(currency),
        );
    }

    return (amount) => format.number(amount, currencyNumberOptions(base));
  };
  const { token } = useAuth();

  const [orders, setOrders] = useState<ApiOrder[] | null>(null);
  const [itemsByCode, setItemsByCode] = useState<Record<string, ApiOrderItem[]>>(
    {},
  );
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [expandedCode, setExpandedCode] = useState<string | null>(null);
  const [returningCode, setReturningCode] = useState<string | null>(null);
  const [returnReason, setReturnReason] = useState("damaged");
  const [returnNote, setReturnNote] = useState("");
  const [returning, setReturning] = useState(false);
  const [confirmingCancelCode, setConfirmingCancelCode] = useState<string | null>(
    null,
  );
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState<string | null>(null);



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
              `/order/user/orders/${order.order_code}/items`,
              { token },
            );
            return [order.order_code, items] as const;
          }),
        );
        setItemsByCode(Object.fromEntries(itemLists));
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

  const requestReturn = async (order: ApiOrder) => {
    setReturning(true);
    try {
      await api.post(
        `/order/user/orders/${order.order_code}/returns`,
        { reason: returnReason, note: returnNote.trim() || null },
        { token },
      );
      setReturningCode(null);
      setReturnReason("damaged");
      setReturnNote("");
      toast.success(t("orders.messages.success.return_requested"));
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("orders.messages.error.return_failed"),
      );
    } finally {
      setReturning(false);
    }
  };

  const cancelOrder = async (order: ApiOrder) => {
    setCancelling(true);
    try {
      const updated = await api.patch<ApiOrder>(
        `/order/user/orders/${order.order_code}/cancel`,
        undefined,
        { token },
      );
      setOrders(
        (previous) =>
          previous?.map((entry) =>
            entry.order_code === updated.order_code ? updated : entry,
          ) ?? previous,
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
      setConfirmingCancelCode(null);
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
      const items = itemsByCode[order.order_code] ?? [];
      return (
        items.some((item) =>
          item.product_name_snapshot.toLowerCase().includes(query),
        ) || order.order_code.toLowerCase().includes(query)
      );
    });
  }, [orders, itemsByCode, search, statusFilter]);

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
          const items = itemsByCode[order.order_code] ?? [];
          const money = orderFormat(order);
          const expanded = expandedCode === order.order_code;
          const shippingAddress = (order.addresses ?? []).find(
            (address) => address.type === "shipping",
          );
          const cancellable = CANCELLABLE_STATUSES.includes(order.status);
          const returnable =
            (order.status === "shipped" || order.status === "completed") &&
            (order.payment_status === "paid" ||
              order.payment_status === "partially_refunded");

          return (
            <li key={order.order_code} className="py-8 first:pt-0 last:pb-0">
              <p className="text-sm font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("labels.order_number", { code: order.order_code })}
              </p>

              {items.map((item, index) => (
                <div
                  key={`${item.variant_id}-${index}`}
                  className="mt-4 flex flex-wrap items-center gap-4"
                >
                  {item.image_url ? (
                    // Backend-served images come from arbitrary hosts, so
                    // this is a plain img rather than next/image.
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={item.image_url}
                      alt={item.product_name_snapshot}
                      loading="lazy"
                      className="size-16 shrink-0 rounded-default border border-border object-cover"
                    />
                  ) : (
                    <span
                      aria-hidden="true"
                      className="size-16 shrink-0 rounded-default border border-border bg-muted"
                    />
                  )}
                  <div className="min-w-0 flex-1">
                    {item.product_slug_snapshot ? (
                      <Link
                        href={`/products/${item.product_slug_snapshot}`}
                        className="truncate text-sm font-medium text-foreground transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
                      >
                        {item.product_name_snapshot}
                      </Link>
                    ) : (
                      <p className="truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                        {item.product_name_snapshot}
                      </p>
                    )}
                    <p className="mt-1 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      {t("labels.qty", { count: item.quantity })}
                    </p>
                  </div>
                  <p className="w-24 text-end text-sm font-semibold text-foreground">
                    {money(Number(item.price_snapshot) * item.quantity)}
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
                  <span
                    className={`inline-flex items-center rounded-default px-3 py-1 text-xs font-medium rtl:normal-case rtl:tracking-normal ${PAYMENT_BADGE_CLASSES[order.payment_status]}`}
                  >
                    {t(`orders.payment_status.${order.payment_status}`)}
                  </span>
                  <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {t(
                      `orders.hints.${statusHintKey(order.status, order.payment_status)}`,
                    )}
                  </span>
                </p>

                {returningCode === order.order_code ? (
                  <div className="mt-4 max-w-xl space-y-3 rounded-default border border-border-light p-4">
                    <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                      {t("orders.return_form.title")}
                    </p>
                    <label className="block space-y-1">
                      <span className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                        {t("orders.return_form.reason")}
                      </span>
                      <select
                        value={returnReason}
                        onChange={(event) => setReturnReason(event.target.value)}
                        className="w-full rounded-default border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none rtl:normal-case rtl:tracking-normal"
                      >
                        {["damaged", "wrong_item", "not_as_described", "other"].map(
                          (reason) => (
                            <option key={reason} value={reason}>
                              {t(`orders.return_form.reasons.${reason}`)}
                            </option>
                          ),
                        )}
                      </select>
                    </label>
                    <label className="block space-y-1">
                      <span className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                        {t("orders.return_form.note")}
                      </span>
                      <textarea
                        rows={3}
                        value={returnNote}
                        onChange={(event) => setReturnNote(event.target.value)}
                        className="w-full rounded-default border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-secondary-text focus:border-primary focus:outline-none rtl:normal-case rtl:tracking-normal"
                        placeholder={t("orders.return_form.note_placeholder")}
                      />
                    </label>
                    <div className="flex flex-wrap items-center gap-3">
                      <button
                        type="button"
                        disabled={returning}
                        onClick={() => requestReturn(order)}
                        className="inline-flex h-10 items-center justify-center rounded-default bg-foreground px-5 text-sm font-medium text-background transition-colors hover:opacity-90 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                      >
                        {returning
                          ? tCommon("messages.info.loading")
                          : t("orders.return_form.submit")}
                      </button>
                      <button
                        type="button"
                        onClick={() => setReturningCode(null)}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                      >
                        {t("orders.actions.keep_order")}
                      </button>
                    </div>
                  </div>
                ) : null}
                <div className="flex flex-wrap items-center gap-3">
                  {confirmingCancelCode === order.order_code ? (
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
                        onClick={() => setConfirmingCancelCode(null)}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                      >
                        {t("orders.actions.keep_order")}
                      </button>
                    </>
                  ) : (
                    <>
                      <button
                        type="button"
                        onClick={() =>
                          setExpandedCode(expanded ? null : order.order_code)
                        }
                        aria-expanded={expanded}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                      >
                        {expanded
                          ? t("orders.actions.hide_order")
                          : t("orders.actions.view_order")}
                      </button>
                      {returnable ? (
                        <button
                          type="button"
                          onClick={() => setReturningCode(order.order_code)}
                          className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                        >
                          {t("orders.actions.request_return")}
                        </button>
                      ) : null}
                      {cancellable ? (
                        <button
                          type="button"
                          onClick={() => setConfirmingCancelCode(order.order_code)}
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
                    {order.coupon_code ? (
                      <p className="mt-4 flex items-center justify-between text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                        <span>
                          {t("labels.discount", { code: order.coupon_code })}
                        </span>
                        <span className="font-medium text-accent">
                          −{money(Number(order.discount_amount))}
                        </span>
                      </p>
                    ) : null}
                    {Number(order.tax_amount) > 0 ? (
                      <p className="mt-4 flex items-center justify-between text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                        <span>{t("labels.tax")}</span>
                        <span className="font-medium text-foreground">
                          {money(Number(order.tax_amount))}
                        </span>
                      </p>
                    ) : null}
                    <p className="mt-4 flex items-center justify-between text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      <span>{t("labels.total")}</span>
                      <span className="text-base font-semibold text-foreground">
                        {money(Number(order.total_amount))}
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
            className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
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
            className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
          >
            {t("orders.actions.next_page")}
          </button>
        </nav>
      ) : null}
    </>
  );
}
