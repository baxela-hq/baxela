import { type ContactMessageStatus } from './schema'

export const statusBadgeVariants = new Map<ContactMessageStatus, string>([
  [
    'unread',
    'bg-amber-100/60 text-amber-900 dark:text-amber-200 border-amber-300',
  ],
  ['read', 'bg-sky-100/40 text-sky-900 dark:text-sky-200 border-sky-300'],
  [
    'replied',
    'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200',
  ],
  ['archived', 'bg-muted text-muted-foreground border-border'],
])
