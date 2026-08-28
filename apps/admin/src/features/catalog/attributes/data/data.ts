import { type DataType } from './schema'

export const dataTypeColors = new Map<DataType, string>([
  ['text', 'bg-sky-100/40 text-sky-900 dark:text-sky-100 border-sky-200'],
  ['number', 'bg-violet-100/40 text-violet-900 dark:text-violet-100 border-violet-200'],
  ['boolean', 'bg-amber-100/40 text-amber-900 dark:text-amber-100 border-amber-200'],
  ['select', 'bg-teal-100/30 text-teal-900 dark:text-teal-100 border-teal-200'],
  ['multiselect', 'bg-fuchsia-100/40 text-fuchsia-900 dark:text-fuchsia-100 border-fuchsia-200'],
])
