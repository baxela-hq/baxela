import { createFileRoute, redirect } from '@tanstack/react-router'

/**
 * The template settings subpages are gone — the real store settings live
 * at /settings/store. Pure URL-relocation redirect for bare /settings.
 */
export const Route = createFileRoute('/_authenticated/settings/')({
  beforeLoad: () => {
    throw redirect({ to: '/settings/store' })
  },
})
