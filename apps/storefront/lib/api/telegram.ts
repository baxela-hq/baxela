// Client for the telegram linking endpoints (see api/bruno under
// Notification/User + Public). Linking calls need the Sanctum bearer
// token; the bot identity is public.

import { api } from "./client";

export interface ApiTelegramBotInfo {
  bot_username: string | null;
  bot_url: string | null;
}

export interface ApiTelegramSubscription {
  id: number;
  chat_id: number;
  username: string | null;
  linked_at: string | null;
  created_at: string | null;
}

/** Exactly one credential is set: the /start code or a typed chat id. */
export interface TelegramLinkInput {
  code: string | null;
  chat_id: number | null;
}

export function telegramApi(token: string | null) {
  const options = { token };

  return {
    /** The caller's linked chat; null when unlinked. */
    subscription: () =>
      api.get<ApiTelegramSubscription | null>(
        "/notification/user/telegram/subscription",
        options,
      ),
    link: (input: TelegramLinkInput) =>
      api.post<ApiTelegramSubscription>(
        "/notification/user/telegram/link",
        input,
        options,
      ),
    unlink: () =>
      api.delete<void>("/notification/user/telegram/subscription", options),
    /** Canary message to the linked chat. */
    test: () =>
      api.post<{ sent: boolean }>(
        "/notification/user/telegram/test",
        {},
        options,
      ),
  };
}

/** Bot identity for the "open bot" deep link; nulls = Telegram off. */
export function fetchTelegramBotInfo() {
  return api.get<ApiTelegramBotInfo>("/notification/telegram/bot-info");
}
