import { getRequest } from '@/shared/lib/api-client'
import type { AllResponse } from '@/shared/types/common.types'
import type { Country } from '@/shared/types/locale.types'

const BASE_URL = 'core/admin/countries'

export async function fetchCountries(): Promise<Country[]> {
  const { data } = await getRequest<AllResponse<Country>>(BASE_URL)
  return data as Country[]
}
