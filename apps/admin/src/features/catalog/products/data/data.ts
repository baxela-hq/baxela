import { type ProductStatus } from './schema'

export const callTypes = new Map<ProductStatus, string>([
  ['discontinued', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
  ['out_of_stock', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
  ['in_stock', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['inactive', 'bg-neutral-300/40 border-neutral-300'],
])


