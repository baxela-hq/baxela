"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useAuth } from "@/context/auth-context";
import { notificationsApi } from "@/lib/api/notifications";
import type { ApiNotification } from "@/lib/api/types";
import {
  connectNotificationEcho,
  getNotificationEcho,
} from "@/lib/realtime/echo";

// Single source of truth for the notifications badge + header hover preview,
// shared by the header bell and the profile notifications page so marking a
// row read on either surface updates the other instantly (no React
// Query/SWR here — the cart context set this pattern). Mounted inside
// AuthProvider: the data refetches whenever the session changes and live
// updates arrive on the private Reverb channel (no polling).

/** Broadcast payload: a notification row plus the server-computed badge. */
type RealtimeNotification = ApiNotification & { unread_count: number };

interface NotificationsContextValue {
  /** null until the first load settles; drives the header badge. */
  unread: number | null;
  /** Newest rows for the header hover preview (first page, capped at 20). */
  preview: ApiNotification[] | null;
  /** Optimistically mark one row read (badge + preview) and PATCH. */
  markRead: (notification: ApiNotification) => void;
  /** PATCH read-all; syncs the badge from the server-computed count. */
  markAllRead: () => Promise<void>;
  /** Refetch the badge + preview; mutations fall back to it on failure. */
  refresh: () => Promise<void>;
}

const NotificationsContext = createContext<NotificationsContextValue | null>(
  null,
);

export function NotificationsProvider({ children }: { children: ReactNode }) {
  const { user, token } = useAuth();
  const [unread, setUnread] = useState<number | null>(null);
  const [preview, setPreview] = useState<ApiNotification[] | null>(null);

  const refresh = useCallback(async () => {
    if (!token) return;
    const [{ unread_count }, paginated] = await Promise.all([
      notificationsApi(token).unreadCount(),
      notificationsApi(token).list(1),
    ]);
    setUnread(unread_count);
    setPreview(paginated.data);
  }, [token]);

  // Initial fetch of the badge + preview; live updates arrive on the
  // websocket below. The async IIFE keeps every setState off the synchronous
  // effect path (the leading await yields first), and the signed-out branch
  // discards the previous session's rows so they never flash for the next
  // account.
  useEffect(() => {
    let active = true;

    void (async () => {
      await Promise.resolve();

      if (!token) {
        if (active) {
          setUnread(null);
          setPreview(null);
        }
        return;
      }

      try {
        const { unread_count } = await notificationsApi(token).unreadCount();
        if (active) setUnread(unread_count);
      } catch {
        // keep the last known count; realtime events re-sync the badge
      }
      try {
        const paginated = await notificationsApi(token).list(1);
        if (active) setPreview(paginated.data);
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
        setPreview((previous) =>
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

  const markRead = useCallback(
    (notification: ApiNotification) => {
      if (notification.read_at || !token) return;
      setPreview(
        (previous) =>
          previous?.map((row) =>
            row.id === notification.id
              ? { ...row, read_at: new Date().toISOString() }
              : row,
          ) ?? previous,
      );
      setUnread((previous) =>
        previous === null ? null : Math.max(0, previous - 1),
      );
      void notificationsApi(token).markRead(notification.id).catch(() => {
        // re-sync the badge to the server truth; a dead network makes
        // this a no-op too
        void refresh().catch(() => {});
      });
    },
    [token, refresh],
  );

  const markAllRead = useCallback(async () => {
    if (!token) return;
    const { unread_count } = await notificationsApi(token).markAllRead();
    setUnread(unread_count);
    setPreview(
      (previous) =>
        previous?.map((row) => ({
          ...row,
          read_at: row.read_at ?? new Date().toISOString(),
        })) ?? previous,
    );
  }, [token]);

  const value = useMemo<NotificationsContextValue>(
    () => ({ unread, preview, markRead, markAllRead, refresh }),
    [unread, preview, markRead, markAllRead, refresh],
  );

  return (
    <NotificationsContext.Provider value={value}>
      {children}
    </NotificationsContext.Provider>
  );
}

export function useNotifications(): NotificationsContextValue {
  const context = useContext(NotificationsContext);
  if (!context) {
    throw new Error(
      "useNotifications must be used within a NotificationsProvider",
    );
  }
  return context;
}
