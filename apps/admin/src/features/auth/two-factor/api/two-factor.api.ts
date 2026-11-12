import { postRequest, getRequest } from '@/shared/lib/api-client'
import { type SingleResponse } from '@/shared/types/common.types.ts'
import {
  type TwoFactorConfirmResponse,
  type TwoFactorEnrollmentResponse,
  type TwoFactorSecret,
  type TwoFactorStatus,
} from '../types/two-factor'

const USER_URL = '/auth/user/2fa'
const PUBLIC_URL = '/auth/public/auth/2fa'

/** Self-service 2FA status. */
export async function fetchTwoFactorStatus(): Promise<TwoFactorStatus> {
  const { data } = await getRequest<SingleResponse<TwoFactorStatus>>(
    `${USER_URL}/status`
  )
  return data
}

/** Self-service enrollment step 1: generates a secret + QR code. */
export async function enableTwoFactor(): Promise<TwoFactorSecret> {
  const { data } = await postRequest<SingleResponse<TwoFactorSecret>, object>(
    `${USER_URL}/enable`,
    {}
  )
  return data
}

/** Self-service enrollment step 2: confirm with a TOTP code. */
export async function confirmTwoFactor(
  code: string
): Promise<TwoFactorConfirmResponse> {
  const { data } = await postRequest<
    SingleResponse<TwoFactorConfirmResponse>,
    { code: string }
  >(`${USER_URL}/confirm`, { code })
  return data
}

/** Disable 2FA (requires a valid TOTP or recovery code). */
export async function disableTwoFactor(code: string): Promise<void> {
  await postRequest<SingleResponse<null>, { code: string }>(
    `${USER_URL}/disable`,
    { code }
  )
}

/** Regenerate recovery codes (requires a valid code). */
export async function regenerateRecoveryCodes(
  code: string
): Promise<TwoFactorConfirmResponse> {
  const { data } = await postRequest<
    SingleResponse<TwoFactorConfirmResponse>,
    { code: string }
  >(`${USER_URL}/recovery-codes`, { code })
  return data
}

/**
 * Forced enrollment (super-admin at sign-in) step 1: fetch the secret
 * + QR with the challenge token — no bearer token exists yet.
 */
export async function fetchEnrollmentSecret(
  challengeToken: string
): Promise<TwoFactorSecret> {
  const { data } = await postRequest<
    SingleResponse<TwoFactorSecret>,
    { challenge_token: string }
  >(`${PUBLIC_URL}/enrollment/secret`, { challenge_token: challengeToken })
  return data
}

/**
 * Forced enrollment step 2: confirm with a TOTP code. Completes the
 * sign-in (token + settings) and returns the recovery codes once.
 */
export async function confirmEnrollment(
  challengeToken: string,
  code: string
): Promise<TwoFactorEnrollmentResponse> {
  const { data } = await postRequest<
    SingleResponse<TwoFactorEnrollmentResponse>,
    { challenge_token: string; code: string }
  >(`${PUBLIC_URL}/enrollment/confirm`, {
    challenge_token: challengeToken,
    code,
  })
  return data
}
