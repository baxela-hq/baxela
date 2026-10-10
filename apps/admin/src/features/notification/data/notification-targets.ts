import { FeatureRoutes as OrderRoutes } from '@/features/order/orders/data/routes'
import { FeatureRoutes as TicketRoutes } from '@/features/support/tickets/data/routes'
import { FeatureRoutes as PostCommentRoutes } from '@/features/content/post-comments/data/routes'
import { type Notification } from './schema'

type DetailTarget = {
  to: typeof TicketRoutes.SHOW | typeof OrderRoutes.SHOW
  params: { id: string }
}

type OrderListTarget = {
  to: typeof OrderRoutes.LIST
  search: { 'filter[order_code]': string }
}

type PostCommentListTarget = {
  to: typeof PostCommentRoutes.LIST
  search: { 'filter[post_id]': string }
}

export type NotificationTarget =
  | DetailTarget
  | OrderListTarget
  | PostCommentListTarget

/**
 * Maps a notification's meta to the admin page it opens. Tickets and
 * orders link to their detail page; order rows stored before order_id
 * was tracked fall back to the orders list filtered by code; pending
 * post comments open the moderation queue filtered to the post.
 */
export function resolveNotificationTarget(
  notification: Notification
): NotificationTarget | undefined {
  const meta = notification.meta

  if (meta?.ticket_id != null) {
    return {
      to: TicketRoutes.SHOW,
      params: { id: String(meta.ticket_id) },
    }
  }

  if (meta?.order_id != null) {
    return {
      to: OrderRoutes.SHOW,
      params: { id: String(meta.order_id) },
    }
  }

  if (meta?.order_code) {
    return {
      to: OrderRoutes.LIST,
      search: { 'filter[order_code]': meta.order_code },
    }
  }

  if (meta?.post_comment_id != null && meta?.post_id != null) {
    return {
      to: PostCommentRoutes.LIST,
      search: { 'filter[post_id]': String(meta.post_id) },
    }
  }

  return undefined
}
