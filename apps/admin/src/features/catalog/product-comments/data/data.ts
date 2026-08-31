import { type ProductCommentStatus } from './schema'

export const statusBadgeVariants = new Map<ProductCommentStatus, string>([
  ['pending', 'bg-amber-100/60 text-amber-900 dark:text-amber-200 border-amber-300'],
  ['approved', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['rejected', 'bg-red-100/50 text-red-900 dark:text-red-200 border-red-300'],
])
