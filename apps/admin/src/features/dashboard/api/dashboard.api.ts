import { getRequest } from '@/shared/lib/api-client'
import type { SingleResponse } from '@/shared/types/common.types'
import { type AuthStats, type OrderStats } from '../data/schema'

const ORDER_STATS_URL = 'order/admin/stats'
const AUTH_STATS_URL = 'auth/admin/stats'

export async function fetchOrderStats(): Promise<OrderStats> {
  const { data } = await getRequest<SingleResponse<OrderStats>>(ORDER_STATS_URL)
  return data
}

export async function fetchAuthStats(): Promise<AuthStats> {
  const { data } = await getRequest<SingleResponse<AuthStats>>(AUTH_STATS_URL)
  return data
}
