import { getRequest } from '@/shared/lib/api-client'
import type { AllResponse } from '@/shared/types/common.types'

export interface Region {
  code: string
  name: string
}

/**
 * Seeded ISO 3166-2 regions for a country (gap-C1a), from the public
 * endpoint — admin reference data, cached per country by react-query.
 */
export async function fetchRegions(countryCode: string): Promise<Region[]> {
  const { data } = await getRequest<AllResponse<Region>>(
    `core/public/regions?country_code=${countryCode}`,
  )
  return data as Region[]
}
