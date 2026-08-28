import { StorageUtility, StorageKeys } from '@/shared/lib/storage-utility.ts'
import type { Translation, Language, Currency } from '@/shared/types/locale.types.ts'

export function getDefaultLanguage(translations: Translation[]): number | null {
  if (translations.length === 0) return null

  if (translations.length === 1) return 0

  const defaultLanguage: Language | null = StorageUtility.getItem<Language>(
    StorageKeys.DEFAULT_LANGUAGE
  )

  if (defaultLanguage === null) return null

  for (let i = 0; i < translations.length; i++) {
    if (translations[i].language === defaultLanguage.code) {
      return i;
    }
  }

  return null
}


export function getDefaultCurrency(): Currency | null {


  const defaultLanguage: Currency | null = StorageUtility.getItem<Currency>(
    StorageKeys.DEFAULT_CURRENCY
  )

  return defaultLanguage
}

