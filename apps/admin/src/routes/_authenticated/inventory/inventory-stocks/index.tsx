import { createFileRoute } from '@tanstack/react-router'
import { InventoryStocks } from '@/features/inventory/inventory-stocks'


export const Route = createFileRoute('/_authenticated/inventory/inventory-stocks/')({
  component: InventoryStocks,
})
