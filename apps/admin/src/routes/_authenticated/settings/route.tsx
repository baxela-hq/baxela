import { createFileRoute, redirect } from '@tanstack/react-router'

/**
 * The template settings subpages (profile/account/appearance/display/
 * notifications) were removed — the real store settings live at
 * /settings/store. Old URLs relocate there.
 */
export const Route = createFileRoute('/_authenticated/settings')({
  beforeLoad: () => {
    throw redirect({ to: '/settings/store' })
  },
})
