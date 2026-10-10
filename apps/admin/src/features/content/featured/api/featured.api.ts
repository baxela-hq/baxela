import { getRequest, putRequest } from '@/shared/lib/api-client'
import type { SingleResponse } from '@/shared/types/common.types'
import { type Featured, type UpdateFeaturedPayload } from '../data/schema'

const BASE_URL = 'content/admin/featured'

export async function fetchFeatured(): Promise<Featured> {
  const { data } = await getRequest<SingleResponse<Featured>>(BASE_URL)
  return data as Featured
}

export function updateFeatured(payload: UpdateFeaturedPayload) {
  return putRequest<Featured, UpdateFeaturedPayload>(BASE_URL, payload)
}
