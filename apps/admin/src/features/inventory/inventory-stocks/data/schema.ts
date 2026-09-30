import { z } from 'zod'
import { translationSchema } from '@/features/catalog/products/data/schema'

// relation payload included by the stocks list (variant + product title columns)
const stockVariantSchema = z.object({
  id: z.number(),
  sku: z.string().nullish(),
  product: z
    .object({
      id: z.number(),
      translations: z.array(translationSchema).nullish(),
    })
    .nullish(),
})

export const inventoryStockSchema = z.object({
  id: z.number(),
  variant_id: z.number(),
  quantity: z.number(),
  variant: stockVariantSchema.nullish(),
  created_at: z.string().nullish(),
  updated_at: z.string().nullish(),
})
export type InventoryStock = z.infer<typeof inventoryStockSchema>

export const formSchema = z.object({
  variant_id: z.number().min(1, 'required'),
  quantity: z.number().int().min(1, 'required').max(100000),
})
export type InventoryStockForm = z.infer<typeof formSchema>

export const defaultValues: InventoryStockForm = {
  variant_id: 0,
  quantity: 1,
}

export function buildEditValues(currentRow?: InventoryStock): InventoryStockForm {
  if (!currentRow) return { ...defaultValues }

  return {
    variant_id: currentRow.variant_id,
    quantity: currentRow.quantity,
  }
}
