import { deleteRequest, getRequest } from '@/shared/lib/api-client'
import { type SingleResponse } from '@/shared/types/common.types'
import { type Session } from '../data/schema'

const BASE_URL = '/auth/user/account/sessions'

/** The signed-in user's active sessions. */
export async function fetchAccountSessions(): Promise<Session[]> {
  const { data } = await getRequest<SingleResponse<Session[]>>(BASE_URL)
  return data
}

/** Revoke one of the user's own sessions. */
export function revokeAccountSession(sessionId: number) {
  return deleteRequest(`${BASE_URL}/${sessionId}`)
}

/**
 * "Sign out everywhere": revokes every session, INCLUDING the current
 * one — the caller is signed out immediately.
 */
export function revokeAllAccountSessions() {
  return deleteRequest(BASE_URL)
}
