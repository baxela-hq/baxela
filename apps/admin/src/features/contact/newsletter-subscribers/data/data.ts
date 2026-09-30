import { type NewsletterSubscriberStatus } from './schema'

export const statusBadgeVariants = new Map<NewsletterSubscriberStatus, string>([
  ['subscribed', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['unsubscribed', 'bg-muted text-muted-foreground border-border'],
])
