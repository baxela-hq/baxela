import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchInventoryStocks } from '../api/inventory-stocks.api'
import { FeatureRoutes } from '../data/routes'
import { type InventoryStock } from '../data/schema'

/** Paginated inventory stocks list (server-side pagination/filter/sort via URL search). */
export function useInventoryStocksList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<InventoryStock>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchInventoryStocks(search),
  })
}
