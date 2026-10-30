import { z } from 'zod'
import type { Currency } from '@/shared/types/locale.types'

export type CurrencyRow = Currency

// Mirrors the backend UpdateCurrencyRequest contract: unsigned decimal
// string with 1–8 fraction digits, non-zero (the base currency is pinned
// to 1 server-side and is never editable from the UI).
export const formSchema = z.object({
  // Set at creation only — the edit drawer keeps it read-only
  code: z.string().length(3, 'invalid-code').regex(/^[A-Z]{3}$/, 'invalid-code'),
  name: z.string().min(1, 'required').max(100),
  native_name: z.string().max(100),
  symbol: z.string().max(10),
  decimal_places: z.number().int().min(0).max(8),
  is_symbol_right: z.boolean(),
  exchange_rate: z
    .string()
    .regex(/^\d+\.\d{1,8}$/, 'invalid-rate')
    .refine((value) => Number.parseFloat(value) !== 0, 'invalid-rate'),
})
export type CurrencyForm = z.infer<typeof formSchema>

export function buildEditValues(currentRow?: CurrencyRow): CurrencyForm {
  if (!currentRow) {
    return {
      code: '',
      name: '',
      native_name: '',
      symbol: '',
      decimal_places: 2,
      is_symbol_right: false,
      exchange_rate: '1.00000000',
    }
  }

  return {
    code: currentRow.code,
    name: currentRow.name,
    native_name: currentRow.native_name ?? '',
    symbol: currentRow.symbol ?? '',
    decimal_places: currentRow.decimal_places,
    is_symbol_right: currentRow.is_symbol_right,
    exchange_rate: currentRow.exchange_rate,
  }
}
