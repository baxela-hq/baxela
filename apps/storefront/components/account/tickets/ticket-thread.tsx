"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { useRouter } from "@/i18n/navigation";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { ApiError } from "@/lib/api/client";
import { ticketsApi } from "@/lib/api/tickets";
import type { ApiTicket } from "@/lib/api/types";
import { TICKET_BADGE_CLASSES } from "@/components/account/tickets/ticket-list";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";

/**
 * A single ticket conversation: header with subject/status/order reference
 * plus the message thread and the reply box. Customer replies sit on the
 * inline end, staff answers on the inline start, so the layout mirrors
 * correctly in RTL. Message bodies render as plain text.
 */
export function TicketThread() {
  const t = useTranslations("account.tickets");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const { token } = useAuth();
  const ticketId = Number(params.id);

  const [ticket, setTicket] = useState<ApiTicket | null>(null);
  const [reply, setReply] = useState("");
  const [sending, setSending] = useState(false);
  const [statusPending, setStatusPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token || !Number.isFinite(ticketId)) return;
    try {
      const loaded = await ticketsApi(token).get(ticketId);
      setTicket(loaded);
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.load_failed"),
      );
    }
  }, [token, ticketId, t]);

  useEffect(() => {
    if (token) {
      // See cart page: fetch-on-auth is a legitimate external-system sync;
      // the rule flags setState statically even though it only runs in the
      // async continuation after the fetch resolves.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [token, load]);

  const onReply = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token || reply.trim() === "") return;
    setSending(true);
    try {
      await ticketsApi(token).reply(ticketId, reply);
      setReply("");
      toast.success(t("messages.success.replied"));
      // refetch: a reply on a closed ticket reopens it server-side
      await load();
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.reply_failed"),
      );
    } finally {
      setSending(false);
    }
  };

  const onToggleStatus = async () => {
    if (!token || !ticket) return;
    const next = ticket.status === "closed" ? "open" : "closed";
    setStatusPending(true);
    try {
      const updated = await ticketsApi(token).updateStatus(ticket.id, next);
      setTicket((previous) =>
        previous ? { ...previous, status: updated.status } : previous,
      );
      toast.success(
        next === "closed"
          ? t("messages.success.closed")
          : t("messages.success.reopened"),
      );
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.status_failed"),
      );
    } finally {
      setStatusPending(false);
    }
  };

  if (ticket === null) {
    // covers both the initial load and a failed one (e.g. someone else's
    // ticket id resolves to the backend's 404)
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {error ?? tCommon("messages.info.loading")}
      </p>
    );
  }

  return (
    <div>
      <button
        type="button"
        onClick={() => router.push("/profile/tickets")}
        className="text-sm font-medium text-secondary-text transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
      >
        ← {t("actions.back_to_tickets")}
      </button>

      <div className="mt-4 rounded-default border border-border-light bg-background p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <h1 className="text-xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
              {ticket.subject}
            </h1>
            <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              <span>
                {t("labels.created_at", {
                  date: format.dateTime(
                    new Date(ticket.created_at ?? ""),
                    { dateStyle: "medium" },
                  ),
                })}
              </span>
              {ticket.order_code ? (
                <span className="inline-flex items-center rounded-default bg-muted px-2 py-0.5 text-xs rtl:normal-case rtl:tracking-normal">
                  {t("labels.order_reference", { code: ticket.order_code })}
                </span>
              ) : null}
            </p>
          </div>
          <div className="flex items-center gap-3">
            <span
              className={`inline-flex items-center rounded-default px-3 py-1 text-xs font-medium rtl:normal-case rtl:tracking-normal ${TICKET_BADGE_CLASSES[ticket.status]}`}
            >
              {t(`status.${ticket.status}`)}
            </span>
            <Button
              type="button"
              variant="outline"
              disabled={statusPending}
              onClick={onToggleStatus}
            >
              {ticket.status === "closed"
                ? t("actions.reopen")
                : t("actions.close")}
            </Button>
          </div>
        </div>

        <ul className="mt-8 flex flex-col gap-6">
          {(ticket.messages ?? []).map((message) => {
            const mine = message.sender === "customer";
            return (
              <li
                key={message.id}
                // ms-auto pushes the bubble to the inline end — the
                // customer's side — in both LTR and RTL
                className={`flex max-w-[85%] flex-col ${
                  mine ? "ms-auto items-end" : "me-auto items-start"
                }`}
              >
                <p className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {mine
                    ? t("labels.you")
                    : t("labels.support_team")}{" "}
                  ·{" "}
                  {format.dateTime(new Date(message.created_at ?? ""), {
                    dateStyle: "medium",
                    timeStyle: "short",
                  })}
                </p>
                <p
                  className={`mt-1 whitespace-pre-wrap rounded-default px-4 py-3 text-sm rtl:normal-case rtl:tracking-normal ${
                    mine
                      ? "bg-primary text-primary-foreground"
                      : "bg-muted text-foreground"
                  }`}
                >
                  {message.body}
                </p>
              </li>
            );
          })}
        </ul>

        <form className="mt-8 flex flex-col gap-4" onSubmit={onReply}>
          {ticket.status === "closed" ? (
            <p className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("texts.reopen_hint")}
            </p>
          ) : null}
          <Textarea
            required
            maxLength={5000}
            label={t("form.labels.reply")}
            placeholder={t("form.placeholders.reply")}
            value={reply}
            onChange={(event) => setReply(event.target.value)}
          />
          <Button type="submit" disabled={sending || reply.trim() === ""}>
            {sending ? tCommon("messages.info.loading") : t("actions.send")}
          </Button>
        </form>
      </div>
    </div>
  );
}
