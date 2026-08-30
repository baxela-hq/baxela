import { useQuery } from '@tanstack/react-query'
import { fetchCountries } from '../api/countries.api'

/**
 * All countries (backend-driven, static reference data). Shared query key
 * 'countries' — every consumer dedupes into one request.
 */
export function useCountries() {
  return useQuery({
    queryKey: ['countries'],
    queryFn: () => fetchCountries(),
    staleTime: Infinity,
  })
}
