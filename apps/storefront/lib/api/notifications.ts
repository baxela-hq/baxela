// Client for the user-audience notification endpoints (see api/bruno under
// Notification/User). All calls need the Sanctum bearer token.

import { api, buildQuery } from "./client";
import type { ApiNotification, Paginated } from "./types";

/** PushSubscription JSON as the browser hands it to us. */
export interface PushSubscriptionInput {
  endpoint: string;
  keys: { p256dh?: string | null; auth?: string | null } | null;
  user_agent?: string | null;
  locale?: string | null;
}

export interface ApiPushSubscription {
  id: number;
  endpoint: string;
  user_agent: string | null;
  locale: string | null;
  created_at: string | null;
}

export function notificationsApi(token: string | null) {
  const options = { token };

  return {
    /** Newest-first feed; 15 per page (server-paginated). */
    list: (page = 1) =>
      api.get<Paginated<ApiNotification>>(
        `/notification/user/notifications${buildQuery({ page })}`,
        options,
      ),
    unreadCount: () =>
      api.get<{ unread_count: number }>(
        "/notification/user/notifications/unread-count",
        options,
      ),
    markRead: (id: number) =>
      api.patch<ApiNotification>(
        `/notification/user/notifications/${id}/read`,
        undefined,
        options,
      ),
    markAllRead: () =>
      api.patch<{ unread_count: number }>(
        "/notification/user/notifications/read-all",
        undefined,
        options,
      ),
    /** Registered browsers for this account ({data} envelope is unwrapped). */
    pushSubscriptions: () =>
      api.get<ApiPushSubscription[]>(
        "/notification/user/push-subscriptions",
        options,
      ),
    savePushSubscription: (subscription: PushSubscriptionInput) =>
      api.post<ApiPushSubscription>(
        "/notification/user/push-subscriptions",
        subscription,
        options,
      ),
    deletePushSubscription: (endpoint: string) =>
      api.delete<void>("/notification/user/push-subscriptions", {
        token,
        body: { endpoint },
      }),
  };
}

/** VAPID public key browsers need before subscribing. Null = web push off. */
export function fetchVapidPublicKey() {
  return api.get<{ public_key: string | null }>(
    "/notification/webpush/vapid-public-key",
  );
}
