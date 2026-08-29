import type { Option } from '../../options/data/schema'

export type MatrixValue = { id: number; title: string }
export type MatrixOption = {
  option: Option
  values: MatrixValue[]
  selectedIds: number[]
}

export function cartesian<T>(groups: T[][]): T[][] {
  return groups.reduce<T[][]>(
    (acc, combo) => acc.flatMap((prefix) => combo.map((item) => [...prefix, item])),
    [[]]
  )
}

export function sanitizeSkuPart(value: string): string {
  return value
    .replace(/[^A-Za-z0-9_-]/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-+|-+$/g, '')
}

export type GeneratedVariant = {
  sku: string
  price: string
  quantity: number
  is_default: boolean
  currency_id: number | null
  option_value_ids: number[]
}

/**
 * Variant rows for the cartesian product of the selected option values:
 * SKUs are built from the product title + value titles (slugified), deduped
 * with -2/-3 suffixes; the first combo is the default variant.
 */
export function generateVariants(
  title: string,
  matrix: MatrixOption[],
  currencyId: number | null
): GeneratedVariant[] {
  const active = matrix.filter((m) => m.selectedIds.length > 0)
  if (active.length === 0) return []

  const groups = active.map((m) =>
    m.selectedIds.map((valueId) => {
      const value = m.values.find((v) => v.id === valueId)
      return { id: valueId, title: value?.title ?? '' }
    })
  )
  const combos = cartesian(groups)
  const seen = new Set<string>()
  return combos.map((combo, index) => {
    const baseSku = [title, ...combo.map((c) => c.title)]
      .filter(Boolean)
      .map(sanitizeSkuPart)
      .filter(Boolean)
      .join('-')
    let sku = baseSku
    let suffix = 2
    while (seen.has(sku.toLowerCase())) {
      sku = `${baseSku}-${suffix++}`
    }
    seen.add(sku.toLowerCase())
    return {
      sku,
      price: '',
      quantity: 0,
      is_default: index === 0,
      currency_id: currencyId,
      option_value_ids: combo.map((c) => c.id),
    }
  })
}
