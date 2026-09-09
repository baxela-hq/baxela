import { useQuery } from '@tanstack/react-query'
import { fetchPermissions } from '../api/permissions.api'

/** Full permission catalog for the role form picker. */
export function usePermissions() {
  return useQuery({
    queryKey: ['permissions'],
    queryFn: () => fetchPermissions(),
    staleTime: 5 * 60 * 1000,
  })
}
