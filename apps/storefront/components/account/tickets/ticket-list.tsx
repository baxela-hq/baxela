"use client";

import { useCallback, useEffect, useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { ApiError } from "@/lib/api/client";
import { ticketsApi } from "@/lib/api/tickets";
import type { ApiTicket, ApiTicketStatus } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

export type TicketStatusFilter = ApiTicketStatus | "all";

// Badge colours: answered is the happy state (staff replied), open waits
// for staff in amber, closed settles to neutral.
export const TICKET_BADGE_CLASSES: Record<ApiTicketStatus, string> = {
  open: "bg-amber-100 text-amber-700",
  answered: "bg-accent/10 text-accent",
  closed: "bg-muted text-secondary-text",
};

interface TicketListProps {
  statusFilter: TicketStatusFilter;
}

/**
 * The "Support Tickets" tab: one row per ticket with its status badge,
 * optional order reference and last activity. Rows link to the
 * conversation thread.
 */
export function TicketList({ statusFilter }: TicketListProps) {
  const t = useTranslations("account.tickets");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { token } = useAuth();

  const [tickets, setTickets] = useState<ApiTicket[] | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (nextPage: number, status: TicketStatusFilter) => {
      if (!token) return;
      try {
        const paginated = await ticketsApi(token).list(
          nextPage,
          status === "all" ? undefined : status,
        );
        setTickets(paginated.data);
        setLastPage(paginated.meta.last_page);
        setError(null);
      } catch (cause) {
        setError(
          cause instanceof ApiError
            ? cause.message
            : t("messages.error.load_failed"),
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
      void load(page, statusFilter);
    }
  }, [token, page, statusFilter, load]);

  if (tickets === null) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {error ?? tCommon("messages.info.loading")}
      </p>
    );
  }

  if (tickets.length === 0) {
    return (
      <div className="py-16 text-center">
        <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.empty")}
        </p>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.empty_hint")}
        </p>
        <Link
          href="/profile/tickets/new"
          className="mt-6 inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          {t("actions.new_ticket")}
        </Link>
      </div>
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
        {tickets.map((ticket) => (
          <li key={ticket.id} className="py-6 first:pt-0 last:pb-0">
            <Link
              href={`/profile/tickets/${ticket.id}`}
              className="group flex flex-wrap items-center justify-between gap-4"
            >
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-foreground transition-colors group-hover:text-accent rtl:normal-case rtl:tracking-normal">
                  {ticket.subject}
                </p>
                <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  <span>{t("labels.created_at", { date: format.dateTime(new Date(ticket.created_at ?? ""), { dateStyle: "medium" }) })}</span>
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
                <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {ticket.last_message_at
                    ? t("labels.last_activity", {
                        date: format.dateTime(new Date(ticket.last_message_at), { dateStyle: "medium" }),
                      })
                    : null}
                </span>
              </div>
            </Link>
          </li>
        ))}
      </ul>

      {lastPage > 1 ? (
        <nav
          aria-label={t("labels.title")}
          className="mt-10 flex items-center justify-between"
        >
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((value) => Math.max(1, value - 1))}
            className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
          >
            {t("actions.prev_page")}
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
            {t("actions.next_page")}
          </button>
        </nav>
      ) : null}
    </>
  );
}

/** Status filter chips shared by the tickets page header. */
export function TicketStatusFilterTabs({
  value,
  onChange,
}: {
  value: TicketStatusFilter;
  onChange: (next: TicketStatusFilter) => void;
}) {
  const t = useTranslations("account.tickets");

  const options: TicketStatusFilter[] = ["all", "open", "answered", "closed"];

  return (
    <div className="flex flex-wrap gap-2">
      {options.map((option) => (
        <button
          key={option}
          type="button"
          onClick={() => onChange(option)}
          className={
            value === option
              ? "inline-flex h-10 items-center rounded-default bg-primary px-5 text-sm font-medium text-primary-foreground transition-colors rtl:normal-case rtl:tracking-normal"
              : "inline-flex h-10 items-center rounded-default px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
          }
        >
          {t(`filters.${option}`)}
        </button>
      ))}
    </div>
  );
}
