import {
  CircleDot,
  Lock,
  MessageCircleReply,
  type LucideIcon,
} from 'lucide-react'
import { type TicketStatus } from './schema'

/** List-column status icons; the show page keeps the colored badge below. */
export const statusIcons = new Map<TicketStatus, LucideIcon>([
  ['open', CircleDot],
  ['answered', MessageCircleReply],
  ['closed', Lock],
])

export const statusBadgeVariants = new Map<TicketStatus, string>([
  ['open', 'bg-amber-100/60 text-amber-900 dark:text-amber-200 border-amber-300'],
  [
    'answered',
    'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200',
  ],
  ['closed', 'bg-muted text-muted-foreground border-border'],
])
