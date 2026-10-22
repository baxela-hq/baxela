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

export function localeDirection(locale: string): "ltr" | "rtl" {
  return RTL_LOCALES.has(locale) ? "rtl" : "ltr";
}

export function nativeName(locale: string): string {
  return NATIVE_NAMES[locale] ?? locale;
}

export const locales = routing.locales;
