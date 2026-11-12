import { type Settings } from '@/features/auth/sign-in/types/sign-in'

/** Self-service 2FA status (GET /auth/user/2fa/status). */
export interface TwoFactorStatus {
    enrolled: boolean
    confirmed_at: string | null
    recovery_codes_remaining: number
}

/** Step 1 of enrollment: the unconfirmed secret + inline SVG QR code. */
export interface TwoFactorSecret {
    secret: string
    qr_code_uri: string
}

/** Step 2 (self-service): recovery codes returned exactly once. */
export interface TwoFactorConfirmResponse {
    recovery_codes: string[]
}

/**
 * Step 2 (forced enrollment at sign-in): a full sign-in payload
 * (the token is issued immediately — enrollment completes the sign-in)
 * plus the one-time recovery codes.
 */
export interface TwoFactorEnrollmentResponse {
    token: string
    settings: Settings
    recovery_codes: string[]
}
