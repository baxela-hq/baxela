import i18n from '@/i18n'
import { StorageKeys, StorageUtility } from '@/shared/lib/storage-utility.ts'
import type { Language } from '@/shared/types/locale.types.ts'

/**
 * Locale-aware datetime formatting for entity data (created_at / updated_at).
 *
 * The active locale is the panel's UI language (i18next), so an fa panel shows
 * Persian dates even when the store's default content language is e.g. en-US.
 * Falls back to the stored default language's locale (persisted at sign-in)
 * and finally the runtime default. Handing e.g. 'fa' / 'fa-IR' to Intl picks
 * the Persian calendar and digits automatically — 'en-US' yields Gregorian —
 * with no extra calendar configuration.
 */

const PLACEHOLDER = '—'

const formatterCache = new Map<string, Intl.DateTimeFormat>()

function resolveLocale(): string | undefined {
  const uiLanguage = i18n.resolvedLanguage
  if (uiLanguage) return uiLanguage
  return StorageUtility.getItem<Language>(
    StorageKeys.DEFAULT_LANGUAGE
  )?.locale
}

function getFormatter(
  locale: string | undefined,
  options: Intl.DateTimeFormatOptions
): Intl.DateTimeFormat {
  const key = `${locale ?? 'default'}:${JSON.stringify(options)}`
  const cached = formatterCache.get(key)
  if (cached) return cached

  // An invalid locale tag (bad backend data) makes the constructor throw
  // RangeError — fall back to the runtime default instead of crashing render
  let formatter: Intl.DateTimeFormat
  try {
    formatter = new Intl.DateTimeFormat(locale, options)
  } catch {
    formatter = new Intl.DateTimeFormat(undefined, options)
  }
  formatterCache.set(key, formatter)
  return formatter
}

function format(
  value: string | null | undefined,
  locale: string | undefined,
  options: Intl.DateTimeFormatOptions
): string {
  if (!value) return PLACEHOLDER
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return PLACEHOLDER
  return getFormatter(locale, options).format(date)
}

/** The locale entity datetimes should be formatted with. */
export function getActiveLocale(): string | undefined {
  return resolveLocale()
}

/** Date-only rendering, e.g. "Dec 19, 2012". */
export function formatDate(
  value?: string | null,
  locale: string | undefined = resolveLocale()
): string {
  return format(value, locale, { dateStyle: 'medium' })
}

/** Date + time rendering, e.g. "Dec 19, 2012, 10:00 AM". */
export function formatDateTime(
  value?: string | null,
  locale: string | undefined = resolveLocale()
): string {
  return format(value, locale, { dateStyle: 'medium', timeStyle: 'short' })
}
