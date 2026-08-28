
export interface UserModel {
    email: string,
    email_verified_at: string,
    is_active: boolean,
    is_admin: boolean,
}

export interface Currency {
  id: number,
  code: string,
  "name": string,
  "native_name": string,
  "decimal_places": string,
  "symbol": string,
  "is_symbol_right": false
}

export interface Language {
  id: number,
  "locale": string,
  "name": string,
  "native_name": string,
  "code3": string,
  "is_rtl": boolean,
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
}


