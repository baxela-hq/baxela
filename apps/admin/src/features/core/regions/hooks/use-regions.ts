import { useQuery } from '@tanstack/react-query'
import { fetchRegions } from '../api/regions.api'

/**
 * Regions for a country (backend-driven reference data); disabled when no
 * country is selected — the drawer renders no region field then.
 */
export function useRegions(countryCode: string) {
  return useQuery({
    queryKey: ['regions', countryCode],
    queryFn: () => fetchRegions(countryCode),
    enabled: countryCode.length === 2,
    staleTime: Infinity,
  })
}
