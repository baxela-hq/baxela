import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import {
  fetchAttributeTemplates,
  fetchOneAttributeTemplate,
} from '../api/attribute-templates.api'
import { FeatureRoutes } from '../data/routes'
import { type AttributeTemplate } from '../data/schema'

/** Paginated attribute-templates list (server-side pagination/filter/sort via URL search). */
export function useAttributeTemplatesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<AttributeTemplate>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchAttributeTemplates(search),
  })
}

/**
 * Template detail with its ordered groups (the list response carries no
 * `groups[]`, so the edit drawer fetches the detail for edit values).
 * Disabled until `enabled` flips true (edit mode only).
 */
export function useOneAttributeTemplate(id: number | undefined, enabled: boolean) {
  return useQuery<AttributeTemplate>({
    queryKey: [FeatureRoutes.CACHE_SINGLE_KEY, id],
    queryFn: () => fetchOneAttributeTemplate(id!.toString()),
    enabled,
  })
}

/**
 * Active attribute templates in one large fetch (product form template
 * picker). Memoized so the array identity is stable between renders.
 */
export function useActiveAttributeTemplates(): AttributeTemplate[] {
  const { data } = useQuery<PaginatedResponse<AttributeTemplate>>({
    queryKey: ['attribute-templates', { per_page: 1000 }],
    queryFn: () => fetchAttributeTemplates({ per_page: 1000 }),
  })
  return useMemo(
    () => (data?.data ?? []).filter((template) => template.is_active),
    [data]
  )
}
