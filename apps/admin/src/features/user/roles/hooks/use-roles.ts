import { useQuery } from '@tanstack/react-query'
import { fetchRoles } from '../api/roles.api'
import { FeatureRoutes } from '../data/routes'

/**
 * All staff roles with their permissions (backend-driven reference data).
 * Shared query key 'roles' — every consumer (users drawer, roles page)
 * dedupes into one request.
 */
export function useRoles() {
  return useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY],
    queryFn: () => fetchRoles(),
  })
}
