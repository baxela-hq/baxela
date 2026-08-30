import { type ShipmentStatus } from './schema'

export const statusColors = new Map<ShipmentStatus, string>([
  ['pending', 'bg-amber-100/30 text-amber-900 dark:text-amber-200 border-amber-200'],
  ['packed', 'bg-blue-100/30 text-blue-900 dark:text-blue-200 border-blue-200'],
  ['shipped', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['in_transit', 'bg-indigo-100/30 text-indigo-900 dark:text-indigo-200 border-indigo-200'],
  ['delivered', 'bg-green-100/30 text-green-900 dark:text-green-200 border-green-200'],
  ['failed', 'bg-red-100/30 text-red-900 dark:text-red-200 border-red-200'],
])
