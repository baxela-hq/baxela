"use client";

import { useEffect, useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { notificationsApi } from "@/lib/api/notifications";
import type { ApiNotification } from "@/lib/api/types";
import {
  connectNotificationEcho,
  getNotificationEcho,
} from "@/lib/realtime/echo";
import { BellIcon, BoxIcon, LockIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

/** Broadcast payload: a notification row plus the server-computed badge. */
type RealtimeNotification = ApiNotification & { unread_count: number };

const CODE_ICONS: Record<string, typeof BoxIcon> = {
  order: BoxIcon,
  payment: LockIcon,
};

function rowIcon(code: string) {
  return CODE_ICONS[code.split(".")[0]] ?? BellIcon;
}

/**
 * Header notifications entry: an icon link with an unread badge and a
 * pure-CSS hover preview of the newest rows, mirroring the CartMenu
 * island. The badge and preview load once on sign-in, then update live
 * over the private Reverb channel (no polling). Full history lives on
 * the profile notifications page.
 */
export function NotificationsMenu() {
  const t = useTranslations("shared.layout");
  const format = useFormatter();
  const { status, user, token } = useAuth();

  const [unread, setUnread] = useState<number | null>(null);
  const [items, setItems] = useState<ApiNotification[] | null>(null);

  // Initial fetch of the badge + preview; live updates arrive on the
  // websocket below.
  useEffect(() => {
    if (!token) return;
    let active = true;
    void (async () => {
      try {
        const { unread_count } = await notificationsApi(token).unreadCount();
        if (active) setUnread(unread_count);
      } catch {
        // keep the last known count; realtime events re-sync the badge
      }
      try {
        const paginated = await notificationsApi(token).list(1);
        if (active) setItems(paginated.data);
      } catch {
        // preview stays on its last known state
      }
    })();
    return () => {
      active = false;
    };
  }, [token]);

  // Live badge + preview: every inserted notification broadcasts on the
  // signed-in user's private channel with the fresh unread total.
  useEffect(() => {
    if (!token || !user?.id) return;
    // Idempotent connect: the auth context owns the connection, but it
    // may attach after this effect in the same commit.
    const echo = connectNotificationEcho(token) ?? getNotificationEcho();
    if (!echo) return;

    const channel = `user.${user.id}`;
    echo
      .private(channel)
      .listen(".notification.created", (payload: RealtimeNotification) => {
        setUnread(payload.unread_count);
        setItems((previous) =>
          [
            payload,
            ...(previous ?? []).filter((row) => row.id !== payload.id),
          ].slice(0, 20),
        );
      });

    return () => {
      echo.leave(channel);
    };
  }, [token, user?.id]);

  if (status !== "authenticated") {
    return null;
  }

  const markRead = (notification: ApiNotification) => {
    if (notification.read_at || !token) return;
    setItems(
      (previous) =>
        previous?.map((row) =>
          row.id === notification.id
            ? { ...row, read_at: new Date().toISOString() }
            : row,
        ) ?? previous,
    );
    setUnread((previous) => (previous === null ? null : Math.max(0, previous - 1)));
    void notificationsApi(token).markRead(notification.id).catch(() => {
      // the next realtime event re-syncs the badge
    });
  };

  return (
    <div className="group relative">
      <Link
        href="/profile/notifications"
        aria-label={t("header.actions.notifications")}
        className="relative flex size-10 items-center justify-center rounded-default text-foreground transition-colors hover:bg-muted"
      >
        <span className="relative">
          <BellIcon className="size-5" />
          {unread !== null && unread > 0 ? (
            <span className="absolute -top-1 -end-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-semibold leading-none text-accent-foreground">
              {unread > 99 ? "99+" : unread.toLocaleString()}
            </span>
          ) : null}
        </span>
      </Link>

      <div className="invisible absolute end-0 top-full z-50 w-80 pt-4 opacity-0 transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
        <div className="rounded-default border border-border-light bg-white shadow-[0_24px_48px_-24px_rgba(23,23,23,0.15)]">
          {items === null ? (
            <p className="px-5 py-8 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("header.notifications.texts.empty")}
            </p>
          ) : items.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("header.notifications.texts.empty")}
            </p>
          ) : (
            <ul className="max-h-72 divide-y divide-border-light overflow-y-auto">
              {items.slice(0, 5).map((item) => {
                const Icon = rowIcon(item.code);
                const orderCode = item.meta?.order_code;

                return (
                  <li key={item.id}>
                    <Link
                      href={
                        orderCode
                          ? `/profile/orders?q=${encodeURIComponent(orderCode)}`
                          : "/profile/notifications"
                      }
                      onClick={() => markRead(item)}
                      className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted"
                    >
                      <span
                        aria-hidden="true"
                        className="grid size-9 shrink-0 place-items-center rounded-full bg-muted text-foreground"
                      >
                        <Icon className="size-4" />
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="flex items-center gap-2">
                          {item.read_at === null ? (
                            <span
                              aria-hidden="true"
                              className="size-2 shrink-0 rounded-full bg-accent"
                            />
                          ) : null}
                          <span className="truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                            {item.title}
                          </span>
                        </span>
                        {item.created_at ? (
                          <span className="mt-0.5 block text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                            {format.relativeTime(new Date(item.created_at))}
                          </span>
                        ) : null}
                      </span>
                    </Link>
                  </li>
                );
              })}
            </ul>
          )}
          <div className="border-t border-border-light p-4">
            <Link
              href="/profile/notifications"
              className="flex h-10 items-center justify-center rounded-default border border-border bg-white text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
            >
              {t("header.notifications.actions.view_all")}
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
