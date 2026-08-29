import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { pickTranslation } from '@/shared/lib/locale'
import { buildHierarchy, excludeSubtree } from '@/shared/lib/tree'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchCategories } from '../api/categories.api'
import { FeatureRoutes } from '../data/routes'
import { type Category, type CategoryNode } from '../data/schema'

/** Paginated categories list (server-side pagination/filter/sort via URL search). */
export function useCategoriesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Category>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchCategories(search),
    // placeholderData: (prev) => prev,
  })
}

/**
 * Flattened category tree for parent-select inputs (single large fetch,
 * cached and shared with the create drawer). When `excludeId` is given,
 * that category and its descendants are excluded (prevents making a
 * category its own parent).
 */
export function useCategoryTree(excludeId?: number | null): CategoryNode[] {
  const { data } = useQuery<PaginatedResponse<Category>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'tree'],
    queryFn: () => fetchCategories({ per_page: 1000 }),
  })

  return useMemo(
    () =>
      excludeSubtree(
        buildHierarchy<Category>(data?.data ?? [], (category) => {
          const translation = pickTranslation(category.translations)
          return translation?.title ?? ''
        }),
        excludeId
      ),
    [data, excludeId]
  )
}
