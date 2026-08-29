import { useQuery } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchUsers } from '../api/users.api'
import { FeatureRoutes } from '../data/routes'
import { type User } from '../data/schema'

/** Paginated users list (server-side pagination/filter/sort via URL search). */
export function useUsersList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<User>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchUsers(search),
    // placeholderData: (prev) => prev,
  })
}
