
export const statusTypes = new Map<string, string>([
  ['draft', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
  ['pending_payment', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
  ['paid', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['processing', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['shipped', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['completed', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  ['cancelled', 'bg-neutral-300/40 border-neutral-300'],
  ['refunded', 'bg-neutral-300/40 border-neutral-300'],
])


