import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchOptionValues } from '../api/option-values.api'
import { FeatureRoutes } from '../data/routes'
import { type OptionValue } from '../data/schema'

/** Paginated option-values list of one parent option (nested resource). */
export function useOptionValuesList(
  optionId: string,
  search: Record<string, unknown>
) {
  return useQuery<PaginatedResponse<OptionValue>>({
    queryKey: [FeatureRoutes.CACHE_KEY, optionId, search],
    queryFn: () => fetchOptionValues(optionId, search),
    // placeholderData: (prev) => prev,
  })
}
