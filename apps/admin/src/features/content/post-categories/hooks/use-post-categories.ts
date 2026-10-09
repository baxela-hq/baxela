import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { pickTranslation } from '@/shared/lib/locale'
import { buildHierarchy, excludeSubtree } from '@/shared/lib/tree'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchPostCategories } from '../api/post-categories.api'
import { FeatureRoutes } from '../data/routes'
import { type PostCategory, type PostCategoryNode } from '../data/schema'

/** Paginated post categories list (server-side pagination/filter/sort via URL search). */
export function usePostCategoriesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<PostCategory>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchPostCategories(search),
    // placeholderData: (prev) => prev,
  })
}

/**
 * Flattened post category tree for parent-select inputs (single large fetch,
 * cached and shared with the create drawer). When `excludeId` is given, that
 * category and its descendants are excluded (prevents making a category its
 * own parent).
 */
export function usePostCategoryTree(excludeId?: number | null): PostCategoryNode[] {
  const { data } = useQuery<PaginatedResponse<PostCategory>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'tree'],
    queryFn: () => fetchPostCategories({ per_page: 1000 }),
  })

  return useMemo(
    () =>
      excludeSubtree(
        buildHierarchy<PostCategory>(data?.data ?? [], (category) => {
          const translation = pickTranslation(category.translations)
          return translation?.title ?? ''
        }),
        excludeId
      ),
    [data, excludeId]
  )
}
