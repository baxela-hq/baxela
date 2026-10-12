import {
  deleteRequest,
  getRequest,
  postRequest,
} from '@/shared/lib/api-client'
import type { SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'notification/user/telegram'

export interface TelegramBotInfo {
  bot_username: string | null
  bot_url: string | null
}

export interface TelegramSubscription {
  id: number
  chat_id: number
  username: string | null
  linked_at: string | null
  created_at: string | null
}

/** Bot identity for the "open bot" deep link; nulls = Telegram unconfigured. */
export function fetchTelegramBotInfo() {
  return getRequest<SingleResponse<TelegramBotInfo>>(
    'notification/telegram/bot-info'
  )
}

/** The caller's linked chat, or null when unlinked. */
export function fetchTelegramSubscription() {
  return getRequest<SingleResponse<TelegramSubscription | null>>(
    `${BASE_URL}/subscription`
  )
}

/** Exactly one credential is set: the /start code or a directly typed chat id. */
export function linkTelegram(input: {
  code: string | null
  chat_id: number | null
}) {
  return postRequest<SingleResponse<TelegramSubscription>, typeof input>(
    `${BASE_URL}/link`,
    input
  )
}

export function unlinkTelegram() {
  return deleteRequest<void>(`${BASE_URL}/subscription`)
}

/** Canary message to the linked chat, verifying the whole delivery chain. */
export function sendTelegramTest() {
  return postRequest<SingleResponse<{ sent: boolean }>, unknown>(
    `${BASE_URL}/test`,
    {}
  )
}
