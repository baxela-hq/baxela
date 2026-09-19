import i18n from 'i18next'
import { StorageKeys, StorageUtility } from '@/shared/lib/storage-utility'
import { type Direction } from '@/context/direction-provider'
import { type Language } from '@/shared/types/locale.types'

/**
 * UI language of the admin panel. Written to storage at sign-in and on
 * settings save, read at boot — the source of truth for translations and
 * (via `is_rtl`) for the layout direction.
 */
const DEFAULT_UI_LANGUAGE_CODE = 'fa'

export function getDefaultLanguageSnapshot(): Language | null {
  return StorageUtility.getItem<Language>(StorageKeys.DEFAULT_LANGUAGE)
}

/**
 * Direction implied by a language's `is_rtl` flag. No snapshot → the
 * built-in default language (fa) → rtl.
 */
export function directionForLanguage(language: Language | null): Direction {
  if (!language) return 'rtl'
  return language.is_rtl ? 'rtl' : 'ltr'
}

/**
 * Apply a store default language to the whole admin UI: switch i18next (all
 * mounted translations re-render) and set `<html lang>`. `null` falls back
 * to the built-in default language.
 */
export function applyUiLanguage(language: Language | null): void {
  const code = language?.code ?? DEFAULT_UI_LANGUAGE_CODE
  // the i18next default export is the singleton configured in '@/i18n';
  // importing it directly avoids a circular dependency with that module
  void i18n.changeLanguage(code)
  document.documentElement.lang = code
}

export { DEFAULT_UI_LANGUAGE_CODE }
