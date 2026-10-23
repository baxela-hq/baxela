import { routing } from "@/i18n/routing";

/**
 * Locale metadata used across the storefront so adding a locale to
 * i18n/routing.ts needs no further code changes here.
 */

// Base codes of the world's RTL scripts (mirrors the backend languages
// table's is_rtl flag). Vazirmatn covers all Arabic-script locales.
const RTL_LOCALES = new Set([
  "ar",
  "ckb",
  "dv",
  "fa",
  "he",
  "ku",
  "ps",
  "sd",
  "ug",
  "ur",
  "yi",
]);

// Native endonym shown in the language switcher.
const NATIVE_NAMES: Record<string, string> = {
  en: "English",
  fa: "فارسی",
  de: "Deutsch",
  fr: "Français",
  es: "Español",
  ar: "العربية",
};

// First-visit display currency per locale, used before the visitor has
// picked one (the baxela-currency cookie always wins once set). Absent
// locales fall back to the store base currency. The mapped code must be
// active on the backend, otherwise the base is used.
const LOCALE_DEFAULT_CURRENCY: Record<string, string> = {
  de: "EUR",
  fr: "EUR",
  es: "EUR",
};

export function localeDirection(locale: string): "ltr" | "rtl" {
  return RTL_LOCALES.has(locale) ? "rtl" : "ltr";
}

export function nativeName(locale: string): string {
  return NATIVE_NAMES[locale] ?? locale;
}

export function localeDefaultCurrency(locale: string): string | undefined {
  return LOCALE_DEFAULT_CURRENCY[locale];
}

export const locales = routing.locales;
