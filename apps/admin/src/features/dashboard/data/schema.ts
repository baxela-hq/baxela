import { type Order } from '@/features/order/orders/data/schema'

type MonthlyRevenue = {
  month: string
  total: string
}

type DailyOrders = {
  date: string
  count: number
}

type StatusCount = {
  status: string
  count: number
}

type TopProduct = {
  name: string
  quantity: number
}

/** Mirrors the GET order/admin/stats payload. */
export interface OrderStats {
  total_revenue: string
  revenue_change_percent: number | null
  orders_count: number
  orders_change_percent: number | null
  pending_orders_count: number
  cancelled_orders_count: number
  paid_orders_count: number
  avg_order_value: string | null
  revenue_by_month: MonthlyRevenue[]
  orders_per_day: DailyOrders[]
  orders_by_status: StatusCount[]
  top_products: TopProduct[]
  recent_orders: Order[]
}

/** Mirrors the GET auth/admin/stats payload. */
export interface AuthStats {
  customers_count: number
  new_customers_this_month: number
  customers_change_percent: number | null
}
