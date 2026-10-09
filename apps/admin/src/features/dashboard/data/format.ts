import { getActiveLocale } from '@/shared/lib/datetime.ts'

/**
 * Locale-aware formatters for the dashboard's stat/chart values, so an fa
 * panel renders Persian digits and month names, an en panel Latin ones.
 */

/** Count rendering, e.g. "12,234" / "۱۲٬۲۳۴". */
export function formatCount(value: number): string {
  return new Intl.NumberFormat(getActiveLocale()).format(value)
}

/** Compact chart-axis rendering, e.g. "45K". */
export function formatCompactNumber(value: number): string {
  return new Intl.NumberFormat(getActiveLocale(), {
    notation: 'compact',
  }).format(value)
}

/** Signed percent for stat-card change lines, e.g. "+12.4%". */
export function formatChangePercent(value: number): string {
  return new Intl.NumberFormat(getActiveLocale(), {
    style: 'percent',
    signDisplay: 'always',
    maximumFractionDigits: 1,
  }).format(value / 100)
}

/** Short localized month label for a "YYYY-MM" series key. */
export function formatMonthLabel(month: string): string {
  const date = new Date(`${month}-01T00:00:00`)
  if (Number.isNaN(date.getTime())) return month
  return new Intl.DateTimeFormat(getActiveLocale(), {
    month: 'short',
  }).format(date)
}

/** Short localized day label for a "YYYY-MM-DD" series key, e.g. "Sep 12". */
export function formatDayLabel(day: string): string {
  const date = new Date(`${day}T00:00:00`)
  if (Number.isNaN(date.getTime())) return day
  return new Intl.DateTimeFormat(getActiveLocale(), {
    month: 'short',
    day: 'numeric',
  }).format(date)
}
