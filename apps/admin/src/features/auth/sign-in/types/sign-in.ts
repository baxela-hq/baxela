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
}


export interface SignInRequest {
    email: string;
    password: string;
    /** Device identifier; injected in the sign-in API layer. */
    device_name?: string;
}
