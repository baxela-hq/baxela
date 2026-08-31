import { useMemo } from 'react'

import {
  formatDate,
  formatDateTime,
  getActiveLocale,
} from '@/shared/lib/datetime.ts'

/**
 * Component-facing datetime formatters bound to the active locale. Resolves
 * the locale once per component lifetime instead of re-reading storage on
 * every cell render.
 */
export function useFormatDateTime() {
  const locale = useMemo(() => getActiveLocale(), [])

  return useMemo(
    () => ({
      formatDate: (value?: string | null) => formatDate(value, locale),
      formatDateTime: (value?: string | null) => formatDateTime(value, locale),
    }),
    [locale]
  )
}
