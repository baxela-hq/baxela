export interface Translation {
  language_id: number;
  language: string;
}

export interface Currency {
  "id": number,
  "locale": string,
  "name": string,
  "native_name": string,
  "code": string,
  "code3": string,
  "symbol": string,
  "is_symbol_right": boolean,
  "is_rtl": boolean,
  "is_active": boolean,
  "is_default": boolean,
  "position": number,
  "date_format": string,
  "time_format": string,
  "created_at": string,
  "updated_at": string
}

export interface Language {
  "id": number,
  "locale": string,
  "name": string,
  "native_name": string,
  "code": string,
  "code3": string,
  "is_rtl": boolean,
  "is_active": boolean,
  "is_default": boolean,
  "position": number,
  "date_format": string,
  "time_format": string,
  "created_at": string,
  "updated_at": string
}