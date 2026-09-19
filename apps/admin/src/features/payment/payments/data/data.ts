import {
  Banknote,
  CheckCircle,
  Circle,
  CircleOff,
  CreditCard,
  Wallet,
  type LucideIcon,
} from 'lucide-react'

export const statusIcons = new Map<string, LucideIcon>([
  ['pending', Circle],
  ['success', CheckCircle],
  ['failed', CircleOff],
])

export const methodIcons = new Map<string, LucideIcon>([
  ['manual', Banknote],
  ['paypal', Wallet],
  ['stripe', CreditCard],
])
