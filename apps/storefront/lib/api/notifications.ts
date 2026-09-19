// Client for the user-audience notification endpoints (see api/bruno under
// Notification/User). All calls need the Sanctum bearer token.

import { api, buildQuery } from "./client";
import type { ApiNotification, Paginated } from "./types";

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
  };
}
