import { useQuery } from '@tanstack/react-query'
import { fetchSettings } from '../api/settings.api'
import { FeatureRoutes } from '../data/routes'
import { type Setting } from '../data/schema'

/** All settings rows (loaded once for the settings form). */
export function useSettings(search: Record<string, unknown>) {
  return useQuery<Setting[]>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchSettings(search),
  })
}
