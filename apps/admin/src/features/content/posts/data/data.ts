import { type PostStatus } from './schema'

export const statusBadgeVariants = new Map<PostStatus, string>([
  ['draft', 'bg-amber-100/60 text-amber-900 dark:text-amber-200 border-amber-300'],
  ['published', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
])
