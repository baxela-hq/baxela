"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type FormEvent,
} from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import { ticketsApi } from "@/lib/api/tickets";
import type { ApiOrder, Paginated } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { SearchableSelect } from "@/components/ui/searchable-select";

/**
 * New-ticket form: subject, optional order reference (picked from the
 * customer's own recent orders) and the opening message. On success the
 * customer lands straight in the conversation thread.
 */
export function TicketForm() {
  const t = useTranslations("account.tickets");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const router = useRouter();
  const { token } = useAuth();

  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [orderCode, setOrderCode] = useState("");
  const [orders, setOrders] = useState<ApiOrder[] | null>(null);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (!token) return;
    // The order picker only needs the most recent orders; the list payload
    // carries their placement date and item name snapshots for the hint
    // line, so one request is enough.
    let cancelled = false;
    api
      .get<Paginated<ApiOrder>>("/order/user/orders?page=1", { token })
      .then((paginated) => {
        if (!cancelled) setOrders(paginated.data);
      })
      .catch(() => {
        // the ticket form stays usable without the order list
      });
    return () => {
      cancelled = true;
    };
  }, [token]);

  // The trigger keeps the short "#code" label the customer knows from
  // "My Orders"; the dropdown adds a hint line with the date and items so
  // orders are told apart by more than their opaque code.
  const orderOptions = useMemo(() => {
    return (orders ?? []).map((order) => {
      const date = order.created_at
        ? format.dateTime(new Date(order.created_at), { dateStyle: "medium" })
        : null;
      const items = (order.items ?? [])
        .map((item) => `${item.product_name_snapshot} ×${item.quantity}`)
        .join(", ");
      const hint = [date, items || null]
        .filter((part): part is string => part !== null)
        .join(" · ");
      return {
        value: order.order_code,
        label: `#${order.order_code}`,
        hint: hint || undefined,
      };
    });
  }, [orders, format]);

  const onSubmit = useCallback(
    async (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      if (!token) return;
      setPending(true);
      try {
        const ticket = await ticketsApi(token).create({
          subject,
          body: message,
          order_code: orderCode || null,
        });
        toast.success(t("messages.success.created"));
        router.push(`/profile/tickets/${ticket.id}`);
      } catch (cause) {
        toast.error(
          cause instanceof ApiError
            ? cause.message
            : t("messages.error.create_failed"),
        );
      } finally {
        setPending(false);
      }
    },
    [token, subject, message, orderCode, router, t],
  );

  return (
    <form
      className="flex flex-col gap-6 rounded-default border border-border-light bg-background p-6 md:p-8"
      onSubmit={onSubmit}
    >
      <h2 className="text-xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("texts.new_title")}
      </h2>

      <Input
        type="text"
        required
        maxLength={255}
        label={t("form.labels.subject")}
        placeholder={t("form.placeholders.subject")}
        value={subject}
        onChange={(event) => setSubject(event.target.value)}
      />

      {orderOptions.length > 0 ? (
        <SearchableSelect
          label={t("form.labels.order")}
          options={orderOptions}
          value={orderCode}
          onChange={setOrderCode}
        />
      ) : null}

      <Textarea
        required
        maxLength={5000}
        label={t("form.labels.message")}
        placeholder={t("form.placeholders.message")}
        value={message}
        onChange={(event) => setMessage(event.target.value)}
      />

      <Button type="submit" disabled={pending}>
        {pending ? tCommon("messages.info.loading") : t("actions.submit")}
      </Button>
    </form>
  );
}
