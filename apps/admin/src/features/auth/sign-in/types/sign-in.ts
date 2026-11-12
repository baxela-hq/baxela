import {
  type Currency,
  type Language,
} from '@/shared/types/locale.types'

export interface UserModel {
    email: string,
    email_verified_at: string,
    is_active: boolean,
}

export interface Settings {
  currency: Currency,
  language: Language,
}


export interface SignInResponse {
    token: string;
    user: UserModel;
    settings: Settings;
    /** Present when 2FA is enrolled — no bearer token in this response. */
    two_factor_required?: boolean;
    /** Present when a super-admin must enroll — no bearer token. */
    two_factor_enrollment_required?: boolean;
    /** The challenge token (bridge to the verify/enrollment endpoints). */
    challenge_token?: string;
}


export interface SignInRequest {
    email: string;
    password: string;
    /** Device identifier; injected in the sign-in API layer. */
    device_name?: string;
}
