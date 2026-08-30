export interface Translation {
  language_id: number;
  language: string;
}

export interface Currency {
  "id": number,
  "code": string,
  "name": string,
  "native_name": string,
  "decimal_places": number,
  "symbol": string,
  "is_default": boolean,
  "is_symbol_right": boolean,
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

export interface Country {
  "id": number,
  "code": string,
  "code3": string,
  "name": string,
  "native_name": string,
  "emoji": string
}