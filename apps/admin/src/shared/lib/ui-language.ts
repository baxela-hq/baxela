import i18n from 'i18next'
import { fontForLanguageCode } from '@/config/language-fonts'
import { StorageKeys, StorageUtility } from '@/shared/lib/storage-utility'
import { type Direction } from '@/context/direction-provider'
import { type Language } from '@/shared/types/locale.types'

/**
 * UI language of the admin panel. Written to storage at sign-in and on
 * settings save, read at boot — the source of truth for translations and
 * (via `is_rtl`) for the layout direction. The built-in default is en/LTR
 * because the pre-auth pages (sign-in etc.) are English-only.
 */
const DEFAULT_UI_LANGUAGE_CODE = 'en'

export function getDefaultLanguageSnapshot(): Language | null {
  return StorageUtility.getItem<Language>(StorageKeys.DEFAULT_LANGUAGE)
}

/**
 * Direction implied by a language's `is_rtl` flag. No snapshot → the
 * built-in default language (en) → ltr.
 */
export function directionForLanguage(language: Language | null): Direction {
  if (!language) return 'ltr'
  return language.is_rtl ? 'rtl' : 'ltr'
}

/**
 * Set data-language-font on <html> when the language needs a dedicated font
 * family (see '@/config/language-fonts'); remove it otherwise so the user's
 * selected base font applies. The swap itself is CSS in 'src/styles/index.css'.
 */
export function applyLanguageFont(code: string): void {
  const font = fontForLanguageCode(code)
  const { documentElement } = document
  if (font) documentElement.dataset.languageFont = font
  else delete documentElement.dataset.languageFont
}

/**
 * Apply a store default language to the whole admin UI: switch i18next (all
 * mounted translations re-render), set `<html lang>` and the language font.
 * `null` falls back to the built-in default language.
 */
export function applyUiLanguage(language: Language | null): void {
  const code = language?.code ?? DEFAULT_UI_LANGUAGE_CODE
  // the i18next default export is the singleton configured in '@/i18n';
  // importing it directly avoids a circular dependency with that module
  void i18n.changeLanguage(code)
  document.documentElement.lang = code
  applyLanguageFont(code)
}

export { DEFAULT_UI_LANGUAGE_CODE }
