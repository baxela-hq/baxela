export const statusTypes = new Map<string, string>([
  [
    'pending',
    'bg-amber-100/30 text-amber-900 dark:text-amber-200 border-amber-200',
  ],
  [
    'success',
    'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200',
  ],
  ['failed', 'bg-neutral-300/40 border-neutral-300'],
])

export const methodTypes = new Map<string, string>([
  ['manual', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
  ['paypal', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
  ['stripe', 'bg-sky-200/40 text-sky-900 dark:text-sky-100 border-sky-300'],
])
