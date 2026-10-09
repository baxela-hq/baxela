export const statusBadgeVariants = new Map<string, string>([
  ['draft', 'bg-amber-100/60 text-amber-900 dark:text-amber-200 border-amber-300'],
  ['published', 'bg-teal-100/30 text-teal-900 dark:text-teal-200 border-teal-200'],
  // admin-only derived state: published post with a future publish date
  ['scheduled', 'bg-sky-100/50 text-sky-900 dark:text-sky-200 border-sky-300'],
])
