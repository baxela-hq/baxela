import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchMenus, fetchOneMenu } from '../api/menus.api'
import { FeatureRoutes } from '../data/routes'
import { type Menu } from '../data/schema'

/** Paginated menus list (server-side pagination/filter/sort via URL search). */
export function useMenusList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Menu>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchMenus(search),
    // placeholderData: (prev) => prev,
  })
}

/** Single menu by id (parent lookup on the menu-links page). */
export function useOneMenu(id: string) {
  return useQuery<Menu>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'single', id],
    queryFn: () => fetchOneMenu(id),
    // placeholderData: (prev) => prev,
  })
}
