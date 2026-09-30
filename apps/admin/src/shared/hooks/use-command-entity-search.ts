import { useQuery } from '@tanstack/react-query'
import { fetchProducts } from '@/features/catalog/products/api/products.api'
import { fetchOrders } from '@/features/order/orders/api/orders.api'
import { type Order } from '@/features/order/orders/data/schema'
import { type Product } from '@/features/catalog/products/data/schema'

export type CommandEntityGroup = 'products' | 'orders'

type CommandEntityResult = {
  group: CommandEntityGroup
  id: number
  label: string
  /** Navigate target with the `$id` placeholder replaced. */
  to: string
}

const MIN_QUERY_LENGTH = 2
const RESULT_LIMIT = 5

/**
 * Server-side entity lookup feeding the ⌘K command menu: products by
 * (any-language) title and orders by code. The query is debounced by the
 * caller; short queries are skipped entirely.
 */
export function useCommandEntitySearch(query: string): {
  results: CommandEntityResult[]
  isFetching: boolean
  enabled: boolean
} {
  const trimmed = query.trim()
  const enabled = trimmed.length >= MIN_QUERY_LENGTH

  const products = useQuery({
    queryKey: ['command-search', 'products', trimmed],
    queryFn: () =>
      fetchProducts({ 'filter[title]': trimmed, per_page: RESULT_LIMIT }),
    enabled,
    staleTime: 30_000,
  })

  const orders = useQuery({
    queryKey: ['command-search', 'orders', trimmed],
    queryFn: () =>
      fetchOrders({ 'filter[order_code]': trimmed, per_page: RESULT_LIMIT }),
    enabled,
    staleTime: 30_000,
  })

  const results: CommandEntityResult[] = [
    ...(products.data?.data ?? []).map((product: Product) => ({
      group: 'products' as const,
      id: product.id,
      label: productLabel(product),
      to: `/catalog/products/${product.id}/edit`,
    })),
    ...(orders.data?.data ?? []).map((order: Order) => ({
      group: 'orders' as const,
      id: order.id,
      label: order.order_code,
      to: `/order/orders/${order.id}/show`,
    })),
  ]

  return {
    results,
    isFetching: products.isFetching || orders.isFetching,
    enabled,
  }
}

/** Display name of a product: any translation title falling back to the id. */
function productLabel(product: Product): string {
  const title = product.translations.find(
    (translation) => translation.title?.trim()
  )?.title
  return title || `#${product.id}`
}
