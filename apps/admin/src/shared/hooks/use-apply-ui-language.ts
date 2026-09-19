import { useDirection } from '@/context/direction-provider'
import { applyUiLanguage } from '@/shared/lib/ui-language'
import { type Language } from '@/shared/types/locale.types'

/**
 * Single entry point for the UI-language sync points (sign-in, settings
 * save, sign-out): switches i18next + `<html lang>` and syncs layout
 * direction to the language's `is_rtl` flag. Pass `null` to fall back to
 * the built-in default (fa / RTL) and drop any manual direction override.
 */
export function useApplyUiLanguage() {
  const { syncDirFromLanguage, resetDir } = useDirection()

  return (language: Language | null) => {
    applyUiLanguage(language)
    if (language === null) {
      resetDir()
      return
    }
    syncDirFromLanguage(language)
  }
}
