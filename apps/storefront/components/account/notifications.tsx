"use client";

import { useCallback, useEffect, useState } from "react";
import { useFormatter, useNow, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { useNotifications } from "@/context/notifications-context";
import { notificationsApi } from "@/lib/api/notifications";
import { ApiError } from "@/lib/api/client";
import type { ApiNotification } from "@/lib/api/types";
import { BellIcon, BoxIcon, LockIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

// Rows arrive from the notification API pre-localized; the icon follows the
// code prefix. Personal (auth) events keep the design's initials tile.
const CODE_ICONS: Record<string, typeof BoxIcon> = {
  order: BoxIcon,
  payment: LockIcon,
};

function rowIcon(code: string) {
  return CODE_ICONS[code.split(".")[0]] ?? BellIcon;
}

function orderHref(notification: ApiNotification): string | null {
  const code = notification.meta?.order_code;
  return code ? `/profile/orders?q=${encodeURIComponent(code)}` : null;
}

/**
 * The "Notifications" tab: a flat feed of rows — icon tile, bold title,
 * description and the time on the side. Clicking a row marks it read and,
 * for order events, jumps to that order (searched by its opaque code).
 */
export function Notifications({ initials }: { initials: string }) {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  // The feed syncs with the live Reverb channel, so relative times must tick
  // instead of staying pinned to the request-time `now` from the provider.
  const now = useNow({ updateInterval: 30_000 });
  const { token } = useAuth();
  // Shared writes keep the header badge + hover preview in sync with this
  // page; the paginated feed itself stays local to the page.
  const { markRead: markReadShared, markAllRead: markAllReadShared } =
    useNotifications();

  const [rows, setRows] = useState<ApiNotification[] | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (nextPage: number, mode: "replace" | "append") => {
      if (!token) return;
      try {
        const paginated = await notificationsApi(token).list(nextPage);
        setRows((previous) =>
          mode === "append"
            ? [...(previous ?? []), ...paginated.data]
            : paginated.data,
        );
        setPage(nextPage);
        setLastPage(paginated.meta.last_page);
        setError(null);
      } catch (cause) {
        setError(
          cause instanceof ApiError
            ? cause.message
            : t("notifications.messages.error.load_failed"),
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
      void load(1, "replace");
    }
  }, [token, load]);

  const markRead = (notification: ApiNotification) => {
    if (notification.read_at || !token) return;
    setRows(
      (previous) =>
        previous?.map((row) =>
          row.id === notification.id
            ? { ...row, read_at: new Date().toISOString() }
            : row,
        ) ?? previous,
    );
    // Fires the PATCH and syncs the header badge + preview; on failure it
    // re-syncs the badge from the server, and the row just stays unread
    // server-side.
    markReadShared(notification);
  };

  const markAllRead = async () => {
    if (!token) return;
    try {
      await markAllReadShared();
      setRows(
        (previous) =>
          previous?.map((row) => ({
            ...row,
            read_at: row.read_at ?? new Date().toISOString(),
          })) ?? previous,
      );
    } catch (cause) {
      if (cause instanceof ApiError) setError(cause.message);
    }
  };

  if (rows === null) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {error ?? tCommon("messages.info.loading")}
      </p>
    );
  }

  if (rows.length === 0) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("notifications.texts.empty")}
      </p>
    );
  }

  const hasUnread = rows.some((row) => row.read_at === null);

  return (
    <div>
      {error ? (
        <p
          role="alert"
          className="mb-6 text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
        >
          {error}
        </p>
      ) : null}

      <div className="flex justify-end">
        <button
          type="button"
          disabled={!hasUnread}
          onClick={markAllRead}
          className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
        >
          {t("notifications.actions.mark_all_read")}
        </button>
      </div>

      <ul className="divide-y divide-border-light">
        {rows.map((row) => {
          const Icon = rowIcon(row.code);
          const personal = row.code.startsWith("auth.");
          const href = orderHref(row);

          const content = (
            <>
              <span
                aria-hidden="true"
                className="grid size-12 shrink-0 place-items-center rounded-full bg-muted text-foreground"
              >
                {personal ? (
                  <span className="text-sm font-semibold">{initials}</span>
                ) : (
                  <Icon className="size-5" />
                )}
              </span>
              <div className="min-w-0 flex-1">
                <p className="flex items-center gap-2 text-base font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                  {row.read_at === null ? (
                    <span
                      aria-hidden="true"
                      className="size-2 shrink-0 rounded-full bg-accent"
                    />
                  ) : null}
                  {row.title}
                </p>
                <p className="mt-1 truncate text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {row.body}
                </p>
              </div>
              <p className="shrink-0 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {row.created_at
                  ? format.relativeTime(new Date(row.created_at), { now })
                  : ""}
              </p>
            </>
          );

          return (
            <li key={row.id} className="flex items-center gap-4 py-6 first:pt-0">
              {href ? (
                <Link
                  href={href}
                  onClick={() => markRead(row)}
                  className="flex w-full items-center gap-4"
                >
                  {content}
                </Link>
              ) : (
                <button
                  type="button"
                  onClick={() => markRead(row)}
                  className="flex w-full items-center gap-4 text-start"
                >
                  {content}
                </button>
              )}
            </li>
          );
        })}
      </ul>

      {page < lastPage ? (
        <div className="mt-10 flex justify-center">
          <button
            type="button"
            onClick={() => void load(page + 1, "append")}
            className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-white px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
          >
            {t("notifications.actions.load_more")}
          </button>
        </div>
      ) : null}
    </div>
  );
}
