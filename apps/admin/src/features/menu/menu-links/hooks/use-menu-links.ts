import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchMenuLinks } from '../api/menu-links.api'
import { FeatureRoutes } from '../data/routes'
import { type MenuLink } from '../data/schema'

/** Paginated menu-links list of one parent menu (nested resource). */
export function useMenuLinksList(
  menuId: string,
  search: Record<string, unknown>
) {
  return useQuery<PaginatedResponse<MenuLink>>({
    queryKey: [FeatureRoutes.CACHE_KEY, menuId, search],
    queryFn: () => fetchMenuLinks(menuId, search),
    // placeholderData: (prev) => prev,
  })
}

/**
 * All links of one menu in a single large fetch. Used by the parent select
 * (tree) and the sort dialog. Memoized so the array identity is stable
 * between renders.
 */
export function useMenuLinksAll(menuId: string): MenuLink[] {
  const { data } = useQuery<PaginatedResponse<MenuLink>>({
    queryKey: [FeatureRoutes.CACHE_KEY, menuId, 'all'],
    queryFn: () => fetchMenuLinks(menuId, { per_page: 1000 }),
    enabled: Boolean(menuId),
  })
  return useMemo(() => data?.data ?? [], [data])
}
