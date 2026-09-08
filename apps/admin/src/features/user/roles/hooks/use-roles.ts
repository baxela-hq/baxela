import { useQuery } from '@tanstack/react-query'
import { fetchRoles } from '../api/roles.api'

/**
 * All staff roles (backend-driven reference data). Shared query key
 * 'roles' — every consumer dedupes into one request.
 */
export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: () => fetchRoles(),
  })
}
