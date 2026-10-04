// Client for the user-audience support ticket endpoints (see api/bruno
// under Support/User). All calls need the Sanctum bearer token.

import { api, buildQuery } from "./client";
import type {
  ApiTicket,
  ApiTicketMessage,
  ApiTicketStatus,
  Paginated,
} from "./types";

export interface CreateTicketInput {
  subject: string;
  body: string;
  order_code?: string | null;
}

/** Statuses a customer may set on their own ticket (answered is staff-only). */
export type CustomerTicketStatus = Extract<ApiTicketStatus, "open" | "closed">;

export function ticketsApi(token: string | null) {
  const options = { token };

  return {
    /** Newest-first feed with an optional exact status filter. */
    list: (page = 1, status?: ApiTicketStatus | "all") =>
      api.get<Paginated<ApiTicket>>(
        `/support/user/tickets${buildQuery({
          page,
          ...(status && status !== "all" ? { "filter[status]": status } : {}),
        })}`,
        options,
      ),
    create: (input: CreateTicketInput) =>
      api.post<ApiTicket>("/support/user/tickets", input, options),
    get: (id: number) => api.get<ApiTicket>(`/support/user/tickets/${id}`, options),
    /** A customer reply reopens a closed ticket server-side. */
    reply: (id: number, body: string) =>
      api.post<ApiTicketMessage>(
        `/support/user/tickets/${id}/messages`,
        { body },
        options,
      ),
    updateStatus: (id: number, status: CustomerTicketStatus) =>
      api.patch<ApiTicket>(`/support/user/tickets/${id}/status`, { status }, options),
  };
}
