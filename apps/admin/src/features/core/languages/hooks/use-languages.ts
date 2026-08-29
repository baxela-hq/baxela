import { useQuery } from '@tanstack/react-query'
import type { Language } from '@/shared/types/locale.types'
import { fetchLanguages } from '../api/languages.api'

/**
 * Available languages (dynamic, backend-driven). Shared query key 'languages'
 * — every consumer dedupes into one request.
 */
export function useLanguages() {
  return useQuery<Language[]>({
    queryKey: ['languages'],
    queryFn: () => fetchLanguages(),
  })
}
